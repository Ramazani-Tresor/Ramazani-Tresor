<?php

namespace App\Services;

use App\Exceptions\BusinessException;
use App\Models\Death;
use App\Models\Employee;
use App\Models\KinshipDegree;
use App\Models\SocialSupport;
use App\Models\SupportType;
use App\Models\User;
use App\Support\Authz;
use App\Support\Settings;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Décès d'un agent. À la validation : statut « Décédé », clôture de l'affectation, arrêt des nouvelles primes à la date
 * du décès (fin des droits), recalcul des droits déjà générés, blocage des paiements ordinaires futurs, instantané
 * financier (primes acquises et arriérés dus aux ayants droit) et ouverture d'une demande de soutien social.
 * Le dossier n'est jamais effacé.
 */
class DeathService
{
    public function __construct(
        private EmployeeService $employees,
        private AssignmentService $assignments,
        private PrimeService $primes,
        private NumberingService $numbers,
        private NotificationService $notifier,
    ) {}

    public function record(Employee $e, array $data, User $user): Death
    {
        Authz::need($user, 'deaths.manage', $e);
        if (empty($data['death_date'])) {
            throw new BusinessException('Indiquez la date du décès.');
        }
        if (Carbon::parse($data['death_date'])->isFuture()) {
            throw new BusinessException('La date du décès ne peut pas être future.');
        }
        if ($e->status === 'deceased' || Death::query()->where('employee_id', $e->id)->exists()) {
            throw new BusinessException('Un décès est déjà enregistré pour cet agent.');
        }
        $d = Death::create([
            'employee_id' => $e->id, 'death_date' => $data['death_date'], 'death_place' => $data['death_place'] ?? null,
            'cause' => $user->hasPermission('deaths.cause') ? ($data['cause'] ?? null) : null,
            'document_id' => $data['document_id'] ?? null, 'contact_name' => $data['contact_name'] ?? $e->emergency_contact_name,
            'contact_phone' => $data['contact_phone'] ?? $e->emergency_contact_phone, 'contact_relation' => $data['contact_relation'] ?? $e->emergency_contact_relation,
            'status' => 'draft', 'created_by' => $user->id,
        ]);
        AuditService::log('deaths.record', $d, "Enregistrement du décès de {$e->matricule} (à valider)");

        return $d;
    }

    public function validate(Death $d, User $user): Death
    {
        Authz::need($user, 'deaths.validate', $d->employee);
        Authz::differentFrom($user, $d->created_by, 'l’enregistrement d’un décès');
        if ($d->status !== 'draft') {
            throw new BusinessException('Ce décès a déjà été validé.');
        }
        if (Settings::bool('require_decision_document') && ! $d->document_id) {
            throw new BusinessException('Le document officiel de constatation est obligatoire.');
        }

        return DB::transaction(function () use ($d, $user) {
            $e = Employee::query()->lockForUpdate()->findOrFail($d->employee_id);
            $date = Carbon::parse($d->death_date);
            $this->assignments->closeAllActive($e, $date, 'Décès');
            // Situations en cours clôturées à la date du décès
            foreach ($e->situations()->where('status', 'approved')->get() as $s) {
                $s->update(['status' => 'closed', 'actual_end_date' => $date->toDateString(), 'closed_by' => $user->id]);
            }
            $this->employees->setStatus($e, 'deceased', $date->toDateString(), 'Décès validé', $date->toDateString());
            $adjusted = $this->primes->recalculate($e, $date, 'death', $d, $user);
            $d->financial_snapshot = $this->primes->summary($e->fresh(), $date) + ['_ajustements' => $adjusted];
            $d->status = 'validated';
            $d->validated_by = $user->id;
            $d->validated_at = now();
            $d->save();
            $this->openFamilySupport($d, $e, $user);
            AuditService::log('deaths.validate', $d, "Validation du décès de {$e->matricule}");
            $this->notifier->toHolders('social.approve', 'support_granted', "Décès de {$e->fullName()} ({$e->matricule}) validé : une demande de soutien social au deuil a été ouverte.", route('social.index'));

            return $d;
        });
    }

    /** Ouvre la procédure de soutien social (type « deuil d'un agent », degré « agent ») si elle est paramétrée. */
    private function openFamilySupport(Death $d, Employee $e, User $user): void
    {
        $type = SupportType::query()->where('code', 'DEUIL_AGENT')->where('is_active', true)->first()
            ?? SupportType::query()->where('uses_kinship', true)->where('is_active', true)->first();
        if (! $type) {
            return;
        }
        $kin = KinshipDegree::query()->where('code', 'AGENT')->first();
        SocialSupport::create([
            'number' => $this->numbers->next('social_support'), 'employee_id' => $e->id, 'support_type_id' => $type->id,
            'kinship_degree_id' => $kin?->id, 'deceased_name' => $e->fullName(), 'event_date' => $d->death_date,
            'description' => 'Procédure ouverte automatiquement à la validation du décès de l’agent.',
            'amount_requested' => $kin?->support_amount ?? $type->default_amount, 'currency' => $kin?->currency ?? $type->currency,
            'beneficiary' => $d->contact_name, 'status' => 'requested', 'death_id' => $d->id, 'created_by' => $user->id,
        ]);
    }
}
