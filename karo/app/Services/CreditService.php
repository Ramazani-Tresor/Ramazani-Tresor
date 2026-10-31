<?php

namespace App\Services;

use App\Exceptions\BusinessException;
use App\Models\AcademicYear;
use App\Models\Charge;
use App\Models\CreditOperation;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\User;
use App\Support\Authz;
use App\Support\Money;
use App\Support\Settings;
use Illuminate\Support\Facades\DB;

/**
 * Excédents (montants validés non affectés) : compensation, remboursement, report sur l'année suivante,
 * ou conservation en crédit (aucune opération).
 *
 * Anti double usage : le paiement source est verrouillé (SELECT … FOR UPDATE) à la demande ET à
 * l'approbation ; une demande en attente réserve le montant. Disponible = montant − affectations
 * − remboursements/reports (exécutés ou en attente) − compensations en attente.
 */
class CreditService
{
    public function __construct(private NumberingService $numbering, private ConversionService $conversion) {}

    public function request(Payment $source, string $type, string $amount, string $reason, User $user, string $clientUuid, ?int $targetChargeId = null, ?int $targetYearId = null, ?string $refundMethod = null): CreditOperation
    {
        if ($existing = CreditOperation::query()->where('client_uuid', $clientUuid)->first()) {
            return $existing;
        }
        Authz::need($user, 'credits.request');
        if (! in_array($type, ['compensation', 'refund', 'carry_forward'], true)) {
            throw new BusinessException('Type d’opération invalide.');
        }
        if (! Money::isPositive($amount)) {
            throw new BusinessException('Le montant doit être positif.');
        }
        if (trim($reason) === '') {
            throw new BusinessException('Le motif est obligatoire.');
        }

        return DB::transaction(function () use ($source, $type, $amount, $reason, $user, $clientUuid, $targetChargeId, $targetYearId, $refundMethod) {
            $source = Payment::query()->lockForUpdate()->findOrFail($source->id);
            $this->assertSourceUsable($source, $type);
            if (Money::cmp($amount, $source->availableAmount()) > 0) {
                throw new BusinessException('Crédit insuffisant sur '.$source->number.' : disponible '.Money::format($source->availableAmount(), $source->currency).' (montants déjà affectés, utilisés ou réservés).');
            }
            $this->validateTarget($source, $type, $amount, $targetChargeId, $targetYearId);
            $op = CreditOperation::create([
                'number' => $this->numbering->next('credit_operation'),
                'client_uuid' => $clientUuid,
                'student_id' => $source->student_id,
                'source_payment_id' => $source->id,
                'type' => $type,
                'currency' => $source->currency,
                'amount' => Money::money($amount),
                'target_charge_id' => $type === 'compensation' ? $targetChargeId : null,
                'target_academic_year_id' => $type === 'carry_forward' ? $targetYearId : null,
                'refund_method' => $refundMethod,
                'status' => 'pending',
                'reason' => $reason,
                'requested_by' => $user->id,
            ]);
            AuditService::log('credit.request', $op, "Demande {$op->number} ({$type}) : ".Money::format($amount, $source->currency)." sur {$source->number}", null, null, $reason);

            return $op;
        });
    }

    public function approve(CreditOperation $op, User $approver, ?string $note = null, ?string $refundReference = null): CreditOperation
    {
        return DB::transaction(function () use ($op, $approver, $note, $refundReference) {
            Authz::need($approver, 'credits.approve');
            $op = CreditOperation::query()->lockForUpdate()->findOrFail($op->id);
            $source = Payment::query()->lockForUpdate()->findOrFail($op->source_payment_id);
            if ($op->status !== 'pending') {
                throw new BusinessException('Cette opération a déjà été traitée : le crédit ne peut pas être utilisé deux fois.');
            }
            if (Settings::bool('segregation_of_duties') && $op->requested_by === $approver->id) {
                throw new BusinessException('Séparation des tâches : l’opération doit être approuvée par une autre personne que le demandeur.');
            }
            $this->assertSourceUsable($source, $op->type);
            // Le montant de cette demande est déjà réservé : il doit rester couvert.
            if (Money::isNegative($source->availableAmount())) {
                throw new BusinessException('Le crédit disponible ne couvre plus cette opération.');
            }
            $this->validateTarget($source, $op->type, (string) $op->amount, $op->target_charge_id, $op->target_academic_year_id, true);

            if ($op->type === 'compensation') {
                $charge = Charge::query()->lockForUpdate()->findOrFail($op->target_charge_id);
                [$chargeAmount, $rate] = $this->conversion->convert((string) $op->amount, $source->currency, $charge->currency);
                if (Money::cmp($chargeAmount, $charge->balance()) > 0) {
                    throw new BusinessException('La compensation dépasse le solde de la créance cible.');
                }
                PaymentAllocation::create([
                    'payment_id' => $source->id, 'charge_id' => $charge->id, 'kind' => 'compensation', 'amount' => $op->amount,
                    'charge_amount' => $chargeAmount, 'exchange_rate_id' => $rate?->id, 'status' => 'active',
                    'credit_operation_id' => $op->id, 'created_by' => $approver->id,
                ]);
            } elseif ($op->type === 'carry_forward') {
                $target = YearGuard::assertWritable($op->target_academic_year_id);
                Payment::create([
                    'number' => $this->numbering->next('payment'),
                    'client_uuid' => self::deterministicUuid('carry-op-'.$op->id),
                    'type' => 'carry_forward',
                    'student_id' => $source->student_id,
                    'academic_year_id' => $target->id,
                    'amount' => $op->amount,
                    'currency' => $source->currency,
                    'bank_reference' => 'REPORT '.$source->number,
                    'deposit_date' => $source->deposit_date,
                    'status' => 'validated',
                    'bank_status' => 'not_applicable',
                    'origin_payment_id' => $source->id,
                    'origin_credit_operation_id' => $op->id,
                    'channel' => 'system',
                    'created_by' => $approver->id,
                    'validated_by' => $approver->id,
                    'validated_at' => now(),
                ]);
            }
            $op->update(['status' => 'executed', 'approved_by' => $approver->id, 'decided_at' => now(), 'decision_note' => $note, 'refund_reference' => $refundReference ?? $op->refund_reference]);
            AuditService::log('credit.execute', $op, "Exécution {$op->number} ({$op->type})", null, null, $note);

            return $op;
        });
    }

    public function reject(CreditOperation $op, User $approver, string $reason): CreditOperation
    {
        Authz::need($approver, 'credits.approve');

        return DB::transaction(function () use ($op, $approver, $reason) {
            $op = CreditOperation::query()->lockForUpdate()->findOrFail($op->id);
            if ($op->status !== 'pending') {
                throw new BusinessException('Cette opération a déjà été traitée.');
            }
            $op->update(['status' => 'rejected', 'approved_by' => $approver->id, 'decided_at' => now(), 'decision_note' => $reason]);
            AuditService::log('credit.reject', $op, "Rejet {$op->number}", null, null, $reason);

            return $op;
        });
    }

    private function assertSourceUsable(Payment $source, string $type): void
    {
        if ($source->status !== 'validated') {
            throw new BusinessException('Seul un paiement validé peut constituer un crédit utilisable.');
        }
        if ($type !== 'carry_forward') {
            YearGuard::assertWritable($source->academic_year_id);
        }
    }

    private function validateTarget(Payment $source, string $type, string $amount, ?int $targetChargeId, ?int $targetYearId, bool $approval = false): void
    {
        if ($type === 'compensation') {
            $charge = Charge::query()->find($targetChargeId);
            if (! $charge || $charge->student_id !== $source->student_id || $charge->status !== 'active') {
                throw new BusinessException('Créance cible introuvable pour cet étudiant.');
            }
            YearGuard::assertWritable($charge->academic_year_id);
            [$converted] = $this->conversion->convert($amount, $source->currency, $charge->currency);
            if (! $approval && Money::cmp($converted, $charge->balance()) > 0) {
                throw new BusinessException('La compensation dépasse le solde de la créance cible ('.Money::format($charge->balance(), $charge->currency).').');
            }
        }
        if ($type === 'carry_forward') {
            $year = AcademicYear::query()->find($targetYearId);
            $sourceYear = $source->academicYear;
            if (! $year || $year->previous_year_id !== $sourceYear->id) {
                throw new BusinessException('Le report se fait uniquement vers l’année académique suivante.');
            }
            YearGuard::assertWritable($year);
        }
    }

    /** UUID déterministe (format v4-like) : un même report ne peut être créé qu'une fois. */
    public static function deterministicUuid(string $seed): string
    {
        $h = md5('karo:'.$seed);

        return substr($h, 0, 8).'-'.substr($h, 8, 4).'-4'.substr($h, 13, 3).'-a'.substr($h, 17, 3).'-'.substr($h, 20, 12);
    }
}
