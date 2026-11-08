<?php

namespace App\Models;

use App\Models\Concerns\RecordsChanges;
use App\Models\Concerns\Undeletable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notifiable;

/** Dossier électronique unique d'un agent. Jamais supprimé (décès, mutation, départ : changement de statut). */
class Employee extends Model
{
    use Notifiable, RecordsChanges, Undeletable;

    public const STATUSES = [
        'active' => 'En activité', 'on_leave' => 'En congé', 'available' => 'En disponibilité', 'suspended' => 'Suspendu',
        'transferred_out' => 'Muté vers une autre institution', 'deceased' => 'Décédé', 'retired' => 'Retraité',
        'resigned' => 'Démissionnaire', 'departed' => 'Départ définitif',
    ];

    /** Statuts qui mettent fin aux droits ordinaires (plus de nouvelles primes après la date d'effet). */
    public const ENDED_STATUSES = ['transferred_out', 'deceased', 'retired', 'resigned', 'departed'];

    public array $maskedHistory = ['bank_name', 'bank_account', 'bank_holder'];

    protected $guarded = ['id'];

    protected $hidden = ['bank_name', 'bank_account', 'bank_holder'];

    protected function casts(): array
    {
        return [
            'birth_date' => 'date', 'first_service_date' => 'date', 'engagement_date' => 'date', 'mandate_start' => 'date',
            'mandate_end' => 'date', 'status_since' => 'date', 'rights_end_date' => 'date',
            'bank_name' => 'encrypted', 'bank_account' => 'encrypted', 'bank_holder' => 'encrypted',
        ];
    }

    public function historyEmployeeId(): ?int
    {
        return $this->id;
    }

    public function routeNotificationForMail(): ?string
    {
        return $this->email ?: $this->user?->email;
    }

    public function user()
    {
        return $this->hasOne(User::class);
    }

    public function category()
    {
        return $this->belongsTo(StaffCategory::class, 'staff_category_id');
    }

    /** Catégorie administrative d'un assistant de recherche (ex. Chef de bureau), distincte de sa fonction scientifique. */
    public function adminGrade()
    {
        return $this->belongsTo(Grade::class, 'admin_grade_id');
    }

    public function grade()
    {
        return $this->belongsTo(Grade::class);
    }

    public function mandate()
    {
        return $this->belongsTo(Mandate::class);
    }

    public function jobFunction()
    {
        return $this->belongsTo(JobFunction::class);
    }

    public function documents()
    {
        return $this->hasMany(EmployeeDocument::class);
    }

    public function careerEvents()
    {
        return $this->hasMany(CareerEvent::class);
    }

    public function qualifications()
    {
        return $this->hasMany(Qualification::class);
    }

    public function assignments()
    {
        return $this->hasMany(Assignment::class);
    }

    public function activeAssignment()
    {
        return $this->hasOne(Assignment::class)->where('status', 'active')->where('is_primary', true)->latestOfMany('start_date');
    }

    public function mutations()
    {
        return $this->hasMany(Mutation::class);
    }

    public function situations()
    {
        return $this->hasMany(AdministrativeSituation::class);
    }

    public function death()
    {
        return $this->hasOne(Death::class);
    }

    public function entitlements()
    {
        return $this->hasMany(PrimeEntitlement::class);
    }

    public function primePayments()
    {
        return $this->hasMany(PrimePayment::class);
    }

    public function socialSupports()
    {
        return $this->hasMany(SocialSupport::class);
    }

    public function correctionRequests()
    {
        return $this->hasMany(CorrectionRequest::class);
    }

    public function fullName(): string
    {
        return trim(mb_strtoupper($this->last_name).' '.mb_strtoupper((string) $this->middle_name).' '.$this->first_name);
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    public function currentUnitId(): ?int
    {
        return $this->activeAssignment?->org_unit_id;
    }

    public function isEnded(): bool
    {
        return in_array($this->status, self::ENDED_STATUSES, true);
    }

    public function scopeSearch(Builder $q, ?string $term): Builder
    {
        $term = trim((string) $term);
        if ($term === '') {
            return $q;
        }

        return $q->where(function (Builder $w) use ($term) {
            $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $term).'%';
            $w->where('matricule', 'like', $like)->orWhere('last_name', 'like', $like)
                ->orWhere('middle_name', 'like', $like)->orWhere('first_name', 'like', $like);
        });
    }

    /** Agents dont l'affectation principale active est dans l'unité ou ses sous-unités. */
    public function scopeInUnit(Builder $q, ?int $unitId): Builder
    {
        if (! $unitId) {
            return $q;
        }

        return $q->whereHas('assignments', fn ($a) => $a->where('status', 'active')->where('is_primary', true)->whereIn('org_unit_id', OrgUnit::subtreeIds($unitId)));
    }
}
