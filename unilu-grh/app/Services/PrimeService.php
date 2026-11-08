<?php

namespace App\Services;

use App\Exceptions\BusinessException;
use App\Models\AcademicYear;
use App\Models\AdministrativeSituation;
use App\Models\Employee;
use App\Models\PrimeAdjustment;
use App\Models\PrimeEntitlement;
use App\Models\PrimePayment;
use App\Models\PrimeRate;
use App\Models\PrimeType;
use App\Models\User;
use App\Support\Authz;
use App\Support\Money;
use Brick\Math\RoundingMode;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Droits à primes et paiements.
 *
 * Règles (paramétrables) :
 *  - Taux : le taux actif le plus spécifique (nombre de critères renseignés) pour le type, la date et l'année l'emporte.
 *  - Coefficient journalier : 0 avant l'engagement et à partir de la date de fin des droits (décès, mutation externe,
 *    départ) ; sinon le plus petit « % de prime » des situations administratives approuvées couvrant le jour (100 sinon).
 *  - Montant = taux × (Σ coefficients / jours de la période) si le type est au prorata ; sinon taux × coefficient du 1er jour.
 *  - Aucune nouvelle prime n'est générée pour une période commençant à/après la fin des droits.
 *  - Un droit n'est jamais modifié : un recalcul crée un ajustement signé ; un paiement validé n'est jamais supprimé.
 */
class PrimeService
{
    public function __construct(private NumberingService $numbers, private NotificationService $notifier) {}

    /** Taux applicable : critères vides = joker ; le plus grand nombre de critères correspondants l'emporte. */
    public function rateFor(Employee $e, PrimeType $type, Carbon $date, ?int $yearId): ?PrimeRate
    {
        $unitIds = [];
        if ($unit = $e->activeAssignment?->orgUnit) {
            $u = $unit;
            $guard = 0;
            while ($u && $guard++ < 20) {
                $unitIds[] = $u->id;
                $u = $u->parent;
            }
        }
        $candidates = PrimeRate::query()->where('prime_type_id', $type->id)->where('is_active', true)
            ->where(fn ($q) => $q->whereNull('academic_year_id')->orWhere('academic_year_id', $yearId))
            ->where(fn ($q) => $q->whereNull('valid_from')->orWhereDate('valid_from', '<=', $date))
            ->where(fn ($q) => $q->whereNull('valid_to')->orWhereDate('valid_to', '>=', $date))
            ->get();
        $best = null;
        $bestScore = -1;
        foreach ($candidates as $r) {
            $checks = [
                [$r->staff_family, $e->staff_family], [$r->staff_category_id, $e->staff_category_id],
                [$r->grade_id, $e->grade_id], [$r->job_function_id, $e->job_function_id],
            ];
            $score = 0;
            foreach ($checks as [$crit, $val]) {
                if ($crit === null) {
                    continue;
                }
                if ((string) $crit !== (string) $val) {
                    continue 2;
                }
                $score++;
            }
            if ($r->org_unit_id) {
                if (! in_array($r->org_unit_id, $unitIds, true)) {
                    continue;
                }
                $score++;
            }
            if ($r->academic_year_id) {
                $score++;
            }
            if ($score > $bestScore) {
                [$best, $bestScore] = [$r, $score];
            }
        }

        return $best;
    }

    /**
     * Calcule le ratio ouvrant droit sur une période.
     *
     * @return array{ratio: string, detail: array}
     */
    public function eligibility(Employee $e, PrimeType $type, Carbon $start, Carbon $end): array
    {
        $situations = AdministrativeSituation::query()->with('type')->where('employee_id', $e->id)
            ->whereIn('status', ['approved', 'closed'])->whereDate('start_date', '<=', $end)->get()
            ->filter(function ($s) use ($start) {
                $sEnd = $s->actual_end_date ?? $s->planned_end_date;

                return $sEnd === null || $sEnd->gte($start);
            });
        $begin = $e->engagement_date ?? $e->first_service_date;
        $rightsEnd = $e->rights_end_date;
        $days = (int) $start->diffInDays($end) + 1;
        $sum = '0';
        $firstCoef = null;
        $counts = ['full' => 0, 'reduced' => 0, 'none' => 0];
        $rules = [];
        for ($d = $start->copy(); $d->lte($end); $d->addDay()) {
            if (($begin && $d->lt($begin)) || ($rightsEnd && $d->gte($rightsEnd))) {
                $coef = 0;
                $rules['hors_droits'] = 'Hors période d’engagement ou après la fin des droits';
            } else {
                $coef = 100;
                foreach ($situations as $s) {
                    $sEnd = $s->actual_end_date ?? $s->planned_end_date;
                    if ($d->gte($s->start_date) && ($sEnd === null || $d->lte($sEnd))) {
                        $coef = min($coef, (int) $s->type->prime_percent);
                        $rules[$s->type->code] = $s->type->name.' : '.$s->type->prime_percent.' %';
                    }
                }
            }
            $firstCoef ??= $coef;
            $counts[$coef === 100 ? 'full' : ($coef === 0 ? 'none' : 'reduced')]++;
            $sum = (string) Money::add($sum, $coef);
        }
        $ratio = $type->prorata
            ? (string) Money::of($sum)->dividedBy($days * 100, 6, RoundingMode::HALF_UP)
            : (string) Money::of($firstCoef)->dividedBy(100, 6, RoundingMode::HALF_UP);

        return ['ratio' => $ratio, 'detail' => ['jours' => $days, 'jours_pleins' => $counts['full'], 'jours_reduits' => $counts['reduced'], 'jours_sans_droit' => $counts['none'], 'prorata' => (bool) $type->prorata, 'regles' => array_values($rules)]];
    }

    /** Agents susceptibles d'avoir droit sur la période (droits non terminés avant le début, engagés avant la fin). */
    public function eligibleQuery(Carbon $start, Carbon $end): Builder
    {
        return Employee::query()
            ->where(fn ($q) => $q->whereNull('rights_end_date')->orWhereDate('rights_end_date', '>', $start))
            ->where(fn ($q) => $q->whereNull('engagement_date')->orWhereDate('engagement_date', '<=', $end));
    }

    /**
     * Génère les droits d'une période (idempotent : clé agent + type + période).
     *
     * @return array{created:int, existing:int, no_rate:int, skipped:int}
     */
    public function generate(PrimeType $type, Carbon $start, Carbon $end, string $label, User $user, array $filters = []): array
    {
        Authz::need($user, 'primes.generate');
        if ($end->lt($start)) {
            throw new BusinessException('La fin de période précède son début.');
        }
        $year = AcademicYear::forDate($start);
        $stats = ['created' => 0, 'existing' => 0, 'no_rate' => 0, 'skipped' => 0];
        $q = $this->eligibleQuery($start, $end)->with(['activeAssignment.orgUnit.parent']);
        if (! empty($filters['staff_family'])) {
            $q->where('staff_family', $filters['staff_family']);
        }
        if (! empty($filters['org_unit_id'])) {
            $q->inUnit((int) $filters['org_unit_id']);
        }
        foreach ($q->get() as $e) {
            $key = $this->key($e->id, $type->id, $start, $end);
            if (PrimeEntitlement::query()->where('uniqueness_key', $key)->exists()) {
                $stats['existing']++;

                continue;
            }
            $rate = $this->rateFor($e, $type, $start, $year?->id);
            if (! $rate) {
                $stats['no_rate']++;

                continue;
            }
            $elig = $this->eligibility($e, $type, $start, $end);
            $amount = Money::mul($rate->amount, $elig['ratio']);
            try {
                PrimeEntitlement::create([
                    'employee_id' => $e->id, 'prime_type_id' => $type->id, 'prime_rate_id' => $rate->id, 'academic_year_id' => $year?->id,
                    'period_label' => $label, 'period_start' => $start->toDateString(), 'period_end' => $end->toDateString(),
                    'base_amount' => $rate->amount, 'amount' => (string) $amount, 'currency' => $rate->currency,
                    'eligible_ratio' => $elig['ratio'], 'calculation' => $elig['detail'],
                    'status' => $amount->isZero() ? 'suspended' : 'due', 'uniqueness_key' => $key, 'created_by' => $user->id,
                ]);
                $stats['created']++;
            } catch (UniqueConstraintViolationException) {
                $stats['existing']++; // génération concurrente : aucun doublon
            }
        }
        AuditService::log('primes.generate', null, "Génération {$type->name} {$label} : {$stats['created']} droit(s) créé(s), {$stats['existing']} existant(s), {$stats['no_rate']} sans taux", null, $stats);

        return $stats;
    }

    public function key(int $employeeId, int $typeId, Carbon $start, Carbon $end): string
    {
        return "{$employeeId}|{$typeId}|{$start->toDateString()}|{$end->toDateString()}";
    }

    /**
     * Recalcule les droits d'un agent dont la période se termine à/après $from (décès, mutation, situation rétroactive).
     * La différence est enregistrée comme ajustement signé ; le montant initial reste intact.
     */
    public function recalculate(Employee $e, Carbon $from, string $source, ?Model $origin = null, ?User $user = null): int
    {
        $n = 0;
        $e->refresh();
        foreach (PrimeEntitlement::query()->with(['adjustments', 'primeType'])->where('employee_id', $e->id)->whereDate('period_end', '>=', $from)->lockForUpdate()->get() as $ent) {
            $elig = $this->eligibility($e, $ent->primeType, $ent->period_start, $ent->period_end);
            $target = Money::mul($ent->base_amount, $elig['ratio']);
            $diff = Money::sub($target, $ent->netAmount());
            if (! $diff->isZero()) {
                PrimeAdjustment::create([
                    'prime_entitlement_id' => $ent->id, 'amount' => Money::money($diff), 'source' => $source,
                    'reason' => 'Recalcul ('.$source.') : droit ramené à '.Money::format($target, $ent->currency).' — '.implode(' ; ', $elig['detail']['regles']),
                    'origin_type' => $origin?->getMorphClass(), 'origin_id' => $origin?->getKey(), 'created_by' => $user?->id,
                ]);
                $n++;
            }
        }

        return $n;
    }

    public function adjust(PrimeEntitlement $ent, string $amount, string $reason, User $user): PrimeAdjustment
    {
        Authz::need($user, 'payments.create');
        if (Money::of($amount)->isZero() || trim($reason) === '') {
            throw new BusinessException('Un ajustement exige un montant non nul et un motif.');
        }
        $adj = PrimeAdjustment::create(['prime_entitlement_id' => $ent->id, 'amount' => Money::money($amount), 'reason' => $reason, 'source' => 'manual', 'created_by' => $user->id]);
        AuditService::log('primes.adjust', $ent, "Ajustement {$adj->amount} {$ent->currency}", null, $adj->toArray(), $reason);

        return $adj;
    }

    /**
     * Saisie d'un mouvement (statut « à valider »).
     *  - acompte : 0 < montant ≤ solde − montants en attente ;
     *  - paiement : par défaut le solde restant (les acomptes validés sont donc automatiquement déduits) ;
     *  - régularisation : montant négatif, uniquement en cas de trop-perçu, plafonné au trop-perçu.
     */
    public function record(PrimeEntitlement $ent, string $kind, ?string $amount, array $data, User $user): PrimePayment
    {
        Authz::need($user, 'payments.create');
        if (! in_array($kind, ['advance', 'payment', 'regularization'], true)) {
            throw new BusinessException('Type de mouvement inconnu.');
        }

        return DB::transaction(function () use ($ent, $kind, $amount, $data, $user) {
            $ent = PrimeEntitlement::query()->with(['adjustments', 'payments', 'employee'])->lockForUpdate()->findOrFail($ent->id);
            $this->assertPayable($ent, $data + ['kind' => $kind]);
            $available = Money::sub($ent->balance(), $ent->pendingAmount());
            if ($kind === 'payment' && ($amount === null || $amount === '')) {
                $amount = Money::money($available);
            }
            $value = Money::of($amount);
            if ($kind === 'regularization') {
                if (! Money::isNegative($ent->balance())) {
                    throw new BusinessException('Aucun trop-perçu à régulariser sur ce droit.');
                }
                if (! $value->isNegative()) {
                    $value = $value->negated();
                }
                if (Money::cmp($value, $available) < 0) {
                    throw new BusinessException('La régularisation dépasse le trop-perçu ('.Money::format($available->negated(), $ent->currency).').');
                }
            } else {
                if (! $value->isPositive()) {
                    throw new BusinessException('Le montant doit être positif.');
                }
                if (Money::cmp($value, $available) > 0) {
                    throw new BusinessException('Le montant dépasse le solde restant à payer ('.Money::format($available, $ent->currency).', mouvements en attente compris).');
                }
            }
            $p = PrimePayment::create([
                'number' => $this->numbers->next('prime_payment'), 'prime_entitlement_id' => $ent->id, 'employee_id' => $ent->employee_id,
                'kind' => $kind, 'amount' => Money::money($value), 'currency' => $ent->currency,
                'payment_date' => $data['payment_date'] ?? now()->toDateString(), 'mode' => $data['mode'] ?? null,
                'reference' => $data['reference'] ?? null, 'beneficiary' => $data['beneficiary'] ?? null, 'notes' => $data['notes'] ?? null,
                'status' => 'pending', 'created_by' => $user->id,
            ]);
            AuditService::log('payments.create', $p, "{$p->number} : {$kind} {$p->amount} {$p->currency} ({$ent->period_label})", null, $p->toArray());

            return $p;
        });
    }

    /** Paiements bloqués pour les périodes postérieures à la fin des droits ; après décès, un ayant droit doit être désigné. */
    private function assertPayable(PrimeEntitlement $ent, array $data): void
    {
        $e = $ent->employee;
        if ($e->rights_end_date && $ent->period_start->gte($e->rights_end_date) && Money::isPositive($ent->netAmount())) {
            throw new BusinessException('Période postérieure à la fin des droits : paiement ordinaire bloqué.');
        }
        if ($e->status === 'deceased' && empty($data['beneficiary']) && ($data['kind'] ?? null) !== 'regularization') {
            throw new BusinessException('Agent décédé : indiquez l’ayant droit bénéficiaire du paiement des sommes restant dues.');
        }
    }

    public function validate(PrimePayment $p, User $user): PrimePayment
    {
        Authz::need($user, 'payments.validate');
        Authz::differentFrom($user, $p->created_by, 'un paiement');

        return DB::transaction(function () use ($p, $user) {
            $p = PrimePayment::query()->lockForUpdate()->findOrFail($p->id);
            if ($p->status !== 'pending') {
                throw new BusinessException('Seul un mouvement « à valider » peut être validé.');
            }
            $ent = PrimeEntitlement::query()->with(['adjustments', 'payments', 'employee'])->lockForUpdate()->findOrFail($p->prime_entitlement_id);
            $after = Money::sub($ent->balance(), $p->amount);
            if ($p->kind !== 'regularization' && $after->isNegative()) {
                throw new BusinessException('Validation refusée : elle créerait un trop-perçu (solde actuel '.Money::format($ent->balance(), $ent->currency).').');
            }
            if ($p->kind === 'regularization' && $after->isPositive()) {
                throw new BusinessException('Validation refusée : la régularisation dépasse le trop-perçu actuel.');
            }
            $p->update(['status' => 'validated', 'validated_by' => $user->id, 'validated_at' => now()]);
            AuditService::log('payments.validate', $p, "Validation {$p->number}");
            $label = $p->kind === 'advance' ? 'advance_paid' : 'prime_paid';
            if ($p->kind !== 'regularization') {
                $this->notifier->toEmployee($ent->employee, $label, ($p->kind === 'advance' ? 'Un acompte' : 'Un paiement').' de '.Money::format($p->amount, $p->currency)." sur « {$ent->primeType?->name} — {$ent->period_label} » a été validé.", route('me.finances'));
            }

            return $p;
        });
    }

    public function reject(PrimePayment $p, string $reason, User $user): PrimePayment
    {
        Authz::need($user, 'payments.validate');
        if ($p->status !== 'pending') {
            throw new BusinessException('Seul un mouvement « à valider » peut être rejeté.');
        }
        $p->update(['status' => 'rejected', 'rejection_reason' => $reason, 'validated_by' => $user->id, 'validated_at' => now()]);
        AuditService::log('payments.reject', $p, "Rejet {$p->number}", null, null, $reason);

        return $p;
    }

    /** Contrepassation d'un mouvement validé : écriture inverse validée, l'original reste visible (statut « contrepassé »). */
    public function reverse(PrimePayment $p, string $reason, User $user): PrimePayment
    {
        Authz::need($user, 'payments.validate');
        if (trim($reason) === '') {
            throw new BusinessException('Le motif de contrepassation est obligatoire.');
        }

        return DB::transaction(function () use ($p, $reason, $user) {
            $p = PrimePayment::query()->lockForUpdate()->findOrFail($p->id);
            if ($p->status !== 'validated' || $p->kind === 'reversal') {
                throw new BusinessException('Seul un mouvement validé peut être contrepassé.');
            }
            $rev = PrimePayment::create([
                'number' => $this->numbers->next('prime_payment'), 'prime_entitlement_id' => $p->prime_entitlement_id, 'employee_id' => $p->employee_id,
                'kind' => 'reversal', 'amount' => Money::money(Money::of($p->amount)->negated()), 'currency' => $p->currency,
                'payment_date' => now()->toDateString(), 'mode' => 'offset', 'reference' => 'Contrepassation de '.$p->number,
                'notes' => $reason, 'status' => 'validated', 'reverses_id' => $p->id, 'created_by' => $user->id, 'validated_by' => $user->id, 'validated_at' => now(),
            ]);
            $p->update(['status' => 'reversed']);
            AuditService::log('payments.reverse', $p, "Contrepassation {$p->number} par {$rev->number}", null, null, $reason);

            return $rev;
        });
    }

    /**
     * Situation financière d'un agent, par devise (jamais d'addition entre devises).
     *
     * @return array<string, array{planned:string, net:string, paid:string, advances:string, balance:string, arrears:string, overpaid:string, unregularized_advances:string}>
     */
    public function summary(Employee $e, ?Carbon $asOf = null): array
    {
        $asOf ??= now();
        $out = [];
        foreach (PrimeEntitlement::query()->with(['adjustments', 'payments'])->where('employee_id', $e->id)->get() as $ent) {
            $c = $ent->currency;
            $out[$c] ??= ['planned' => '0.00', 'net' => '0.00', 'paid' => '0.00', 'advances' => '0.00', 'balance' => '0.00', 'arrears' => '0.00', 'overpaid' => '0.00', 'unregularized_advances' => '0.00'];
            $bal = $ent->balance();
            $out[$c]['planned'] = Money::sum([$out[$c]['planned'], $ent->amount]);
            $out[$c]['net'] = Money::sum([$out[$c]['net'], $ent->netAmount()]);
            $out[$c]['paid'] = Money::sum([$out[$c]['paid'], $ent->paidAmount()]);
            $out[$c]['advances'] = Money::sum([$out[$c]['advances'], $ent->advancesAmount()]);
            $out[$c]['balance'] = Money::sum([$out[$c]['balance'], $bal]);
            if (Money::isPositive($bal) && $ent->period_end->lt($asOf)) {
                $out[$c]['arrears'] = Money::sum([$out[$c]['arrears'], $bal]);
            }
            if (Money::isNegative($bal)) {
                $out[$c]['overpaid'] = Money::sum([$out[$c]['overpaid'], Money::of($bal)->negated()]);
            }
            if (Money::isPositive($ent->advancesAmount()) && Money::isPositive($bal)) {
                $out[$c]['unregularized_advances'] = Money::sum([$out[$c]['unregularized_advances'], $ent->advancesAmount()]);
            }
        }

        return $out;
    }
}
