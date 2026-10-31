<?php

namespace App\Services;

use App\Exceptions\BusinessException;
use App\Models\Charge;
use App\Models\ChargeAdjustment;
use App\Models\User;
use App\Support\Authz;
use App\Support\Money;
use App\Support\Settings;
use Illuminate\Support\Facades\DB;

/**
 * Bourses, réductions, exonérations, prises en charge et ajustements.
 * Un ajustement n'a d'effet sur le solde qu'une fois approuvé, par une personne différente du demandeur.
 * Une diminution ne peut pas dépasser le solde restant de la créance (pas de trop-perçu implicite).
 */
class AdjustmentService
{
    public const DECREASING = ['scholarship', 'reduction', 'exemption', 'sponsorship', 'decrease'];

    public function __construct(private NumberingService $numbering) {}

    public function request(Charge $charge, string $type, string $amount, string $reason, User $user, ?string $sponsor = null, ?string $justificationPath = null): ChargeAdjustment
    {
        Authz::need($user, 'adjustments.request');
        YearGuard::assertWritable($charge->academic_year_id);
        if (! in_array($type, [...self::DECREASING, 'increase'], true)) {
            throw new BusinessException('Type d’ajustement non autorisé.');
        }
        if (! Money::isPositive($amount)) {
            throw new BusinessException('Le montant doit être positif.');
        }
        if (trim($reason) === '') {
            throw new BusinessException('Le motif est obligatoire.');
        }
        if ($charge->status !== 'active') {
            throw new BusinessException('Cette créance n’est pas active.');
        }
        if (in_array($type, ['scholarship', 'exemption', 'sponsorship'], true) && ! $justificationPath) {
            throw new BusinessException('Un justificatif est obligatoire pour une bourse, une exonération ou une prise en charge.');
        }
        $signed = $type === 'increase' ? '-'.Money::money($amount) : Money::money($amount);
        if ($type !== 'increase') {
            $this->assertWithinBalance($charge, $amount);
        }
        $adj = ChargeAdjustment::create([
            'number' => $this->numbering->next('adjustment'),
            'charge_id' => $charge->id,
            'type' => $type,
            'amount' => $signed,
            'reason' => $reason,
            'sponsor' => $sponsor,
            'justification_path' => $justificationPath,
            'status' => 'pending',
            'requested_by' => $user->id,
        ]);
        AuditService::log('adjustment.request', $adj, "Demande {$adj->number} sur {$charge->number} : ".Money::format($amount, $charge->currency), null, null, $reason);

        if (! Settings::bool('adjustment_requires_approval') && $user->hasPermission('adjustments.approve')) {
            return $this->approve($adj, $user, 'Approbation automatique (paramètre)');
        }

        return $adj;
    }

    public function approve(ChargeAdjustment $adj, User $approver, ?string $note = null): ChargeAdjustment
    {
        return DB::transaction(function () use ($adj, $approver, $note) {
            Authz::need($approver, 'adjustments.approve');
            $adj = ChargeAdjustment::query()->lockForUpdate()->findOrFail($adj->id);
            $charge = Charge::query()->lockForUpdate()->findOrFail($adj->charge_id);
            YearGuard::assertWritable($charge->academic_year_id);
            if ($adj->status !== 'pending') {
                throw new BusinessException('Cet ajustement a déjà été traité.');
            }
            if (Settings::bool('segregation_of_duties') && Settings::bool('adjustment_requires_approval') && $adj->requested_by === $approver->id) {
                throw new BusinessException('Séparation des tâches : l’ajustement doit être approuvé par une autre personne que le demandeur.');
            }
            if (Money::isPositive($adj->amount)) {
                $this->assertWithinBalance($charge, $adj->amount);
            }
            $adj->update(['status' => 'approved', 'approved_by' => $approver->id, 'approved_at' => now(), 'decision_note' => $note]);
            AuditService::log('adjustment.approve', $adj, "Approbation {$adj->number}", null, null, $note);

            return $adj;
        });
    }

    public function reject(ChargeAdjustment $adj, User $approver, string $reason): ChargeAdjustment
    {
        Authz::need($approver, 'adjustments.approve');
        if ($adj->status !== 'pending') {
            throw new BusinessException('Cet ajustement a déjà été traité.');
        }
        $adj->update(['status' => 'rejected', 'approved_by' => $approver->id, 'approved_at' => now(), 'decision_note' => $reason]);
        AuditService::log('adjustment.reject', $adj, "Rejet {$adj->number}", null, null, $reason);

        return $adj;
    }

    private function assertWithinBalance(Charge $charge, string $amount): void
    {
        if (Money::cmp($amount, $charge->balance()) > 0) {
            throw new BusinessException('La diminution ('.Money::format($amount, $charge->currency).') dépasse le solde restant de la créance ('.Money::format($charge->balance(), $charge->currency).'). Les montants déjà réglés ne peuvent être libérés que par contrepassation.');
        }
    }
}
