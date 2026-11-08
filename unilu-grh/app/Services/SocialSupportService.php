<?php

namespace App\Services;

use App\Exceptions\BusinessException;
use App\Models\Employee;
use App\Models\KinshipDegree;
use App\Models\SocialSupport;
use App\Models\SupportType;
use App\Models\User;
use App\Support\Authz;
use App\Support\Money;

/** Soutiens sociaux : demande → approbation (autre personne) → paiement. Montants issus du paramétrage. */
class SocialSupportService
{
    public function __construct(private NumberingService $numbers, private NotificationService $notifier) {}

    public function request(Employee $e, array $data, User $user): SocialSupport
    {
        Authz::need($user, 'social.manage', $e);
        $type = SupportType::query()->where('is_active', true)->findOrFail($data['support_type_id'] ?? 0);
        $kin = null;
        if ($type->uses_kinship) {
            $kin = KinshipDegree::query()->where('is_active', true)->find($data['kinship_degree_id'] ?? 0);
            if (! $kin) {
                throw new BusinessException('Soutien au deuil : indiquez le lien de parenté avec la personne décédée.');
            }
            if (empty($data['deceased_name']) || empty($data['event_date'])) {
                throw new BusinessException('Indiquez le nom de la personne décédée et la date du décès.');
            }
        }
        $amount = ($data['amount_requested'] ?? '') !== '' ? $data['amount_requested'] : ($kin?->support_amount ?? $type->default_amount);
        $s = SocialSupport::create([
            'number' => $this->numbers->next('social_support'), 'employee_id' => $e->id, 'support_type_id' => $type->id,
            'kinship_degree_id' => $kin?->id, 'deceased_name' => $data['deceased_name'] ?? null, 'event_date' => $data['event_date'] ?? null,
            'description' => $data['description'] ?? null, 'amount_requested' => $amount !== null ? Money::money($amount) : null,
            'currency' => $kin?->currency ?? $type->currency, 'beneficiary' => $data['beneficiary'] ?? null,
            'status' => 'requested', 'created_by' => $user->id,
        ]);
        AuditService::log('social.request', $s, "Demande {$s->number} ({$type->name}) pour {$e->matricule}", null, $s->toArray());

        return $s;
    }

    public function approve(SocialSupport $s, ?string $amount, ?string $decisionRef, User $user): SocialSupport
    {
        Authz::need($user, 'social.approve', $s->employee);
        Authz::differentFrom($user, $s->created_by, 'une demande de soutien');
        if ($s->status !== 'requested') {
            throw new BusinessException('Cette demande a déjà été traitée.');
        }
        $granted = ($amount ?? '') !== '' ? $amount : $s->amount_requested;
        if ($granted === null || ! Money::isPositive($granted)) {
            throw new BusinessException('Indiquez le montant accordé.');
        }
        if ($s->type->max_amount && Money::cmp($granted, $s->type->max_amount) > 0) {
            throw new BusinessException('Montant supérieur au plafond paramétré ('.Money::format($s->type->max_amount, $s->currency).').');
        }
        if (empty($decisionRef)) {
            throw new BusinessException('Indiquez la référence de la décision d’attribution.');
        }
        $s->update(['status' => 'approved', 'amount_granted' => Money::money($granted), 'decision_reference' => $decisionRef, 'approved_by' => $user->id, 'approved_at' => now()]);
        AuditService::log('social.approve', $s, "Approbation {$s->number} : ".Money::format($granted, $s->currency));
        if ($s->employee->status !== 'deceased') {
            $this->notifier->toEmployee($s->employee, 'support_granted', "Un soutien « {$s->type->name} » de ".Money::format($granted, $s->currency).' vous a été accordé (décision '.$decisionRef.').', route('me.finances'));
        }

        return $s;
    }

    public function reject(SocialSupport $s, string $reason, User $user): SocialSupport
    {
        Authz::need($user, 'social.approve', $s->employee);
        if ($s->status !== 'requested') {
            throw new BusinessException('Cette demande a déjà été traitée.');
        }
        $s->update(['status' => 'rejected', 'rejection_reason' => $reason, 'approved_by' => $user->id, 'approved_at' => now()]);
        AuditService::log('social.reject', $s, 'Rejet', null, null, $reason);

        return $s;
    }

    public function pay(SocialSupport $s, string $date, ?string $reference, User $user): SocialSupport
    {
        Authz::need($user, 'social.pay');
        Authz::differentFrom($user, $s->approved_by, 'le paiement d’un soutien que vous avez approuvé');
        if ($s->status !== 'approved') {
            throw new BusinessException('Seul un soutien approuvé peut être payé.');
        }
        $s->update(['status' => 'paid', 'paid_on' => $date, 'payment_reference' => $reference, 'paid_by' => $user->id]);
        AuditService::log('social.pay', $s, "Paiement {$s->number}");

        return $s;
    }
}
