<?php

namespace App\Services;

use App\Exceptions\BusinessException;
use App\Models\Employee;
use App\Models\Mutation;
use App\Models\User;
use App\Support\Authz;
use App\Support\Settings;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Mutations. Interne : nouvelle affectation, droits aux primes maintenus.
 * Externe : clôture de l'affectation, statut « Muté vers une autre institution », fin des droits ordinaires à la date d'effet,
 * recalcul des primes (ajustements), instantané de la situation financière ; le dossier est conservé intégralement.
 */
class MutationService
{
    public function __construct(
        private AssignmentService $assignments,
        private EmployeeService $employees,
        private PrimeService $primes,
        private NotificationService $notifier,
    ) {}

    public function propose(Employee $e, array $data, User $user): Mutation
    {
        Authz::need($user, 'mutations.manage', $e);
        if (empty($data['effective_date'])) {
            throw new BusinessException('Toute mutation doit avoir une date d’effet.');
        }
        foreach (['kind' => 'la nature', 'reason' => 'le motif', 'decision_date' => 'la date de décision', 'decision_reference' => 'la référence de la décision'] as $f => $label) {
            if (empty($data[$f])) {
                throw new BusinessException("Indiquez {$label} de la mutation.");
            }
        }
        if ($e->isEnded()) {
            throw new BusinessException('Cet agent n’est plus en fonction à l’université.');
        }
        if ($data['kind'] === 'internal' && empty($data['to_unit_id'])) {
            throw new BusinessException('Mutation interne : indiquez la structure d’accueil.');
        }
        if ($data['kind'] === 'internal' && (int) $data['to_unit_id'] === (int) $e->currentUnitId()) {
            throw new BusinessException('La structure d’accueil est la structure actuelle de l’agent.');
        }
        if ($data['kind'] === 'external' && empty($data['destination_institution'])) {
            throw new BusinessException('Mutation externe : indiquez l’institution d’accueil.');
        }
        $m = Mutation::create([
            'employee_id' => $e->id, 'kind' => $data['kind'], 'from_unit_id' => $e->currentUnitId(),
            'to_unit_id' => $data['kind'] === 'internal' ? $data['to_unit_id'] : null,
            'to_position_id' => $data['kind'] === 'internal' ? ($data['to_position_id'] ?? null) : null,
            'origin_institution' => $data['origin_institution'] ?? (Settings::get('university_name')),
            'destination_institution' => $data['kind'] === 'external' ? $data['destination_institution'] : Settings::get('university_name'),
            'reason' => $data['reason'], 'decision_date' => $data['decision_date'], 'effective_date' => $data['effective_date'],
            'decision_reference' => $data['decision_reference'], 'document_id' => $data['document_id'] ?? null,
            'status' => 'draft', 'created_by' => $user->id,
        ]);
        AuditService::log('mutations.propose', $m, "Proposition de mutation {$m->kind} pour {$e->matricule}", null, $m->toArray());

        return $m;
    }

    public function validate(Mutation $m, User $user): Mutation
    {
        Authz::need($user, 'mutations.validate', $m->employee);
        Authz::differentFrom($user, $m->created_by, 'une mutation');
        if ($m->status !== 'draft') {
            throw new BusinessException('Cette mutation a déjà été traitée.');
        }
        if (Settings::bool('require_decision_document') && ! $m->document_id) {
            throw new BusinessException('La décision numérisée est obligatoire pour valider une mutation.');
        }

        return DB::transaction(function () use ($m, $user) {
            $e = Employee::query()->lockForUpdate()->findOrFail($m->employee_id);
            $date = Carbon::parse($m->effective_date);
            if ($m->kind === 'internal') {
                $this->assignments->assign($e, [
                    'org_unit_id' => $m->to_unit_id, 'position_id' => $m->to_position_id, 'start_date' => $date->toDateString(),
                    'reason' => 'Mutation interne : '.$m->reason, 'decision_number' => $m->decision_reference, 'document_id' => $m->document_id,
                ], $user, false, false);
                $this->notifier->toEmployee($e, 'mutation', 'Mutation interne vers « '.$m->toUnit?->name.' » à compter du '.$date->format('d/m/Y').' (décision '.$m->decision_reference.').', route('me.show'));
            } else {
                $this->assignments->closeAllActive($e, $date->copy()->subDay(), 'Mutation externe vers '.$m->destination_institution);
                $this->employees->setStatus($e, 'transferred_out', $date->toDateString(), 'Mutation externe '.$m->decision_reference, $date->toDateString());
                $adjusted = $this->primes->recalculate($e, $date, 'mutation', $m, $user);
                $m->financial_snapshot = $this->primes->summary($e->fresh(), $date) + ['_ajustements' => $adjusted];
                $this->notifier->toEmployee($e, 'mutation', 'Mutation externe vers « '.$m->destination_institution.' » avec effet au '.$date->format('d/m/Y').'. Votre dossier reste conservé ; les sommes restant dues seront traitées.', route('me.finances'));
            }
            $m->status = 'validated';
            $m->validated_by = $user->id;
            $m->validated_at = now();
            $m->save();
            AuditService::log('mutations.validate', $m, "Validation de la mutation {$m->kind} de {$e->matricule}");

            return $m;
        });
    }

    public function reject(Mutation $m, string $reason, User $user): Mutation
    {
        Authz::need($user, 'mutations.validate', $m->employee);
        if ($m->status !== 'draft') {
            throw new BusinessException('Cette mutation a déjà été traitée.');
        }
        $m->update(['status' => 'rejected', 'rejection_reason' => $reason, 'validated_by' => $user->id, 'validated_at' => now()]);
        AuditService::log('mutations.reject', $m, 'Rejet de la mutation', null, null, $reason);

        return $m;
    }
}
