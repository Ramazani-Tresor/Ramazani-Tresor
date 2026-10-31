<?php

namespace App\Services;

use App\Models\Charge;
use App\Models\Enrollment;
use App\Models\ExamSession;
use App\Models\ExamWaiver;
use App\Models\PaymentAllocation;
use App\Support\Money;
use Illuminate\Support\Carbon;

/**
 * Conformité FINANCIÈRE à une session d'examens (ne vaut pas admissibilité académique).
 *
 * Pour chaque étudiant inscrit et chaque devise :
 *   Requis  = Σ des montants nets des frais retenus pour la session ; si une date limite est fixée,
 *             seule la part des tranches échues à cette date est exigée.
 *   Réglé   = Σ des affectations effectives (paiements validés) ; si la session l'exige, seules les
 *             affectations de paiements confirmés par la banque comptent.
 *   Statut  = « autorisation exceptionnelle » si dérogation valide ; « en règle » si Réglé ≥ seuil % × Requis
 *             dans chaque devise ; « partiellement en règle » si un règlement existe ; sinon « solde dû ».
 */
class ExamClearanceService
{
    /** @return array<int, array{enrollment: Enrollment, status: string, details: array, waiver: ?ExamWaiver}> */
    public function evaluate(ExamSession $session, ?array $facultyIds = null, ?int $promotionId = null, ?Carbon $asOf = null): array
    {
        $asOf ??= now();
        $categoryIds = $session->fee_category_ids ?: [];
        $threshold = Money::of($session->threshold_percent ?? 100);
        $deadline = $session->payment_deadline;
        $waivers = ExamWaiver::query()->where('exam_session_id', $session->id)->where('status', 'active')
            ->whereDate('valid_until', '>=', $asOf->toDateString())->get()->keyBy('student_id');

        $enrollments = Enrollment::query()->with(['student', 'promotion.program.department'])
            ->where('academic_year_id', $session->academic_year_id)->where('state', 'active')
            ->when($promotionId, fn ($q) => $q->where('promotion_id', $promotionId))
            ->when($facultyIds !== null, fn ($q) => $q->whereHas('promotion.program.department', fn ($d) => $d->whereIn('faculty_id', $facultyIds ?: [0])))
            ->get()->sortBy(fn ($e) => $e->student->last_name);

        $out = [];
        foreach ($enrollments as $e) {
            $charges = Charge::query()->with('installments')->where('student_id', $e->student_id)->where('academic_year_id', $session->academic_year_id)
                ->where('status', 'active')->whereIn('fee_category_id', $categoryIds ?: [0])
                ->where(fn ($q) => $q->whereNull('exam_session_id')->orWhere('exam_session_id', $session->id))->get();
            $details = [];
            foreach ($charges as $c) {
                $net = Money::of($c->netAmount());
                $required = $deadline
                    ? Money::min(Money::add(...$c->installments->filter(fn ($i) => $i->due_date->lte($deadline))->pluck('amount')->all()), $net)
                    : $net;
                $settledQ = PaymentAllocation::query()->where('charge_id', $c->id)->where('status', 'active')
                    ->whereHas('payment', fn ($p) => $p->where('status', 'validated')
                        ->when($session->require_bank_confirmation, fn ($q) => $q->whereIn('bank_status', ['confirmed', 'not_applicable'])));
                $settled = Money::of($settledQ->sum('charge_amount'));
                $d = &$details[$c->currency];
                $d = ['required' => Money::money(Money::add($d['required'] ?? 0, $required)), 'settled' => Money::money(Money::add($d['settled'] ?? 0, Money::min($settled, $required)))];
                unset($d);
            }
            $allOk = true;
            $anyPaid = false;
            foreach ($details as $cur => $d) {
                $needed = Money::percentOf($d['required'], $threshold);
                $details[$cur]['ratio'] = Money::ratio($d['settled'], $d['required']);
                if (Money::cmp($d['settled'], $needed) < 0) {
                    $allOk = false;
                }
                if (Money::isPositive($d['settled'])) {
                    $anyPaid = true;
                }
            }
            $waiver = $waivers->get($e->student_id);
            $status = $waiver ? 'waiver' : ($allOk ? 'cleared' : ($anyPaid ? 'partial' : 'due'));
            $out[] = ['enrollment' => $e, 'status' => $status, 'details' => $details, 'waiver' => $waiver];
        }

        return $out;
    }
}
