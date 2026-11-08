<?php

namespace App\Services;

use App\Exceptions\BusinessException;
use App\Models\CareerEvent;
use App\Models\Employee;
use App\Models\User;
use App\Support\Authz;
use Illuminate\Support\Facades\DB;

/**
 * Dossiers individuels. Après création, le grade, la catégorie et le mandat ne changent que par une décision de carrière
 * validée, et le statut uniquement par une situation administrative, une mutation ou un décès : tout reste traçable.
 */
class EmployeeService
{
    public const IDENTITY_FIELDS = [
        'last_name', 'middle_name', 'first_name', 'sex', 'birth_date', 'birth_place', 'nationality', 'address', 'phone', 'email',
        'marital_status', 'children_count', 'family_notes', 'emergency_contact_name', 'emergency_contact_phone', 'emergency_contact_relation',
        'first_service_date', 'engagement_date', 'assistant_track', 'admin_grade_id', 'job_function_id',
    ];

    public const CLASSIFICATION_FIELDS = ['staff_family', 'staff_category_id', 'grade_id', 'mandate_id', 'mandate_start', 'mandate_end'];

    public const BANK_FIELDS = ['bank_name', 'bank_account', 'bank_holder'];

    public function __construct(private NumberingService $numbers) {}

    public function nextMatricule(): string
    {
        return config('grh.matricule_prefix', 'UNILU').'-'.$this->numbers->next('matricule');
    }

    public function create(array $data, User $user, string $context = 'Création du dossier'): Employee
    {
        Authz::need($user, 'employees.manage');
        $matricule = trim((string) ($data['matricule'] ?? '')) ?: $this->nextMatricule();
        if (Employee::query()->where('matricule', $matricule)->exists()) {
            throw new BusinessException("Le matricule {$matricule} est déjà attribué : un agent possède un matricule unique.");
        }
        $fields = array_intersect_key($data, array_flip([...self::IDENTITY_FIELDS, ...self::CLASSIFICATION_FIELDS]));
        if ($user->hasPermission('employees.bank')) {
            $fields += array_intersect_key($data, array_flip(self::BANK_FIELDS));
        }

        return DB::transaction(function () use ($fields, $matricule, $user, $context) {
            Employee::$changeContext = $context;
            $e = Employee::create($fields + ['matricule' => $matricule, 'status' => 'active', 'status_since' => $fields['engagement_date'] ?? now()->toDateString(), 'created_by' => $user->id]);
            Employee::$changeContext = null;
            AuditService::log('employees.create', $e, "Création du dossier {$e->matricule} — {$e->fullName()}");

            return $e;
        });
    }

    public function update(Employee $e, array $data, User $user, string $context = 'Mise à jour du dossier'): Employee
    {
        Authz::need($user, 'employees.manage', $e);
        $fields = array_intersect_key($data, array_flip(self::IDENTITY_FIELDS));
        // Classification modifiable directement seulement tant qu'aucune décision de carrière n'est enregistrée (saisie initiale).
        if (! CareerEvent::query()->where('employee_id', $e->id)->where('status', 'validated')->exists()) {
            $fields += array_intersect_key($data, array_flip(self::CLASSIFICATION_FIELDS));
        }
        $old = $e->only(array_keys($fields));
        Employee::$changeContext = $context;
        $e->fill($fields)->save();
        Employee::$changeContext = null;
        AuditService::log('employees.update', $e, "Mise à jour du dossier {$e->matricule}", $old, $e->only(array_keys($fields)));

        return $e;
    }

    public function updateBank(Employee $e, array $data, User $user): Employee
    {
        Authz::need($user, 'employees.bank', $e);
        Employee::$changeContext = 'Coordonnées bancaires';
        $e->fill(array_intersect_key($data, array_flip(self::BANK_FIELDS)))->save();
        Employee::$changeContext = null;
        AuditService::log('employees.bank', $e, "Modification des coordonnées bancaires de {$e->matricule}");

        return $e;
    }

    public function setPhoto(Employee $e, string $path, User $user): void
    {
        Authz::need($user, 'employees.manage', $e);
        $e->update(['photo_path' => $path]);
    }

    /** Changement de statut interne aux services de décision (situations, mutation, décès). */
    public function setStatus(Employee $e, string $status, $since, string $context, ?string $rightsEnd = null): void
    {
        Employee::$changeContext = $context;
        $e->status = $status;
        $e->status_since = $since;
        if ($rightsEnd !== null) {
            $e->rights_end_date = $rightsEnd;
        }
        $e->save();
        Employee::$changeContext = null;
    }
}
