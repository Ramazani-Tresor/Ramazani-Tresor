<?php

namespace App\Services;

use App\Exceptions\BusinessException;
use App\Models\Candidate;
use App\Models\CareerEvent;
use App\Models\Employee;
use App\Models\User;
use App\Support\Authz;
use App\Support\Labels;
use Illuminate\Support\Facades\DB;

/** Recrutement : candidature → examen → validation → engagement (matricule, classification, affectation initiale, fichier général). */
class RecruitmentService
{
    public const TRANSITIONS = [
        'submitted' => ['under_review', 'rejected'],
        'under_review' => ['validated', 'rejected'],
        'validated' => ['rejected'],
    ];

    public function __construct(private NumberingService $numbers, private EmployeeService $employees, private AssignmentService $assignments) {}

    public function create(array $data, User $user): Candidate
    {
        Authz::need($user, 'recruitment.manage');
        $c = Candidate::create(array_intersect_key($data, array_flip([
            'last_name', 'middle_name', 'first_name', 'sex', 'birth_date', 'birth_place', 'nationality', 'phone', 'email', 'address',
            'staff_family', 'staff_category_id', 'grade_id', 'org_unit_id', 'position_id', 'job_function_id', 'highest_degree', 'notes',
        ])) + ['reference' => $this->numbers->next('candidate'), 'status' => 'submitted', 'created_by' => $user->id]);
        AuditService::log('recruitment.create', $c, "Candidature {$c->reference} — {$c->fullName()}");

        return $c;
    }

    public function transition(Candidate $c, string $to, User $user, ?string $reason = null): Candidate
    {
        Authz::need($user, 'recruitment.manage');
        if (! in_array($to, self::TRANSITIONS[$c->status] ?? [], true)) {
            throw new BusinessException('Changement de statut non autorisé depuis « '.Labels::status('candidate', $c->status).' ».');
        }
        if ($to === 'validated') {
            Authz::differentFrom($user, $c->created_by, 'une candidature');
        }
        $c->update(['status' => $to, 'reviewed_by' => $user->id, 'rejection_reason' => $to === 'rejected' ? $reason : $c->rejection_reason]);
        AuditService::log('recruitment.status', $c, "Candidature {$c->reference} : {$to}", null, null, $reason);

        return $c;
    }

    public function engage(Candidate $c, array $data, User $user): Employee
    {
        Authz::need($user, 'recruitment.engage');
        if ($c->status !== 'validated') {
            throw new BusinessException('Seule une candidature validée peut donner lieu à un engagement.');
        }
        foreach (['decision_number' => 'le numéro de la décision d’engagement', 'decision_date' => 'la date de décision', 'engagement_date' => 'la date d’engagement', 'org_unit_id' => 'l’affectation initiale'] as $f => $label) {
            if (empty($data[$f])) {
                throw new BusinessException("Indiquez {$label}.");
            }
        }

        return DB::transaction(function () use ($c, $data, $user) {
            $c = Candidate::query()->lockForUpdate()->findOrFail($c->id);
            if ($c->employee_id) {
                throw new BusinessException('Ce candidat est déjà engagé.');
            }
            $e = $this->employees->create([
                'matricule' => $data['matricule'] ?? null,
                'last_name' => $c->last_name, 'middle_name' => $c->middle_name, 'first_name' => $c->first_name, 'sex' => $c->sex,
                'birth_date' => $c->birth_date, 'birth_place' => $c->birth_place, 'nationality' => $c->nationality,
                'phone' => $c->phone, 'email' => $c->email, 'address' => $c->address,
                'staff_family' => $data['staff_family'] ?? $c->staff_family,
                'staff_category_id' => $data['staff_category_id'] ?? $c->staff_category_id,
                'grade_id' => $data['grade_id'] ?? $c->grade_id, 'job_function_id' => $data['job_function_id'] ?? $c->job_function_id,
                'first_service_date' => $data['engagement_date'], 'engagement_date' => $data['engagement_date'],
            ], $user, 'Engagement '.$data['decision_number']);
            CareerEvent::create([
                'employee_id' => $e->id, 'type' => 'engagement', 'new_grade_id' => $e->grade_id, 'new_category_id' => $e->staff_category_id,
                'effective_date' => $data['engagement_date'], 'decision_date' => $data['decision_date'], 'decision_number' => $data['decision_number'],
                'signing_authority' => $data['signing_authority'] ?? 'Autorité compétente', 'observations' => 'Engagement à partir de la candidature '.$c->reference,
                'status' => 'validated', 'created_by' => $c->created_by, 'validated_by' => $user->id, 'validated_at' => now(),
            ]);
            $this->assignments->assign($e, [
                'org_unit_id' => $data['org_unit_id'], 'position_id' => $data['position_id'] ?? $c->position_id,
                'job_function_id' => $data['job_function_id'] ?? $c->job_function_id, 'start_date' => $data['engagement_date'],
                'reason' => 'Affectation initiale (engagement)', 'decision_number' => $data['decision_number'],
            ], $user, false, false);
            $c->update(['status' => 'engaged', 'employee_id' => $e->id, 'decision_number' => $data['decision_number'], 'decision_date' => $data['decision_date'], 'engagement_date' => $data['engagement_date'], 'engaged_by' => $user->id]);
            AuditService::log('recruitment.engage', $c, "Engagement de {$c->fullName()} : matricule {$e->matricule}");

            return $e;
        });
    }
}
