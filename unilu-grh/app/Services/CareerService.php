<?php

namespace App\Services;

use App\Exceptions\BusinessException;
use App\Models\CareerEvent;
use App\Models\Employee;
use App\Models\Grade;
use App\Models\Mandate;
use App\Models\User;
use App\Support\Authz;
use App\Support\Labels;
use App\Support\Settings;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Carrière : aucune promotion automatique. Une évolution est préparée (brouillon) puis validée par une autre personne,
 * sur la base d'une décision formelle (numéro, date, autorité signataire). La validation met à jour le dossier.
 */
class CareerService
{
    public function __construct(private NotificationService $notifier) {}

    public function propose(Employee $e, array $data, User $user): CareerEvent
    {
        Authz::need($user, 'career.manage', $e);
        foreach (['type' => 'le type d’évolution', 'effective_date' => 'la date de prise d’effet', 'decision_date' => 'la date de décision', 'decision_number' => 'le numéro de décision', 'signing_authority' => 'l’autorité signataire'] as $f => $label) {
            if (empty($data[$f])) {
                throw new BusinessException("Toute évolution de carrière doit être justifiée par une décision officielle : indiquez {$label}.");
            }
        }
        if ($e->isEnded()) {
            throw new BusinessException('Agent sans droits ordinaires : aucune évolution de carrière ne peut être enregistrée.');
        }
        if (in_array($data['type'], ['promotion', 'nomination', 'reclassification'], true) && empty($data['new_grade_id']) && empty($data['new_category_id'])) {
            throw new BusinessException('Indiquez le nouveau grade ou la nouvelle catégorie.');
        }
        if ($data['type'] === 'mandate_renewal' && empty($data['new_mandate_id'])) {
            throw new BusinessException('Indiquez le nouveau mandat.');
        }
        if (! empty($data['new_grade_id']) && (int) $data['new_grade_id'] === (int) $e->grade_id && empty($data['new_mandate_id'])) {
            throw new BusinessException('Le nouveau grade est identique au grade actuel.');
        }
        if (! empty($data['new_grade_id'])) {
            $g = Grade::query()->findOrFail($data['new_grade_id']);
            if ($g->family !== $e->staff_family && empty($data['allow_family_change'])) {
                throw new BusinessException("Le grade « {$g->name} » relève d’une autre famille de personnel : confirmez explicitement le changement de famille.");
            }
        }
        $ev = CareerEvent::create([
            'employee_id' => $e->id, 'type' => $data['type'],
            'old_grade_id' => $e->grade_id, 'new_grade_id' => $data['new_grade_id'] ?? null,
            'old_category_id' => $e->staff_category_id, 'new_category_id' => $data['new_category_id'] ?? null,
            'old_mandate_id' => $e->mandate_id, 'new_mandate_id' => $data['new_mandate_id'] ?? null,
            'new_mandate_end' => $data['new_mandate_end'] ?? null,
            'effective_date' => $data['effective_date'], 'decision_date' => $data['decision_date'],
            'decision_number' => $data['decision_number'], 'signing_authority' => $data['signing_authority'],
            'document_id' => $data['document_id'] ?? null, 'observations' => $data['observations'] ?? null,
            'status' => 'draft', 'created_by' => $user->id,
        ]);
        AuditService::log('career.propose', $ev, "Proposition {$ev->type} pour {$e->matricule} (décision {$ev->decision_number})", null, $ev->toArray());

        return $ev;
    }

    public function validate(CareerEvent $ev, User $user): CareerEvent
    {
        Authz::need($user, 'career.validate', $ev->employee);
        Authz::differentFrom($user, $ev->created_by, 'une décision de carrière');
        if ($ev->status !== 'draft') {
            throw new BusinessException('Cette décision a déjà été traitée.');
        }
        if (Settings::bool('require_decision_document') && ! $ev->document_id) {
            throw new BusinessException('La pièce justificative numérisée est obligatoire pour valider une décision.');
        }

        return DB::transaction(function () use ($ev, $user) {
            $e = Employee::query()->lockForUpdate()->findOrFail($ev->employee_id);
            Employee::$changeContext = 'Décision '.$ev->decision_number;
            if ($ev->new_grade_id) {
                $grade = Grade::query()->find($ev->new_grade_id);
                $e->grade_id = $ev->new_grade_id;
                if ($grade && $grade->family !== $e->staff_family) {
                    $e->staff_family = $grade->family;
                }
                if ($grade && $grade->staff_category_id && ! $ev->new_category_id) {
                    $e->staff_category_id = $grade->staff_category_id;
                }
                if ($grade && ! $grade->has_mandates && ! $ev->new_mandate_id) {
                    $e->mandate_id = null;
                    $e->mandate_start = null;
                    $e->mandate_end = null;
                }
            }
            if ($ev->new_category_id) {
                $e->staff_category_id = $ev->new_category_id;
            }
            if ($ev->new_mandate_id) {
                $m = Mandate::query()->find($ev->new_mandate_id);
                $e->mandate_id = $ev->new_mandate_id;
                $e->mandate_start = $ev->effective_date;
                $e->mandate_end = $ev->new_mandate_end ?? ($m?->duration_months ? Carbon::parse($ev->effective_date)->addMonths($m->duration_months)->subDay() : null);
            }
            $e->save();
            Employee::$changeContext = null;
            $ev->update(['status' => 'validated', 'validated_by' => $user->id, 'validated_at' => now()]);
            AuditService::log('career.validate', $ev, "Validation {$ev->type} de {$e->matricule} (décision {$ev->decision_number})");
            $event = match ($ev->type) {
                'promotion' => 'promotion', 'nomination' => 'nomination', 'mandate_renewal', 'reclassification' => 'grade_change', default => 'grade_change',
            };
            $what = $ev->newGrade?->name ?? $ev->newCategory?->name ?? $ev->newMandate?->name ?? '';
            $this->notifier->toEmployee($e, $event, 'Décision '.$ev->decision_number.' du '.$ev->decision_date->format('d/m/Y').' : '.mb_strtolower(Labels::status('career_type', $ev->type)).($what ? " — {$what}" : '').', avec effet au '.$ev->effective_date->format('d/m/Y').'.', route('me.career'));

            return $ev;
        });
    }

    public function reject(CareerEvent $ev, string $reason, User $user): CareerEvent
    {
        Authz::need($user, 'career.validate', $ev->employee);
        if ($ev->status !== 'draft') {
            throw new BusinessException('Cette décision a déjà été traitée.');
        }
        $ev->update(['status' => 'rejected', 'rejection_reason' => $reason, 'validated_by' => $user->id, 'validated_at' => now()]);
        AuditService::log('career.reject', $ev, 'Rejet de la proposition', null, null, $reason);

        return $ev;
    }

    /** Agents dont le mandat expire dans les N jours (ou est déjà expiré). */
    public function expiringMandates(int $days)
    {
        return Employee::query()->with(['grade', 'mandate', 'activeAssignment.orgUnit'])
            ->whereNotIn('status', Employee::ENDED_STATUSES)->whereNotNull('mandate_end')
            ->whereDate('mandate_end', '<=', now()->addDays($days))->orderBy('mandate_end');
    }
}
