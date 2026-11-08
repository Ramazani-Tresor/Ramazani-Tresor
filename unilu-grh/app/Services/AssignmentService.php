<?php

namespace App\Services;

use App\Exceptions\BusinessException;
use App\Models\Assignment;
use App\Models\Employee;
use App\Models\OrgUnit;
use App\Models\Position;
use App\Models\User;
use App\Support\Authz;
use App\Support\Settings;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/** Affectations : historique complet ; une seule affectation principale active à la fois, sauf paramètre contraire (règle 2). */
class AssignmentService
{
    public function __construct(private NotificationService $notifier) {}

    public function assign(Employee $e, array $data, User $user, bool $checkPermission = true, bool $notify = true): Assignment
    {
        if ($checkPermission) {
            Authz::need($user, 'assignments.manage', $e);
        }
        if ($e->isEnded()) {
            throw new BusinessException('Agent sans droits ordinaires (décédé, muté ou parti) : aucune nouvelle affectation possible.');
        }
        $unit = OrgUnit::query()->where('is_active', true)->find($data['org_unit_id'] ?? 0);
        if (! $unit) {
            throw new BusinessException('Structure d’accueil inconnue ou inactive.');
        }
        $start = Carbon::parse($data['start_date'] ?? now());
        $isPrimary = (bool) ($data['is_primary'] ?? true);

        return DB::transaction(function () use ($e, $data, $user, $unit, $start, $isPrimary, $notify) {
            $position = null;
            if (! empty($data['position_id'])) {
                $position = Position::query()->lockForUpdate()->find($data['position_id']);
                if (! $position || $position->org_unit_id !== $unit->id) {
                    throw new BusinessException('Le poste choisi n’appartient pas à la structure d’accueil.');
                }
                $occupied = Assignment::query()->where('position_id', $position->id)->where('status', 'active')->where('employee_id', '!=', $e->id)->count();
                if ($occupied >= $position->seats) {
                    throw new BusinessException("Le poste « {$position->title} » est déjà entièrement pourvu ({$occupied}/{$position->seats}).");
                }
            }
            $current = Assignment::query()->where('employee_id', $e->id)->where('status', 'active')->where('is_primary', true)->lockForUpdate()->get();
            if ($isPrimary && $current->isNotEmpty() && ! Settings::bool('multiple_primary_assignments')) {
                foreach ($current as $c) {
                    if ($start->lte($c->start_date)) {
                        throw new BusinessException('La nouvelle affectation doit commencer après le début de l’affectation en cours ('.$c->start_date->format('d/m/Y').').');
                    }
                    $this->close($c, $start->copy()->subDay(), 'Remplacée par une nouvelle affectation');
                }
            }
            $a = Assignment::create([
                'employee_id' => $e->id, 'from_unit_id' => $data['from_unit_id'] ?? $current->first()?->org_unit_id,
                'org_unit_id' => $unit->id, 'position_id' => $position?->id,
                'job_function_id' => $data['job_function_id'] ?? $position?->job_function_id,
                'is_primary' => $isPrimary, 'start_date' => $start->toDateString(), 'end_date' => $data['end_date'] ?? null,
                'reason' => $data['reason'] ?? null, 'decision_number' => $data['decision_number'] ?? null, 'authority' => $data['authority'] ?? null,
                'document_id' => $data['document_id'] ?? null, 'status' => 'active', 'created_by' => $user->id,
            ]);
            if ($isPrimary && ! empty($a->job_function_id) && empty($data['keep_function'])) {
                Employee::$changeContext = 'Affectation';
                $e->update(['job_function_id' => $a->job_function_id]);
                Employee::$changeContext = null;
            }
            AuditService::log('assignments.create', $a, "Affectation de {$e->matricule} à {$unit->name}");
            if ($notify) {
                $this->notifier->toEmployee($e, 'assignment', "Vous êtes affecté(e) à « {$unit->name} »".($position ? " (poste : {$position->title})" : '').' à compter du '.$start->format('d/m/Y').'.', route('me.show'));
            }

            return $a;
        });
    }

    public function close(Assignment $a, Carbon $end, string $reason): Assignment
    {
        if ($a->status !== 'active') {
            return $a;
        }
        if ($end->lt($a->start_date)) {
            $end = $a->start_date->copy();
        }
        $a->update(['status' => 'closed', 'end_date' => $end->toDateString(), 'closure_reason' => $reason]);
        AuditService::log('assignments.close', $a, "Clôture de l’affectation #{$a->id} au ".$end->format('d/m/Y'), null, null, $reason);

        return $a;
    }

    public function closeAllActive(Employee $e, Carbon $end, string $reason): int
    {
        $n = 0;
        foreach (Assignment::query()->where('employee_id', $e->id)->where('status', 'active')->get() as $a) {
            $this->close($a, $end, $reason);
            $n++;
        }

        return $n;
    }

    public function closeByUser(Assignment $a, Carbon $end, string $reason, User $user): Assignment
    {
        Authz::need($user, 'assignments.manage', $a->employee);

        return $this->close($a, $end, $reason);
    }
}
