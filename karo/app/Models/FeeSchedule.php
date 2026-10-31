<?php

namespace App\Models;

use App\Support\Labels;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FeeSchedule extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['amount' => 'decimal:2', 'effective_from' => 'date', 'approved_at' => 'datetime', 'apply_to_existing' => 'boolean'];

    public function category(): BelongsTo
    {
        return $this->belongsTo(FeeCategory::class, 'fee_category_id');
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function installments(): HasMany
    {
        return $this->hasMany(FeeScheduleInstallment::class)->orderBy('sequence');
    }

    public function faculty(): BelongsTo
    {
        return $this->belongsTo(Faculty::class);
    }

    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class);
    }

    public function promotion(): BelongsTo
    {
        return $this->belongsTo(Promotion::class);
    }

    public function cycle(): BelongsTo
    {
        return $this->belongsTo(Cycle::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function criteriaLabel(): string
    {
        $p = array_filter([$this->faculty?->code, $this->program?->code, $this->promotion?->code, $this->cycle?->code, $this->student_status ? Labels::status('enrollment_status', $this->student_status) : null]);

        return $p ? implode(' · ', $p) : 'Tous les étudiants';
    }
}
