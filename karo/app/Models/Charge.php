<?php

namespace App\Models;

use App\Support\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Charge extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['amount' => 'decimal:2'];

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(FeeCategory::class, 'fee_category_id');
    }

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(FeeSchedule::class, 'fee_schedule_id');
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(ExamSession::class, 'exam_session_id');
    }

    public function installments(): HasMany
    {
        return $this->hasMany(ChargeInstallment::class)->orderBy('sequence');
    }

    public function adjustments(): HasMany
    {
        return $this->hasMany(ChargeAdjustment::class);
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(PaymentAllocation::class);
    }

    public function carriedFrom(): BelongsTo
    {
        return $this->belongsTo(Charge::class, 'carried_from_charge_id');
    }

    /** Ajustements approuvés (positif = diminue la créance). */
    public function approvedAdjustments(): string
    {
        return Money::money($this->adjustments()->where('status', 'approved')->sum('amount'));
    }

    /** Montant net dû = montant − ajustements approuvés. */
    public function netAmount(): string
    {
        return Money::money(Money::sub($this->amount, $this->approvedAdjustments()));
    }

    /** Règlements effectifs = affectations actives de paiements validés (dans la devise de la créance). */
    public function settledAmount(): string
    {
        return Money::money($this->allocations()->where('payment_allocations.status', 'active')
            ->whereHas('payment', fn ($p) => $p->where('status', 'validated'))->sum('charge_amount'));
    }

    public function balance(): string
    {
        return Money::money(Money::sub($this->netAmount(), $this->settledAmount()));
    }
}
