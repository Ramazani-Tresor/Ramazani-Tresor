<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AcademicYear extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['starts_on' => 'date', 'ends_on' => 'date', 'is_current' => 'boolean', 'closed_at' => 'datetime'];

    public function sessions(): HasMany
    {
        return $this->hasMany(ExamSession::class)->orderBy('semester')->orderBy('type');
    }

    public function semesters(): HasMany
    {
        return $this->hasMany(Semester::class)->orderBy('number');
    }

    public function previous(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class, 'previous_year_id');
    }

    public function closer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public function isClosed(): bool
    {
        return $this->status === 'closed';
    }

    public function next(): ?self
    {
        return self::query()->where('previous_year_id', $this->id)->first();
    }

    public static function current(): ?self
    {
        return self::query()->where('is_current', true)->first() ?? self::query()->where('status', 'open')->orderByDesc('starts_on')->first();
    }
}
