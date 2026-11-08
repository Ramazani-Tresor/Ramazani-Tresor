<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Student extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['birth_date' => 'date', 'is_active' => 'boolean'];

    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class);
    }

    public function charges(): HasMany
    {
        return $this->hasMany(Charge::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function contracts(): HasMany
    {
        return $this->hasMany(PaymentContract::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(StudentDocument::class);
    }

    public function enrollmentFor(int $yearId): ?Enrollment
    {
        return $this->enrollments()->where('academic_year_id', $yearId)->where('state', 'active')->first();
    }

    public function fullName(): string
    {
        return trim($this->last_name.' '.$this->post_name.' '.$this->first_name);
    }

    /** Nom masqué pour la vérification publique des documents : « K. M. Jean » → initiales. */
    public function maskedName(): string
    {
        return mb_substr($this->last_name, 0, 1).'. '.($this->post_name ? mb_substr($this->post_name, 0, 1).'. ' : '').mb_substr($this->first_name, 0, 1).'.';
    }

    public function scopeSearch(Builder $q, ?string $term): Builder
    {
        if (! $term) {
            return $q;
        }

        return $q->where(fn ($w) => $w->where('matricule', 'like', "%{$term}%")
            ->orWhere('last_name', 'like', "%{$term}%")
            ->orWhere('post_name', 'like', "%{$term}%")
            ->orWhere('first_name', 'like', "%{$term}%"));
    }

    /** Restreint aux facultés autorisées (null = toutes). */
    public function scopeInFaculties(Builder $q, ?array $facultyIds, ?int $yearId = null): Builder
    {
        if ($facultyIds === null && $yearId === null) {
            return $q;
        }

        return $q->whereHas('enrollments', function ($e) use ($facultyIds, $yearId) {
            if ($yearId) {
                $e->where('academic_year_id', $yearId);
            }
            if ($facultyIds !== null) {
                $e->whereHas('promotion.program.department', fn ($d) => $d->whereIn('faculty_id', $facultyIds ?: [0]));
            }
        });
    }
}
