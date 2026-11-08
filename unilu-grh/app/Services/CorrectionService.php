<?php

namespace App\Services;

use App\Exceptions\BusinessException;
use App\Models\CorrectionRequest;
use App\Models\Employee;
use App\Models\User;
use App\Support\Authz;

/** Demandes de correction soumises par l'agent ; seules certaines rubriques d'identité sont appliquées automatiquement à l'acceptation. */
class CorrectionService
{
    public const FIELDS = [
        'phone' => 'Téléphone', 'email' => 'Adresse électronique', 'address' => 'Adresse', 'marital_status' => 'État civil',
        'children_count' => 'Nombre d’enfants', 'emergency_contact_name' => 'Personne à contacter', 'emergency_contact_phone' => 'Téléphone de la personne à contacter',
        'birth_place' => 'Lieu de naissance', 'birth_date' => 'Date de naissance', 'last_name' => 'Nom', 'middle_name' => 'Postnom', 'first_name' => 'Prénom',
        'other' => 'Autre information (diplôme, carrière, affectation…)',
    ];

    public function __construct(private EmployeeService $employees, private NotificationService $notifier) {}

    public function submit(Employee $e, array $data, User $user): CorrectionRequest
    {
        if ($user->employee_id !== $e->id) {
            throw new BusinessException('Vous ne pouvez demander la correction que de votre propre dossier.');
        }
        if (! isset(self::FIELDS[$data['field'] ?? ''])) {
            throw new BusinessException('Rubrique inconnue.');
        }
        $field = $data['field'];
        $r = CorrectionRequest::create([
            'employee_id' => $e->id, 'field' => $field, 'current_value' => $field === 'other' ? null : (string) ($e->getAttribute($field) instanceof \DateTimeInterface ? $e->getAttribute($field)->format('Y-m-d') : $e->getAttribute($field)),
            'requested_value' => $data['requested_value'], 'justification' => $data['justification'], 'document_id' => $data['document_id'] ?? null, 'status' => 'pending',
        ]);
        AuditService::log('corrections.submit', $r, "Demande de correction ({$field}) par {$e->matricule}");
        $this->notifier->toHolders('corrections.handle', 'correction', "Demande de correction de {$e->fullName()} : ".self::FIELDS[$field].'.', route('corrections.index'), $e);

        return $r;
    }

    public function handle(CorrectionRequest $r, bool $accept, string $response, User $user): CorrectionRequest
    {
        Authz::need($user, 'corrections.handle', $r->employee);
        if ($r->status !== 'pending') {
            throw new BusinessException('Demande déjà traitée.');
        }
        if ($accept && $r->field !== 'other') {
            $this->employees->update($r->employee, [$r->field => $r->requested_value], $user, 'Demande de correction #'.$r->id);
        }
        $r->update(['status' => $accept ? 'accepted' : 'rejected', 'response' => $response, 'handled_by' => $user->id, 'handled_at' => now()]);
        AuditService::log('corrections.handle', $r, ($accept ? 'Acceptation' : 'Rejet').' de la demande #'.$r->id, null, null, $response);
        $this->notifier->toEmployee($r->employee, 'correction', 'Votre demande de correction ('.self::FIELDS[$r->field].') a été '.($accept ? 'acceptée' : 'rejetée').' : '.$response, route('me.corrections'));

        return $r;
    }
}
