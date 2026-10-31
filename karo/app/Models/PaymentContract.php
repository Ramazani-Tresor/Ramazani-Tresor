<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PaymentContract extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['total_due' => 'decimal:2', 'paid_at_signature' => 'decimal:2', 'balance' => 'decimal:2', 'charge_ids' => 'array', 'signatories' => 'array', 'approved_at' => 'datetime'];

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function installments(): HasMany
    {
        return $this->hasMany(ContractInstallment::class)->orderBy('sequence');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(PaymentContract::class, 'parent_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
