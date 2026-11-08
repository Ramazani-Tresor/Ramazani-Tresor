<?php

namespace App\Services;

use App\Exceptions\BusinessException;
use App\Models\BankAccount;
use App\Models\ImportBatch;
use App\Models\Payment;
use App\Models\ReconciliationMatch;
use App\Models\StatementLine;
use App\Models\User;
use App\Support\Authz;
use App\Support\Money;
use App\Support\Settings;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Rapprochement entre les lignes de relevé (preuve bancaire) et les paiements enregistrés.
 *
 * Correspondance proposée selon : référence (normalisée), montant, devise, compte, date (tolérance paramétrable).
 *  - « exacte » : même compte et devise, même référence, montant égal au reste à rapprocher, date dans la tolérance ;
 *  - une correspondance exacte UNIQUE peut être appliquée automatiquement ;
 *  - plusieurs candidats (ambiguïté) ou des critères partiels : décision humaine obligatoire.
 * Rapprochements partiels et multiples autorisés (une ligne ↔ plusieurs paiements, et inversement).
 * Le rapprochement confirme un paiement ; il n'en crée jamais un nouveau.
 */
class ReconciliationService
{
    /** @return Collection<int, array{payment: Payment, score: int, exact: bool, criteria: array}> */
    public function candidates(StatementLine $line): Collection
    {
        $tolerance = Settings::int('reconciliation_date_tolerance_days');
        $remaining = Money::of($line->remaining());

        return Payment::query()->with('student')
            ->where('bank_account_id', $line->bank_account_id)
            ->where('currency', $line->currency)
            ->where('type', 'bank_deposit')
            ->whereNotIn('status', ['rejected', 'reversed'])
            ->whereColumn('bank_confirmed_amount', '<', 'amount')
            ->where(function ($q) use ($line, $tolerance) {
                $q->when($line->reference_normalized, fn ($w) => $w->orWhere('bank_reference_normalized', $line->reference_normalized))
                    ->orWhere(fn ($w) => $w->whereBetween('deposit_date', [
                        $line->operation_date->copy()->subDays($tolerance + 30)->toDateString(),
                        $line->operation_date->copy()->addDays($tolerance + 30)->toDateString(),
                    ]));
            })
            ->limit(200)->get()
            ->map(function (Payment $p) use ($line, $tolerance, $remaining) {
                $paymentRemaining = Money::sub($p->amount, $p->bank_confirmed_amount);
                $refOk = $line->reference_normalized && $line->reference_normalized === $p->bank_reference_normalized;
                $amountOk = Money::cmp($paymentRemaining, $remaining) === 0;
                $days = abs($line->operation_date->diffInDays($p->deposit_date, false));
                $dateOk = $days <= $tolerance;
                $score = ($refOk ? 50 : 0) + ($amountOk ? 30 : 0) + ($dateOk ? 20 : 0);

                return ['payment' => $p, 'score' => $score, 'exact' => $refOk && $amountOk && $dateOk,
                    'criteria' => ['reference' => $refOk, 'amount' => $amountOk, 'date' => $dateOk, 'days' => (int) $days]];
            })
            ->filter(fn ($c) => $c['score'] >= 30)
            ->sortByDesc('score')->values();
    }

    /** Rapprochement automatique des seules correspondances exactes et uniques. */
    public function autoMatch(ImportBatch $batch, User $user): array
    {
        $stats = ['matched' => 0, 'ambiguous' => 0, 'none' => 0];
        if (! Settings::bool('reconciliation_auto_exact')) {
            return $stats;
        }
        foreach ($batch->lines()->where('match_status', 'unmatched')->whereIn('review_status', ['not_required', 'verified'])->get() as $line) {
            $exact = $this->candidates($line)->where('exact', true)->values();
            if ($exact->count() === 1) {
                // la ligne ne doit pas non plus correspondre exactement à un autre paiement d'une autre ligne
                $this->match($line, $exact[0]['payment'], $line->remaining(), $user, 'Correspondance exacte unique', 'auto_exact', 100, $exact[0]['criteria']);
                $stats['matched']++;
            } elseif ($exact->count() > 1) {
                $stats['ambiguous']++;
            } else {
                $stats['none']++;
            }
        }
        AuditService::log('reconciliation.auto', $batch, "Rapprochement automatique : {$stats['matched']} exact(s), {$stats['ambiguous']} ambigu(s), {$stats['none']} sans correspondance");

        return $stats;
    }

    public function match(StatementLine $line, Payment $payment, string $amount, User $user, ?string $note = null, string $method = 'manual', int $score = 0, ?array $criteria = null): ReconciliationMatch
    {
        return DB::transaction(function () use ($line, $payment, $amount, $user, $note, $method, $score, $criteria) {
            Authz::need($user, 'bank.reconcile');
            $line = StatementLine::query()->lockForUpdate()->findOrFail($line->id);
            $payment = Payment::query()->lockForUpdate()->findOrFail($payment->id);
            YearGuard::assertWritable($payment->academic_year_id);
            if (! in_array($line->review_status, ['not_required', 'verified'], true)) {
                throw new BusinessException('Cette ligne de relevé est incertaine : elle doit être vérifiée avant tout rapprochement.');
            }
            if ($line->match_status === 'ignored') {
                throw new BusinessException('Cette ligne a été écartée.');
            }
            if (in_array($payment->status, ['rejected', 'reversed'], true) || $payment->type !== 'bank_deposit') {
                throw new BusinessException('Ce paiement ne peut pas être rapproché.');
            }
            if ($payment->bank_account_id !== $line->bank_account_id || $payment->currency !== $line->currency) {
                throw new BusinessException('Compte ou devise différents entre la ligne de relevé et le paiement.');
            }
            if (! Money::isPositive($amount)) {
                throw new BusinessException('Montant de rapprochement invalide.');
            }
            if (Money::cmp($amount, $line->remaining()) > 0) {
                throw new BusinessException('Montant supérieur au reste non rapproché de la ligne ('.Money::format($line->remaining(), $line->currency).').');
            }
            $paymentRemaining = Money::sub($payment->amount, $payment->bank_confirmed_amount);
            if (Money::cmp($amount, $paymentRemaining) > 0) {
                throw new BusinessException('Montant supérieur au reste non confirmé du paiement ('.Money::format($paymentRemaining, $payment->currency).').');
            }
            if ($method === 'manual' && trim((string) $note) === '' && Money::cmp($amount, $line->amount) !== 0) {
                throw new BusinessException('Un rapprochement partiel exige un commentaire.');
            }
            $match = ReconciliationMatch::create([
                'statement_line_id' => $line->id, 'payment_id' => $payment->id, 'amount' => Money::money($amount),
                'method' => $method, 'score' => $score, 'criteria' => $criteria, 'status' => 'active', 'note' => $note, 'created_by' => $user->id,
            ]);
            $this->refresh($line, $payment);
            AuditService::log('reconciliation.match', $match, "Rapprochement ligne #{$line->id} ↔ {$payment->number} : ".Money::format($amount, $line->currency), null, $criteria, $note);

            return $match;
        });
    }

    public function unmatch(ReconciliationMatch $match, User $user, string $reason): void
    {
        DB::transaction(function () use ($match, $user, $reason) {
            $match = ReconciliationMatch::query()->lockForUpdate()->findOrFail($match->id);
            if ($match->status !== 'active') {
                return;
            }
            $match->update(['status' => 'cancelled', 'cancelled_by' => $user->id, 'cancelled_at' => now(), 'note' => trim($match->note.' | Annulé : '.$reason, ' |')]);
            $this->refresh(StatementLine::query()->lockForUpdate()->findOrFail($match->statement_line_id), Payment::query()->lockForUpdate()->findOrFail($match->payment_id));
            AuditService::log('reconciliation.unmatch', $match, 'Annulation d’un rapprochement', null, null, $reason);
        });
    }

    public function review(StatementLine $line, bool $accept, User $user, array $corrections = [], ?string $note = null): StatementLine
    {
        return DB::transaction(function () use ($line, $accept, $user, $corrections, $note) {
            Authz::need($user, 'bank.review');
            $line = StatementLine::query()->lockForUpdate()->findOrFail($line->id);
            if ($line->review_status !== 'pending') {
                throw new BusinessException('Cette ligne ne nécessite pas de vérification.');
            }
            $old = $line->only(['operation_date', 'value_date', 'reference', 'amount']);
            if ($accept) {
                $patch = array_filter([
                    'operation_date' => $corrections['operation_date'] ?? null,
                    'value_date' => $corrections['value_date'] ?? null,
                    'reference' => $corrections['reference'] ?? null,
                    'amount' => isset($corrections['amount']) && $corrections['amount'] !== '' ? Money::money($corrections['amount']) : null,
                ], fn ($v) => $v !== null && $v !== '');
                if (isset($patch['reference'])) {
                    $patch['reference_normalized'] = PaymentService::normalizeReference($patch['reference']);
                }
                $line->fill($patch);
                if (! Money::isPositive($line->amount)) {
                    throw new BusinessException('Montant invalide.');
                }
                $line->fill(['review_status' => 'verified', 'reviewed_by' => $user->id, 'reviewed_at' => now()]);
            } else {
                $line->fill(['review_status' => 'rejected', 'match_status' => 'ignored', 'ignore_reason' => $note ?: 'Extraction rejetée', 'reviewed_by' => $user->id, 'reviewed_at' => now()]);
            }
            $line->save();
            AuditService::log('statement.review', $line, ($accept ? 'Vérification' : 'Rejet')." de la ligne #{$line->id}", $old, $line->only(array_keys($old)), $note);

            return $line;
        });
    }

    public function ignore(StatementLine $line, User $user, string $reason): StatementLine
    {
        if ($line->matches()->where('status', 'active')->exists()) {
            throw new BusinessException('Annulez d’abord les rapprochements de cette ligne.');
        }
        if (trim($reason) === '') {
            throw new BusinessException('Le motif est obligatoire.');
        }
        $line->update(['match_status' => 'ignored', 'ignore_reason' => $reason]);
        AuditService::log('statement.ignore', $line, "Ligne #{$line->id} écartée", null, null, $reason);

        return $line;
    }

    private function refresh(StatementLine $line, Payment $payment): void
    {
        $lineMatched = Money::money(ReconciliationMatch::query()->where('statement_line_id', $line->id)->where('status', 'active')->sum('amount'));
        $line->update([
            'matched_amount' => $lineMatched,
            'match_status' => Money::isZero($lineMatched) ? 'unmatched' : (Money::cmp($lineMatched, $line->amount) >= 0 ? 'matched' : 'partial'),
        ]);
        $confirmed = Money::money(ReconciliationMatch::query()->where('payment_id', $payment->id)->where('status', 'active')->sum('amount'));
        $payment->update([
            'bank_confirmed_amount' => $confirmed,
            'bank_status' => Money::isZero($confirmed) ? 'unconfirmed' : (Money::cmp($confirmed, $payment->amount) >= 0 ? 'confirmed' : 'partial'),
        ]);
    }

    /** Rapport : couverture des relevés, rapprochés, non rapprochés, écarts, anomalies. */
    public function report(?int $bankAccountId, Carbon $from, Carbon $to): array
    {
        $accounts = BankAccount::query()->with('bank')->when($bankAccountId, fn ($q) => $q->whereKey($bankAccountId))->get();
        $coverage = [];
        foreach ($accounts as $acc) {
            $batches = ImportBatch::query()->where('type', 'bank_statement')->where('bank_account_id', $acc->id)->where('status', 'completed')
                ->orderBy('period_start')->get(['number', 'period_start', 'period_end']);
            $gaps = [];
            $cursor = $from->copy()->startOfDay();
            foreach ($batches as $b) {
                if ($b->period_end->lt($from) || $b->period_start->gt($to)) {
                    continue;
                }
                if ($b->period_start->gt($cursor)) {
                    $gaps[] = [$cursor->toDateString(), $b->period_start->copy()->subDay()->toDateString()];
                }
                if ($b->period_end->gte($cursor)) {
                    $cursor = $b->period_end->copy()->addDay();
                }
            }
            if ($cursor->lte($to)) {
                $gaps[] = [$cursor->toDateString(), $to->toDateString()];
            }
            $coverage[] = ['account' => $acc, 'batches' => $batches, 'gaps' => $gaps];
        }
        $lines = StatementLine::query()->with('bankAccount.bank')->whereBetween('operation_date', [$from->toDateString(), $to->toDateString()])
            ->when($bankAccountId, fn ($q) => $q->where('bank_account_id', $bankAccountId));
        $payments = Payment::query()->with('student', 'bankAccount')->where('type', 'bank_deposit')->where('status', 'validated')
            ->whereBetween('deposit_date', [$from->toDateString(), $to->toDateString()])
            ->when($bankAccountId, fn ($q) => $q->where('bank_account_id', $bankAccountId));

        return [
            'coverage' => $coverage,
            'matched' => (clone $lines)->where('match_status', 'matched')->get(),
            'partial' => (clone $lines)->where('match_status', 'partial')->get(),
            'unmatched' => (clone $lines)->where('match_status', 'unmatched')->get(),
            'ignored' => (clone $lines)->where('match_status', 'ignored')->get(),
            'to_review' => (clone $lines)->where('review_status', 'pending')->get(),
            'unconfirmed_payments' => (clone $payments)->whereIn('bank_status', ['unconfirmed', 'partial'])->get(),
            'anomalous_payments' => Payment::query()->with('student')->whereNotNull('anomalies')->whereNotIn('status', ['rejected', 'reversed'])
                ->whereBetween('deposit_date', [$from->toDateString(), $to->toDateString()])->get(),
        ];
    }
}
