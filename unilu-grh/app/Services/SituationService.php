<?php

namespace App\Services;

use App\Exceptions\BusinessException;
use App\Models\AdministrativeSituation;
use App\Models\Employee;
use App\Models\SituationType;
use App\Models\User;
use App\Support\Authz;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Congés et situations administratives. Chaque type porte ses conséquences paramétrables :
 * statut de l'agent, % de primes dû, clôture de l'affectation, fin des droits (retraite, démission…).
 */
class SituationService
{
    public function __construct(
        private EmployeeService $employees,
        private AssignmentService $assignments,
        private PrimeService $primes,
        private NotificationService $notifier,
    ) {}

    public function request(Employee $e, array $data, User $user): AdministrativeSituation
    {
        Authz::need($user, 'situations.manage', $e);
        $type = SituationType::query()->where('is_active', true)->findOrFail($data['situation_type_id'] ?? 0);
        if (empty($data['start_date']) || empty($data['reason'])) {
            throw new BusinessException('Indiquez la date de début et le motif (justification).');
        }
        $start = Carbon::parse($data['start_date']);
        $end = ! empty($data['planned_end_date']) ? Carbon::parse($data['planned_end_date']) : null;
        if ($type->requires_end_date && ! $end) {
            throw new BusinessException("« {$type->name} » doit être enregistré(e) avec une durée : indiquez la date de fin prévue.");
        }
        if ($end && $end->lt($start)) {
            throw new BusinessException('La date de fin précède la date de début.');
        }
        if ($end && $type->max_days && $start->diffInDays($end) + 1 > $type->max_days) {
            throw new BusinessException("Durée maximale dépassée pour « {$type->name} » ({$type->max_days} jours).");
        }
        if ($e->isEnded()) {
            throw new BusinessException('Agent sans droits ordinaires : aucune nouvelle situation ne peut être enregistrée.');
        }
        $overlap = AdministrativeSituation::query()->where('employee_id', $e->id)->whereIn('status', ['requested', 'approved'])
            ->whereDate('start_date', '<=', $end ?? '9999-12-31')
            ->where(fn ($q) => $q->whereNull('planned_end_date')->orWhereDate('planned_end_date', '>=', $start))
            ->when(! empty($data['extends_id']), fn ($q) => $q->where('id', '!=', $data['extends_id']))
            ->exists();
        if ($overlap) {
            throw new BusinessException('Une autre situation demandée ou en cours couvre déjà cette période.');
        }
        $s = AdministrativeSituation::create([
            'employee_id' => $e->id, 'situation_type_id' => $type->id, 'extends_id' => $data['extends_id'] ?? null,
            'start_date' => $start->toDateString(), 'planned_end_date' => $end?->toDateString(), 'reason' => $data['reason'],
            'decision_reference' => $data['decision_reference'] ?? null, 'authority' => $data['authority'] ?? null,
            'document_id' => $data['document_id'] ?? null, 'status' => 'requested', 'created_by' => $user->id,
        ]);
        AuditService::log('situations.request', $s, "{$type->name} demandé(e) pour {$e->matricule}", null, $s->toArray());
        if (! $type->requires_approval && $user->hasPermission('situations.validate')) {
            return $this->approve($s, $user, true);
        }

        return $s;
    }

    public function approve(AdministrativeSituation $s, User $user, bool $auto = false): AdministrativeSituation
    {
        Authz::need($user, 'situations.validate', $s->employee);
        if (! $auto) {
            Authz::differentFrom($user, $s->created_by, 'une situation administrative');
        }
        if ($s->status !== 'requested') {
            throw new BusinessException('Cette demande a déjà été traitée.');
        }

        return DB::transaction(function () use ($s, $user) {
            $e = Employee::query()->lockForUpdate()->findOrFail($s->employee_id);
            $type = $s->type;
            $s->previous_status = $e->status;
            $s->status = 'approved';
            $s->approved_by = $user->id;
            $s->approved_at = now();
            $s->save();
            $rightsEnd = $type->ends_employment ? $s->start_date->toDateString() : null;
            if ($s->start_date->lte(now()) || $type->ends_employment) {
                $this->employees->setStatus($e, $type->resulting_status, $s->start_date->toDateString(), $type->name.' '.($s->decision_reference ?? ''), $rightsEnd);
            }
            if ($type->closes_assignment || $type->ends_employment) {
                $this->assignments->closeAllActive($e, $s->start_date->copy()->subDay(), $type->name);
            }
            // Conséquences financières (situation éventuellement rétroactive) : recalcul des droits déjà générés.
            $this->primes->recalculate($e, $s->start_date, 'situation', $s, $user);
            AuditService::log('situations.approve', $s, "Approbation : {$type->name} de {$e->matricule}");
            $event = match ($type->kind) {
                'leave' => 'leave_approved', 'availability' => 'availability', 'suspension' => 'suspension', 'reinstatement' => 'reinstatement', default => 'leave_approved',
            };
            $period = 'à compter du '.$s->start_date->format('d/m/Y').($s->planned_end_date ? ' jusqu’au '.$s->planned_end_date->format('d/m/Y') : '');
            $this->notifier->toEmployee($e, $event, "{$type->name} approuvé(e) {$period}.", route('me.situations'));

            return $s;
        });
    }

    public function reject(AdministrativeSituation $s, string $reason, User $user): AdministrativeSituation
    {
        Authz::need($user, 'situations.validate', $s->employee);
        if ($s->status !== 'requested') {
            throw new BusinessException('Cette demande a déjà été traitée.');
        }
        $s->update(['status' => 'rejected', 'rejection_reason' => $reason, 'approved_by' => $user->id, 'approved_at' => now()]);
        AuditService::log('situations.reject', $s, 'Rejet', null, null, $reason);

        return $s;
    }

    /** Clôture (fin anticipée ou au terme) avec réintégration de l'agent en activité. */
    public function close(AdministrativeSituation $s, Carbon $endDate, User $user, bool $reinstate = true, ?string $note = null): AdministrativeSituation
    {
        Authz::need($user, 'situations.validate', $s->employee);
        if ($s->status !== 'approved') {
            throw new BusinessException('Seule une situation en cours peut être clôturée.');
        }
        if ($endDate->lt($s->start_date)) {
            throw new BusinessException('La date de clôture précède le début de la situation.');
        }
        if ($s->type->ends_employment) {
            throw new BusinessException('Une fin de fonctions (retraite, démission…) ne se clôture pas : elle est définitive.');
        }

        return DB::transaction(function () use ($s, $endDate, $user, $reinstate, $note) {
            $s->update(['status' => 'closed', 'actual_end_date' => $endDate->toDateString(), 'closed_by' => $user->id]);
            $e = Employee::query()->lockForUpdate()->findOrFail($s->employee_id);
            $stillOpen = AdministrativeSituation::query()->where('employee_id', $e->id)->where('status', 'approved')->where('id', '!=', $s->id)->exists();
            if ($reinstate && ! $stillOpen && ! $e->isEnded()) {
                $this->employees->setStatus($e, 'active', $endDate->copy()->addDay()->toDateString(), 'Réintégration après '.$s->type->name);
                $this->notifier->toEmployee($e, 'reinstatement', 'Réintégration en activité à compter du '.$endDate->copy()->addDay()->format('d/m/Y').'.', route('me.situations'));
            }
            $this->primes->recalculate($e, $s->start_date, 'situation', $s, $user);
            AuditService::log('situations.close', $s, 'Clôture au '.$endDate->format('d/m/Y'), null, null, $note);

            return $s;
        });
    }

    /** Prolongation : nouvelle demande liée, débutant le lendemain de la fin prévue, soumise à approbation. */
    public function extend(AdministrativeSituation $s, Carbon $newEnd, array $data, User $user): AdministrativeSituation
    {
        if ($s->status !== 'approved' || ! $s->planned_end_date) {
            throw new BusinessException('Seule une situation en cours avec une date de fin prévue peut être prolongée.');
        }
        if ($newEnd->lte($s->planned_end_date)) {
            throw new BusinessException('La nouvelle échéance doit être postérieure à la fin prévue actuelle.');
        }

        return $this->request($s->employee, [
            'situation_type_id' => $s->situation_type_id, 'extends_id' => $s->id,
            'start_date' => $s->planned_end_date->copy()->addDay()->toDateString(), 'planned_end_date' => $newEnd->toDateString(),
            'reason' => 'Prolongation : '.($data['reason'] ?? $s->reason), 'decision_reference' => $data['decision_reference'] ?? null,
            'authority' => $data['authority'] ?? null, 'document_id' => $data['document_id'] ?? null,
        ], $user);
    }

    /** Applique les situations approuvées dont la date de début est atteinte (commande quotidienne). */
    public function applyDueStarts(): int
    {
        $n = 0;
        foreach (AdministrativeSituation::query()->with(['type', 'employee'])->where('status', 'approved')->whereDate('start_date', '<=', now())->get() as $s) {
            $e = $s->employee;
            $end = $s->actual_end_date ?? $s->planned_end_date;
            if (($end === null || $end->gte(now()->startOfDay())) && $e->status !== $s->type->resulting_status && ! $e->isEnded()) {
                $this->employees->setStatus($e, $s->type->resulting_status, $s->start_date->toDateString(), $s->type->name.' (entrée en vigueur)');
                $n++;
            }
        }

        return $n;
    }
}
