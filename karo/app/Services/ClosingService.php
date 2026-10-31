<?php

namespace App\Services;

use App\Exceptions\BusinessException;
use App\Models\AcademicYear;
use App\Models\Charge;
use App\Models\ChargeAdjustment;
use App\Models\CreditOperation;
use App\Models\Payment;
use App\Models\PaymentContract;
use App\Models\StatementLine;
use App\Models\SyncOperation;
use App\Models\User;
use App\Support\Authz;
use App\Support\DbErrors;
use App\Support\Money;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * Clôture contrôlée d'une année académique.
 * Bloquants : paiements en attente, ajustements et opérations sur excédent en attente, conflits de synchronisation.
 * À signaler (acceptation explicite) : paiements validés non confirmés, lignes de relevé à vérifier ou non rapprochées,
 * contrats en brouillon, paiements avec anomalies.
 * Reports (sans duplication, clés déterministes) :
 *   - arriérés : ajustement « report » sur la créance d'origine + créance « reportée » dans l'année suivante, liées ;
 *   - crédits : opération de report exécutée + paiement de type « report » dans l'année suivante, liés.
 */
class ClosingService
{
    public function __construct(private NumberingService $numbering) {}

    public function checklist(AcademicYear $year): array
    {
        $blocking = [
            'Paiements soumis ou contrôlés non validés' => Payment::query()->where('academic_year_id', $year->id)->whereIn('status', ['submitted', 'checked'])->count(),
            'Ajustements en attente' => ChargeAdjustment::query()->where('status', 'pending')->whereHas('charge', fn ($c) => $c->where('academic_year_id', $year->id))->count(),
            'Opérations sur excédent en attente' => CreditOperation::query()->where('status', 'pending')->whereHas('sourcePayment', fn ($p) => $p->where('academic_year_id', $year->id))->count(),
            'Conflits de synchronisation non traités' => SyncOperation::query()->where('status', 'conflict')->count(),
        ];
        $warnings = [
            'Paiements validés non confirmés par la banque' => Payment::query()->where('academic_year_id', $year->id)->where('status', 'validated')->where('type', 'bank_deposit')->whereIn('bank_status', ['unconfirmed', 'partial'])->count(),
            'Lignes de relevé à vérifier' => StatementLine::query()->where('review_status', 'pending')->whereBetween('operation_date', [$year->starts_on, $year->ends_on])->count(),
            'Lignes de relevé non rapprochées' => StatementLine::query()->whereIn('match_status', ['unmatched', 'partial'])->whereBetween('operation_date', [$year->starts_on, $year->ends_on])->count(),
            'Contrats en brouillon' => PaymentContract::query()->where('academic_year_id', $year->id)->where('status', 'draft')->count(),
            'Paiements validés avec anomalies' => Payment::query()->where('academic_year_id', $year->id)->where('status', 'validated')->whereNotNull('anomalies')->count(),
        ];
        $next = $year->next();
        $arrears = Charge::query()->where('academic_year_id', $year->id)->where('status', 'active')->get()->filter(fn ($c) => Money::isPositive($c->balance()));
        $credits = Payment::query()->where('academic_year_id', $year->id)->where('status', 'validated')->get()->filter(fn ($p) => Money::isPositive($p->availableAmount()));

        return ['blocking' => $blocking, 'warnings' => $warnings, 'next' => $next, 'arrears_count' => $arrears->count(), 'credits_count' => $credits->count(),
            'can_close' => array_sum($blocking) === 0 && $next !== null && $next->status !== 'closed'];
    }

    public function close(AcademicYear $year, User $user, string $note, bool $acknowledgeWarnings): array
    {
        Authz::need($user, 'years.close');
        if ($year->status !== 'open') {
            throw new BusinessException('Seule une année ouverte peut être clôturée.');
        }
        $check = $this->checklist($year);
        if (array_sum($check['blocking']) > 0) {
            throw new BusinessException('Clôture impossible : traitez d’abord les éléments bloquants ('.implode(', ', array_keys(array_filter($check['blocking']))).').');
        }
        if (! $check['next']) {
            throw new BusinessException('Créez l’année académique suivante (liée à celle-ci) pour recevoir les reports d’arriérés et de crédits.');
        }
        if ($check['next']->status === 'closed') {
            throw new BusinessException('L’année suivante est clôturée.');
        }
        if (array_sum($check['warnings']) > 0 && ! $acknowledgeWarnings) {
            throw new BusinessException('Des points de vigilance subsistent : cochez la confirmation pour clôturer malgré eux.');
        }
        if (trim($note) === '') {
            throw new BusinessException('Un commentaire de clôture est obligatoire.');
        }

        return DB::transaction(function () use ($year, $user, $note, $check) {
            $year = AcademicYear::query()->lockForUpdate()->findOrFail($year->id);
            $next = $check['next'];
            if ($next->status === 'planned') {
                $next->update(['status' => 'open']);
            }
            $stats = ['arrears' => 0, 'credits' => 0];

            foreach (Charge::query()->where('academic_year_id', $year->id)->where('status', 'active')->lockForUpdate()->get() as $charge) {
                $balance = $charge->balance();
                if (! Money::isPositive($balance)) {
                    continue;
                }
                try {
                    $new = Charge::create([
                        'number' => $this->numbering->next('charge'),
                        'student_id' => $charge->student_id, 'academic_year_id' => $next->id,
                        'enrollment_id' => $charge->student->enrollmentFor($next->id)?->id,
                        'fee_category_id' => $charge->fee_category_id, 'fee_schedule_id' => null,
                        'currency' => $charge->currency, 'amount' => $balance, 'origin' => 'carried_over',
                        'carried_from_charge_id' => $charge->id, 'status' => 'active',
                        'uniqueness_key' => 'CO-'.$charge->id, 'notes' => "Arriéré reporté de {$year->code} ({$charge->number})",
                        'created_by' => $user->id,
                    ]);
                } catch (QueryException $e) {
                    if (DbErrors::isUniqueViolation($e)) {
                        continue; // déjà reporté (réouverture puis nouvelle clôture)
                    }
                    throw $e;
                }
                $new->installments()->create(['sequence' => 1, 'label' => 'Arriéré', 'amount' => $balance, 'due_date' => $next->starts_on]);
                ChargeAdjustment::create([
                    'number' => $this->numbering->next('adjustment'), 'charge_id' => $charge->id, 'type' => 'transfer_out',
                    'amount' => $balance, 'reason' => "Report vers {$next->code} ({$new->number})", 'status' => 'approved',
                    'requested_by' => $user->id, 'approved_by' => $user->id, 'approved_at' => now(),
                ]);
                $stats['arrears']++;
            }

            foreach (Payment::query()->where('academic_year_id', $year->id)->where('status', 'validated')->lockForUpdate()->get() as $payment) {
                $available = $payment->availableAmount();
                if (! Money::isPositive($available)) {
                    continue;
                }
                $uuid = CreditService::deterministicUuid('close-'.$payment->id.'-'.$year->id);
                if (CreditOperation::query()->where('client_uuid', $uuid)->exists()) {
                    continue;
                }
                $op = CreditOperation::create([
                    'number' => $this->numbering->next('credit_operation'), 'client_uuid' => $uuid, 'student_id' => $payment->student_id,
                    'source_payment_id' => $payment->id, 'type' => 'carry_forward', 'currency' => $payment->currency, 'amount' => $available,
                    'target_academic_year_id' => $next->id, 'status' => 'executed', 'reason' => "Report automatique à la clôture de {$year->code}",
                    'requested_by' => $user->id, 'approved_by' => $user->id, 'decided_at' => now(),
                ]);
                Payment::create([
                    'number' => $this->numbering->next('payment'), 'client_uuid' => CreditService::deterministicUuid('carry-op-'.$op->id),
                    'type' => 'carry_forward', 'student_id' => $payment->student_id, 'academic_year_id' => $next->id,
                    'amount' => $available, 'currency' => $payment->currency, 'bank_reference' => 'REPORT '.$payment->number,
                    'deposit_date' => $payment->deposit_date, 'status' => 'validated', 'bank_status' => 'not_applicable',
                    'origin_payment_id' => $payment->id, 'origin_credit_operation_id' => $op->id, 'channel' => 'system',
                    'created_by' => $user->id, 'validated_by' => $user->id, 'validated_at' => now(),
                ]);
                $stats['credits']++;
            }

            $report = "Clôture de {$year->code} par {$user->name} le ".now()->format('d/m/Y H:i')." : {$stats['arrears']} arriéré(s) et {$stats['credits']} crédit(s) reporté(s) vers {$next->code}. Vigilance : "
                .collect($check['warnings'])->filter()->map(fn ($n, $k) => "$k : $n")->implode(' ; ').'. Commentaire : '.$note;
            $year->update(['status' => 'closed', 'is_current' => false, 'closed_at' => now(), 'closed_by' => $user->id, 'closing_report' => $report]);
            if (! AcademicYear::query()->where('is_current', true)->exists()) {
                $next->update(['is_current' => true]);
            }
            AuditService::log('year.close', $year, $report, null, $stats, $note);

            return $stats;
        });
    }

    public function reopen(AcademicYear $year, User $user, string $reason): AcademicYear
    {
        Authz::need($user, 'years.reopen');
        if ($year->status !== 'closed') {
            throw new BusinessException('Cette année n’est pas clôturée.');
        }
        if (mb_strlen(trim($reason)) < 10) {
            throw new BusinessException('Une réouverture exige un motif détaillé (10 caractères minimum).');
        }
        $year->update(['status' => 'open']);
        AuditService::log('year.reopen', $year, "Réouverture exceptionnelle de {$year->code}", null, null, $reason);

        return $year;
    }
}
