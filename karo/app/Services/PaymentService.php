<?php

namespace App\Services;

use App\Exceptions\BusinessException;
use App\Models\BankAccount;
use App\Models\Charge;
use App\Models\IssuedDocument;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\PaymentEvent;
use App\Models\ReconciliationMatch;
use App\Models\Student;
use App\Models\User;
use App\Support\Authz;
use App\Support\DbErrors;
use App\Support\Money;
use App\Support\Settings;
use Brick\Math\BigDecimal;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * Cycle d'un paiement (bordereau bancaire) :
 *   submitted (saisi, soumis au contrôle) → checked (justificatif contrôlé) → validated (affectations effectives)
 *   submitted|checked → rejected ; validated → reversed (contrepassation, jamais de suppression).
 * La confirmation bancaire (rapprochement avec un relevé) est un axe distinct (bank_status).
 * Un versement réparti entre plusieurs frais reste UN paiement avec plusieurs affectations.
 */
class PaymentService
{
    public function __construct(private NumberingService $numbering, private ConversionService $conversion) {}

    /**
     * @param  array{client_uuid:string, student_id:int, academic_year_id:int, bank_account_id:int, bank_reference:?string,
     *               deposit_date:string, amount:mixed, currency:string, depositor_name?:?string}  $data
     * @param  array<int, array{charge_id:int, amount:mixed}>  $allocations
     * @return array{0: Payment, 1: bool} [paiement, déjà existant]
     */
    public function submit(array $data, array $allocations, ?array $proof, User $user, string $channel = 'web', ?string $deviceId = null): array
    {
        if ($existing = Payment::query()->where('client_uuid', $data['client_uuid'])->first()) {
            return [$existing, true];
        }
        Authz::need($user, 'payments.create');
        $year = YearGuard::assertWritable((int) $data['academic_year_id']);
        $student = Student::query()->findOrFail($data['student_id']);
        if (! $user->canAccessStudent($student, 'payments.create')) {
            throw new BusinessException('Ce dossier étudiant est hors de votre périmètre.');
        }
        if (! $student->enrollmentFor($year->id) && $student->charges()->where('academic_year_id', $year->id)->doesntExist()) {
            throw new BusinessException("L'étudiant n'a ni inscription ni créance pour l'année {$year->code}.");
        }
        $account = BankAccount::query()->with('bank')->find($data['bank_account_id']);
        if (! $account || ! $account->is_active) {
            throw new BusinessException('Compte bénéficiaire invalide ou inactif.');
        }
        $amount = Money::of(Money::money($data['amount'] ?? 0));
        if (! $amount->isPositive()) {
            throw new BusinessException('Le montant du versement doit être positif.');
        }
        if ($account->currency !== $data['currency']) {
            throw new BusinessException("Devise incohérente : le compte {$account->account_number} est en {$account->currency}, le bordereau en {$data['currency']}.");
        }
        if (strtotime($data['deposit_date']) > strtotime('today 23:59:59')) {
            throw new BusinessException('La date du versement ne peut pas être dans le futur.');
        }
        if (Settings::bool('require_proof_for_submission') && ! $proof) {
            throw new BusinessException('Le justificatif (bordereau numérisé) est obligatoire pour soumettre au contrôle.');
        }

        $prepared = $this->prepareAllocations($student, $year->id, $account, $data['currency'], $amount, $allocations, $data['deposit_date']);
        $normalized = self::normalizeReference($data['bank_reference'] ?? null);
        $anomalies = $this->detectAnomalies($student, $account, $normalized, (string) $amount, $data['deposit_date'], $proof, null);

        try {
            $payment = DB::transaction(function () use ($data, $student, $year, $account, $amount, $normalized, $anomalies, $proof, $prepared, $user, $channel, $deviceId) {
                $payment = Payment::create([
                    'number' => $this->numbering->next('payment'),
                    'client_uuid' => $data['client_uuid'],
                    'type' => 'bank_deposit',
                    'student_id' => $student->id,
                    'academic_year_id' => $year->id,
                    'bank_account_id' => $account->id,
                    'bank_reference' => $data['bank_reference'] ?? null,
                    'bank_reference_normalized' => $normalized,
                    'deposit_date' => $data['deposit_date'],
                    'amount' => (string) $amount,
                    'currency' => $data['currency'],
                    'depositor_name' => $data['depositor_name'] ?? null,
                    'proof_path' => $proof['path'] ?? null,
                    'proof_sha256' => $proof['sha256'] ?? null,
                    'status' => 'submitted',
                    'bank_status' => 'unconfirmed',
                    'anomalies' => $anomalies ?: null,
                    'channel' => $channel,
                    'device_id' => $deviceId,
                    'created_by' => $user->id,
                ]);
                foreach ($prepared as $p) {
                    PaymentAllocation::create($p + ['payment_id' => $payment->id, 'status' => 'proposed', 'kind' => 'direct', 'created_by' => $user->id]);
                }
                $this->event($payment, null, 'submitted', 'Saisie du bordereau'.($anomalies ? ' — '.count($anomalies).' anomalie(s) détectée(s)' : ''), $user);
                AuditService::log('payment.submit', $payment, "Bordereau {$payment->number} : ".Money::format($amount, $payment->currency)." pour {$student->matricule}", null, ['reference' => $payment->bank_reference, 'allocations' => $prepared]);

                return $payment;
            });
        } catch (QueryException $e) {
            if (DbErrors::isUniqueViolation($e) && ($existing = Payment::query()->where('client_uuid', $data['client_uuid'])->first())) {
                return [$existing, true];
            }
            throw $e;
        }

        return [$payment, false];
    }

    /** Modification des affectations proposées tant que le paiement n'est pas validé. */
    public function updateAllocations(Payment $payment, array $allocations, User $user): Payment
    {
        return DB::transaction(function () use ($payment, $allocations, $user) {
            $payment = Payment::query()->lockForUpdate()->findOrFail($payment->id);
            YearGuard::assertWritable($payment->academic_year_id);
            if (! in_array($payment->status, ['submitted', 'checked'], true)) {
                throw new BusinessException('Les affectations d’un paiement validé ne se modifient que par compensation ou contrepassation.');
            }
            $prepared = $this->prepareAllocations($payment->student, $payment->academic_year_id, $payment->bankAccount, $payment->currency, Money::of($payment->amount), $allocations, $payment->deposit_date->toDateString());
            $old = $payment->allocations()->where('status', 'proposed')->get(['charge_id', 'amount'])->toArray();
            $payment->allocations()->where('status', 'proposed')->delete(); // proposées seulement : aucune valeur comptable
            foreach ($prepared as $p) {
                PaymentAllocation::create($p + ['payment_id' => $payment->id, 'status' => 'proposed', 'kind' => 'direct', 'created_by' => $user->id]);
            }
            AuditService::log('payment.allocations', $payment, "Affectations proposées modifiées ({$payment->number})", ['allocations' => $old], ['allocations' => $prepared]);

            return $payment;
        });
    }

    public function check(Payment $payment, User $user, ?string $note = null): Payment
    {
        return DB::transaction(function () use ($payment, $user, $note) {
            Authz::need($user, 'payments.check');
            $payment = Payment::query()->lockForUpdate()->findOrFail($payment->id);
            YearGuard::assertWritable($payment->academic_year_id);
            if (Settings::bool('segregation_of_duties') && $payment->created_by === $user->id) {
                throw new BusinessException('Séparation des tâches : l’auteur de la saisie ne peut pas contrôler son propre bordereau.');
            }
            if ($payment->status !== 'submitted') {
                throw new BusinessException('Seul un paiement soumis peut être contrôlé.');
            }
            if (Settings::bool('segregation_of_duties') && $payment->created_by === $user->id) {
                throw new BusinessException('Séparation des tâches : l’auteur de la saisie ne peut pas contrôler son propre bordereau.');
            }
            if (! $payment->proof_path) {
                throw new BusinessException('Justificatif manquant : contrôle impossible.');
            }
            $payment->update(['status' => 'checked', 'checked_by' => $user->id, 'checked_at' => now()]);
            $this->event($payment, 'submitted', 'checked', $note ?: 'Justificatif contrôlé', $user);
            AuditService::log('payment.check', $payment, "Contrôle du justificatif {$payment->number}", null, null, $note);

            return $payment;
        });
    }

    public function validate(Payment $payment, User $user, bool $acknowledgeAnomalies = false, ?string $note = null): Payment
    {
        return DB::transaction(function () use ($payment, $user, $acknowledgeAnomalies, $note) {
            Authz::need($user, 'payments.validate');
            $payment = Payment::query()->lockForUpdate()->findOrFail($payment->id);
            YearGuard::assertWritable($payment->academic_year_id);
            if (Settings::bool('segregation_of_duties') && $payment->created_by === $user->id) {
                throw new BusinessException('Séparation des tâches : l’auteur de la saisie ne peut pas valider son propre bordereau.');
            }
            $allowedFrom = Settings::bool('require_check_before_validation') ? ['checked'] : ['submitted', 'checked'];
            if (! in_array($payment->status, $allowedFrom, true)) {
                throw new BusinessException($payment->status === 'submitted' ? 'Le justificatif doit d’abord être contrôlé.' : 'Ce paiement ne peut pas être validé dans son état actuel.');
            }
            if (Settings::bool('segregation_of_duties') && $payment->created_by === $user->id) {
                throw new BusinessException('Séparation des tâches : l’auteur de la saisie ne peut pas valider son propre bordereau.');
            }
            // Contrôles recalculés au moment de la validation (l'état a pu changer depuis la saisie).
            $anomalies = $this->detectAnomalies($payment->student, $payment->bankAccount, $payment->bank_reference_normalized, (string) $payment->amount,
                $payment->deposit_date->toDateString(), $payment->proof_path ? ['path' => $payment->proof_path] : null, $payment->id);
            if ($anomalies && ! $acknowledgeAnomalies) {
                throw new BusinessException('Anomalies à examiner avant validation : '.implode(' ; ', array_column($anomalies, 'message')), ['anomalies' => $anomalies]);
            }
            $allocations = $payment->allocations()->where('status', 'proposed')->get();
            $total = Money::add(...$allocations->pluck('amount')->all());
            if (Money::cmp($total, $payment->amount) > 0) {
                throw new BusinessException('Les affectations dépassent le montant du versement.');
            }
            $charges = Charge::query()->whereIn('id', $allocations->pluck('charge_id')->unique()->sort()->values())->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $perCharge = [];
            foreach ($allocations as $a) {
                $perCharge[$a->charge_id] = Money::add($perCharge[$a->charge_id] ?? 0, $a->charge_amount);
            }
            foreach ($perCharge as $chargeId => $sum) {
                $charge = $charges[$chargeId];
                if ($charge->status !== 'active') {
                    throw new BusinessException("La créance {$charge->number} n'est plus active.");
                }
                if (Money::cmp($sum, $charge->balance()) > 0) {
                    throw new BusinessException("Affectation supérieure au solde de la créance {$charge->number} (solde ".Money::format($charge->balance(), $charge->currency).') : un autre paiement a pu être validé entre-temps. Corrigez les affectations.');
                }
            }
            $payment->allocations()->where('status', 'proposed')->update(['status' => 'active']);
            $from = $payment->status;
            $payment->update(['status' => 'validated', 'validated_by' => $user->id, 'validated_at' => now(), 'anomalies' => $anomalies ?: null]);
            $this->event($payment, $from, 'validated', trim(($note ?: 'Validation').($anomalies ? ' — anomalies examinées' : '')), $user);
            AuditService::log('payment.validate', $payment, "Validation {$payment->number} : ".Money::format($payment->amount, $payment->currency).', affecté '.Money::format($total, $payment->currency), null, ['anomalies' => $anomalies], $note);

            return $payment;
        });
    }

    public function reject(Payment $payment, User $user, string $reason): Payment
    {
        if (trim($reason) === '') {
            throw new BusinessException('Le motif du rejet est obligatoire.');
        }

        return DB::transaction(function () use ($payment, $user, $reason) {
            Authz::need($user, $user->hasPermission('payments.check') ? 'payments.check' : 'payments.validate');
            $payment = Payment::query()->lockForUpdate()->findOrFail($payment->id);
            YearGuard::assertWritable($payment->academic_year_id);
            if (! in_array($payment->status, ['submitted', 'checked'], true)) {
                throw new BusinessException('Seul un paiement non validé peut être rejeté. Un paiement validé se corrige par contrepassation.');
            }
            $from = $payment->status;
            $payment->allocations()->where('status', 'proposed')->update(['status' => 'reversed', 'reversed_by' => $user->id, 'reversed_at' => now()]);
            $payment->update(['status' => 'rejected', 'rejected_by' => $user->id, 'rejected_at' => now(), 'rejection_reason' => $reason]);
            $this->event($payment, $from, 'rejected', $reason, $user);
            AuditService::log('payment.reject', $payment, "Rejet {$payment->number}", null, null, $reason);

            return $payment;
        });
    }

    /** Contrepassation : annule les effets d'un paiement validé, sans suppression. */
    public function reverse(Payment $payment, User $user, string $reason): Payment
    {
        if (trim($reason) === '') {
            throw new BusinessException('Le motif de la contrepassation est obligatoire.');
        }

        return DB::transaction(function () use ($payment, $user, $reason) {
            Authz::need($user, 'payments.reverse');
            $payment = Payment::query()->lockForUpdate()->findOrFail($payment->id);
            YearGuard::assertWritable($payment->academic_year_id);
            if ($payment->status !== 'validated') {
                throw new BusinessException('Seul un paiement validé peut être contrepassé.');
            }
            if ($payment->creditOperations()->where('status', 'executed')->whereIn('type', ['refund', 'carry_forward'])->exists()) {
                throw new BusinessException('Un remboursement ou un report a été exécuté sur ce paiement : régularisez-le d’abord.');
            }
            $payment->creditOperations()->where('status', 'pending')->update(['status' => 'rejected', 'decision_note' => 'Paiement source contrepassé', 'decided_at' => now(), 'approved_by' => $user->id]);
            $payment->allocations()->where('status', 'active')->update(['status' => 'reversed', 'reversed_by' => $user->id, 'reversed_at' => now()]);
            ReconciliationMatch::query()->where('payment_id', $payment->id)->where('status', 'active')->get()
                ->each(fn ($m) => app(ReconciliationService::class)->unmatch($m, $user, 'Paiement contrepassé : '.$reason));
            $payment->update(['status' => 'reversed', 'reversed_by' => $user->id, 'reversed_at' => now(), 'reversal_reason' => $reason]);
            IssuedDocument::query()->where('subject_type', $payment->getMorphClass())->where('subject_id', $payment->id)->update(['status' => 'cancelled']);
            $this->event($payment, 'validated', 'reversed', $reason, $user);
            AuditService::log('payment.reverse', $payment, "Contrepassation {$payment->number}", null, null, $reason);

            return $payment;
        });
    }

    /**
     * Contrôles automatiques : référence déjà utilisée, doublon probable, justificatif manquant.
     *
     * @return array<int, array{code:string, message:string}>
     */
    public function detectAnomalies(Student $student, BankAccount $account, ?string $normalizedRef, string $amount, string $date, ?array $proof, ?int $ignoreId): array
    {
        $out = [];
        if ($normalizedRef) {
            $dup = Payment::query()->where('bank_account_id', $account->id)->where('bank_reference_normalized', $normalizedRef)
                ->whereNotIn('status', ['rejected', 'reversed'])->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))->first();
            if ($dup) {
                $out[] = ['code' => 'duplicate_reference', 'message' => "Référence bancaire déjà utilisée par le paiement {$dup->number}"];
            }
        } elseif (Settings::bool('statement_reference_required')) {
            $out[] = ['code' => 'missing_reference', 'message' => 'Référence bancaire absente'];
        }
        $same = Payment::query()->where('student_id', $student->id)->where('bank_account_id', $account->id)
            ->where('amount', Money::money($amount))->whereDate('deposit_date', $date)
            ->whereNotIn('status', ['rejected', 'reversed'])->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))->first();
        if ($same) {
            $out[] = ['code' => 'possible_duplicate', 'message' => "Même étudiant, compte, montant et date que le paiement {$same->number}"];
        }
        if (! $proof) {
            $out[] = ['code' => 'missing_proof', 'message' => 'Justificatif manquant'];
        }

        return $out;
    }

    private function prepareAllocations(Student $student, int $yearId, BankAccount $account, string $currency, BigDecimal $amount, array $allocations, string $date): array
    {
        $prepared = [];
        $sum = Money::of(0);
        foreach ($allocations as $i => $a) {
            $value = Money::of(Money::money($a['amount'] ?? 0));
            if ($value->isZero()) {
                continue;
            }
            $n = $i + 1;
            if ($value->isNegative()) {
                throw new BusinessException("Affectation {$n} : montant invalide.");
            }
            $charge = Charge::query()->with('category.bankAccounts')->find($a['charge_id'] ?? 0);
            if (! $charge || $charge->student_id !== $student->id || $charge->status !== 'active') {
                throw new BusinessException("Affectation {$n} : créance introuvable pour cet étudiant.");
            }
            $allowed = $charge->category->bankAccounts;
            if ($allowed->isNotEmpty() && ! $allowed->contains('id', $account->id)) {
                throw new BusinessException("Le compte {$account->account_number} n'est pas autorisé pour « {$charge->category->label} ».");
            }
            [$chargeAmount, $rate] = $this->conversion->convert((string) $value, $currency, $charge->currency, $date);
            if (Money::cmp($chargeAmount, $charge->balance()) > 0) {
                throw new BusinessException("Affectation {$n} : montant supérieur au solde de « {$charge->category->label} » (".Money::format($charge->balance(), $charge->currency).').');
            }
            $prepared[] = ['charge_id' => $charge->id, 'amount' => (string) $value, 'charge_amount' => $chargeAmount, 'exchange_rate_id' => $rate?->id];
            $sum = $sum->plus($value);
        }
        if (Money::cmp($sum, $amount) > 0) {
            throw new BusinessException('Le total affecté ('.Money::format($sum, $currency).') dépasse le montant reçu ('.Money::format($amount, $currency).').');
        }

        return $prepared;
    }

    public function event(Payment $payment, ?string $from, string $to, ?string $note, User $user): void
    {
        PaymentEvent::create(['payment_id' => $payment->id, 'from_status' => $from, 'to_status' => $to, 'note' => $note ? mb_substr($note, 0, 255) : null, 'user_id' => $user->id]);
    }

    public static function normalizeReference(?string $ref): ?string
    {
        if ($ref === null) {
            return null;
        }
        $n = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $ref));

        return $n === '' ? null : mb_substr($n, 0, 80);
    }
}
