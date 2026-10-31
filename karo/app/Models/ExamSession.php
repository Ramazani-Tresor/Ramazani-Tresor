<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ExamSession extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['starts_on' => 'date', 'ends_on' => 'date', 'payment_deadline' => 'date', 'require_bank_confirmation' => 'boolean', 'threshold_percent' => 'decimal:2', 'fee_category_ids' => 'array'];

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function waivers(): HasMany
    {
        return $this->hasMany(ExamWaiver::class);
    }

    public function feeCategories()
    {
        return FeeCategory::query()->where('trigger', 'exam_session')->where('session_semester', $this->semester)->where('session_type', $this->type);
    }
}
