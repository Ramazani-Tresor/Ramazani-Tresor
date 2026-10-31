<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = ['name', 'username', 'email', 'phone', 'password', 'is_active', 'must_change_password', 'student_id'];

    protected $hidden = ['password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes'];

    /** @var array<int, array{perms: string[], faculty_id: ?int, year_id: ?int, requires_2fa: bool}>|null */
    private ?array $grants = null;

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'is_active' => 'boolean',
            'must_change_password' => 'boolean',
            'two_factor_secret' => 'encrypted',
            'two_factor_recovery_codes' => 'encrypted:array',
            'two_factor_confirmed_at' => 'datetime',
            'last_login_at' => 'datetime',
            'archived_at' => 'datetime',
        ];
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(UserRoleAssignment::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    private function grants(): array
    {
        if ($this->grants === null) {
            $this->grants = $this->assignments()->with('role.permissions')->get()->map(fn ($a) => [
                'perms' => $a->role->permissions->pluck('slug')->all(),
                'faculty_id' => $a->faculty_id,
                'year_id' => $a->academic_year_id,
                'requires_2fa' => $a->role->requires_2fa,
            ])->all();
        }

        return $this->grants;
    }

    public function flushGrants(): void
    {
        $this->grants = null;
    }

    /**
     * Permission dans un périmètre. Sans faculté/année précisée : vrai si la permission est
     * détenue dans au moins un périmètre (le contrôle fin se fait ensuite sur la donnée).
     */
    public function hasPermission(string $permission, ?int $facultyId = null, ?int $yearId = null): bool
    {
        foreach ($this->grants() as $g) {
            if (! in_array($permission, $g['perms'], true)) {
                continue;
            }
            if ($facultyId !== null && $g['faculty_id'] !== null && $g['faculty_id'] !== $facultyId) {
                continue;
            }
            if ($yearId !== null && $g['year_id'] !== null && $g['year_id'] !== $yearId) {
                continue;
            }

            return true;
        }

        return false;
    }

    /** null = toutes les facultés ; sinon liste des facultés autorisées pour cette permission. */
    public function facultyScope(string $permission): ?array
    {
        $ids = [];
        foreach ($this->grants() as $g) {
            if (in_array($permission, $g['perms'], true)) {
                if ($g['faculty_id'] === null) {
                    return null;
                }
                $ids[] = $g['faculty_id'];
            }
        }

        return array_values(array_unique($ids));
    }

    /** null = toutes les années ; sinon liste des années autorisées. */
    public function yearScope(string $permission): ?array
    {
        $ids = [];
        foreach ($this->grants() as $g) {
            if (in_array($permission, $g['perms'], true)) {
                if ($g['year_id'] === null) {
                    return null;
                }
                $ids[] = $g['year_id'];
            }
        }

        return array_values(array_unique($ids));
    }

    /** Accès à un dossier : protège contre la manipulation d'identifiants dans l'URL. */
    public function canAccessStudent(Student $student, string $permission, ?int $yearId = null): bool
    {
        if ($this->student_id !== null && $this->student_id === $student->id && $this->hasPermission('student.self')) {
            return true;
        }
        if (! $this->hasPermission($permission)) {
            return false;
        }
        $faculties = $this->facultyScope($permission);
        $years = $this->yearScope($permission);
        if ($faculties === null && $years === null) {
            return true;
        }
        foreach ($student->enrollments()->with('promotion.program.department')->get() as $e) {
            if (($faculties === null || in_array($e->facultyId(), $faculties, true))
                && ($years === null || in_array($e->academic_year_id, $years, true))
                && ($yearId === null || $yearId === $e->academic_year_id)) {
                return true;
            }
        }

        return false;
    }

    public function requiresTwoFactor(): bool
    {
        foreach ($this->grants() as $g) {
            if ($g['requires_2fa']) {
                return true;
            }
        }

        return false;
    }

    public function hasTwoFactor(): bool
    {
        return $this->two_factor_secret !== null && $this->two_factor_confirmed_at !== null;
    }

    public function roleNames(): string
    {
        return $this->assignments()->with('role')->get()->pluck('role.name')->unique()->implode(', ');
    }
}
