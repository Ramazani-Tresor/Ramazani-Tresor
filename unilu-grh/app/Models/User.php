<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use Notifiable;

    protected $guarded = ['id'];

    protected $hidden = ['password', 'remember_token'];

    /** @var array<int, array{0:string,1:?int,2:?string}>|null [permission, org_unit_id, famille] */
    private ?array $grants = null;

    protected function casts(): array
    {
        return [
            'password' => 'hashed', 'is_active' => 'boolean', 'must_change_password' => 'boolean',
            'last_login_at' => 'datetime', 'archived_at' => 'datetime',
        ];
    }

    public function roleAssignments()
    {
        return $this->hasMany(UserRoleAssignment::class);
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function grants(): array
    {
        if ($this->grants !== null) {
            return $this->grants;
        }
        $this->grants = [];
        foreach ($this->roleAssignments()->with('role.permissions:id,slug')->get() as $a) {
            foreach ($a->role?->permissions ?? [] as $p) {
                $this->grants[] = [$p->slug, $a->org_unit_id, $a->staff_family];
            }
        }

        return $this->grants;
    }

    public function flushGrants(): void
    {
        $this->grants = null;
    }

    /** Détient la permission dans au moins un périmètre. */
    public function hasPermission(string $permission): bool
    {
        foreach ($this->grants() as [$p]) {
            if ($p === $permission) {
                return true;
            }
        }

        return false;
    }

    /** Permission sans restriction de périmètre (toute l'université, toutes familles). */
    public function hasGlobalPermission(string $permission): bool
    {
        foreach ($this->grants() as [$p, $unit, $family]) {
            if ($p === $permission && $unit === null && $family === null) {
                return true;
            }
        }

        return false;
    }

    /**
     * Permission sur un agent précis : le périmètre de l'attribution (unité et sous-unités, famille)
     * doit couvrir l'affectation principale active de l'agent. Contrôle serveur contre la manipulation d'identifiants.
     */
    public function canOnEmployee(string $permission, Employee $employee): bool
    {
        $unitId = null;
        foreach ($this->grants() as [$p, $unit, $family]) {
            if ($p !== $permission) {
                continue;
            }
            if ($family !== null && $family !== $employee->staff_family) {
                continue;
            }
            if ($unit === null) {
                return true;
            }
            $unitId ??= $employee->currentUnitId() ?? 0;
            if (in_array($unitId, OrgUnit::subtreeIds($unit), true)) {
                return true;
            }
        }

        return false;
    }

    /** Restreint une requête sur les agents au périmètre de la permission. */
    public function scopeEmployees(Builder $query, string $permission): Builder
    {
        $conditions = [];
        foreach ($this->grants() as [$p, $unit, $family]) {
            if ($p !== $permission) {
                continue;
            }
            if ($unit === null && $family === null) {
                return $query;
            }
            $conditions[] = [$unit ? OrgUnit::subtreeIds($unit) : null, $family];
        }
        if (! $conditions) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where(function (Builder $q) use ($conditions) {
            foreach ($conditions as [$units, $family]) {
                $q->orWhere(function (Builder $w) use ($units, $family) {
                    if ($family) {
                        $w->where('staff_family', $family);
                    }
                    if ($units) {
                        $w->whereHas('assignments', fn ($a) => $a->where('status', 'active')->where('is_primary', true)->whereIn('org_unit_id', $units));
                    }
                });
            }
        });
    }

    public function roleNames(): string
    {
        return $this->roleAssignments->load('role')->map(fn ($a) => $a->role?->name)->filter()->unique()->implode(', ');
    }
}
