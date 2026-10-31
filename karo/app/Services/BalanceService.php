<?php

namespace App\Services;

use App\Models\Charge;
use App\Models\Payment;
use App\Models\Student;
use App\Support\Money;
use Illuminate\Support\Carbon;

/**
 * Situation financière d'un étudiant, par année académique ET par devise (jamais d'addition entre devises).
 *
 *  Créances actives (A)        = Σ montants des créances actives
 *  Ajustements approuvés (R)   = Σ bourses, réductions, exonérations, prises en charge, révisions (− hausses)
 *  Net dû (N)                  = A − R
 *  Règlements affectés (S)     = Σ affectations effectives des paiements validés (devise de la créance)
 *  Solde dû (D)                = Σ par créance max(0, net − réglé)
 *  Exigible (E)                = Σ par créance max(0, tranches échues − réglé)   [retard si E > 0]
 *  Paiements en attente (P)    = Σ paiements soumis ou contrôlés (non encore validés)
 *  Confirmés banque (B)        = Σ montants rapprochés avec un relevé
 *  Non affecté / crédit (C)    = Σ disponible des paiements validés (y compris reports reçus)
 * Les sommes dues (D) et les crédits disponibles (C) sont présentés séparément, jamais compensés d'office.
 */
class BalanceService
{
    public function situation(Student $student, ?int $yearId = null, ?Carbon $asOf = null): array
    {
        $asOf ??= now();
        $charges = Charge::query()->with(['category', 'installments', 'academicYear', 'session'])
            ->where('student_id', $student->id)->where('status', 'active')
            ->when($yearId, fn ($q) => $q->where('academic_year_id', $yearId))
            ->orderBy('academic_year_id')->orderBy('id')->get();
        $payments = Payment::query()->where('student_id', $student->id)
            ->when($yearId, fn ($q) => $q->where('academic_year_id', $yearId))->get();

        $byCurrency = [];
        $lines = [];
        foreach ($charges as $charge) {
            $net = $charge->netAmount();
            $settled = $charge->settledAmount();
            $balance = Money::max(0, Money::sub($net, $settled));
            $dueSoFar = Money::add(...$charge->installments->filter(fn ($i) => $i->due_date->lte($asOf))->pluck('amount')->all());
            $dueSoFar = Money::min($dueSoFar, $net);
            $exigible = Money::max(0, $dueSoFar->minus(Money::of($settled)));
            $nextDue = $charge->installments->first(fn ($i) => $i->due_date->gt($asOf));
            $state = Money::isZero($balance) ? 'paid' : ($exigible->isPositive() ? 'overdue' : (Money::isPositive($settled) ? 'partial' : 'unpaid'));
            $lines[] = compact('charge', 'net', 'settled', 'balance', 'exigible', 'nextDue', 'state') + ['adjustments' => $charge->approvedAdjustments()];
            $c = &$byCurrency[$charge->currency];
            $c = $this->sum($c ?? [], [
                'charges' => $charge->amount, 'adjustments' => $charge->approvedAdjustments(), 'net' => $net, 'settled' => $settled,
                'balance' => (string) $balance, 'exigible' => (string) $exigible,
                'arrears' => $charge->origin === 'carried_over' ? (string) $balance : '0',
            ]);
            unset($c);
        }
        foreach ($payments as $p) {
            $c = &$byCurrency[$p->currency];
            $c = $this->sum($c ?? [], [
                'pending' => in_array($p->status, ['submitted', 'checked'], true) ? $p->amount : '0',
                'validated' => $p->status === 'validated' && $p->type === 'bank_deposit' ? $p->amount : '0',
                'bank_confirmed' => $p->status !== 'reversed' ? $p->bank_confirmed_amount : '0',
                'credit' => $p->status === 'validated' ? $p->availableAmount() : '0',
            ]);
            unset($c);
        }
        foreach ($byCurrency as $cur => $vals) {
            $byCurrency[$cur] = array_merge(array_fill_keys(['charges', 'adjustments', 'net', 'settled', 'balance', 'exigible', 'arrears', 'pending', 'validated', 'bank_confirmed', 'credit'], '0.00'), $vals);
        }
        ksort($byCurrency);

        return ['lines' => $lines, 'totals' => $byCurrency, 'payments' => $payments, 'as_of' => $asOf];
    }

    private function sum(array $acc, array $add): array
    {
        foreach ($add as $k => $v) {
            $acc[$k] = Money::money(Money::add($acc[$k] ?? 0, $v));
        }

        return $acc;
    }

    public static function stateLabel(string $state): string
    {
        return ['paid' => 'Soldée', 'partial' => 'Partiellement réglée', 'unpaid' => 'Non réglée (non échue)', 'overdue' => 'En retard'][$state] ?? $state;
    }
}
