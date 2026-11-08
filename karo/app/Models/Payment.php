<?php

namespace App\Models;

use App\Support\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Payment extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'deposit_date' => 'date',
        'amount' => 'decimal:2',
        'bank_confirmed_amount' => 'decimal:2',
        'anomalies' => 'array',
        'checked_at' => 'datetime',
        'validated_at' => 'datetime',
        'rejected_at' => 'datetime',
        'reversed_at' => 'datetime',
    ];

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class);
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(PaymentAllocation::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(PaymentEvent::class)->orderBy('id');
    }

    public function matches(): HasMany
    {
        return $this->hasMany(ReconciliationMatch::class);
    }

    public function creditOperations(): HasMany
    {
        return $this->hasMany(CreditOperation::class, 'source_payment_id');
    }

    public function originPayment(): BelongsTo
    {
        return $this->belongsTo(Payment::class, 'origin_payment_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function checker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'checked_by');
    }

    public function validator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'validated_by');
    }

    /** Montant affecté (affectations proposées ou effectives). */
    public function allocatedAmount(): string
    {
        return Money::money($this->allocations()->whereIn('status', ['proposed', 'active'])->sum('amount'));
    }

    /** Montant consommé ou réservé par des opérations sur excédent (remboursement, report). */
    public function creditUsedAmount(): string
    {
        return Money::money($this->creditOperations()->whereIn('type', ['refund', 'carry_forward'])->whereIn('status', ['pending', 'executed'])->sum('amount'));
    }

    /** Compensations en attente : montant réservé, pas encore affecté. */
    public function pendingCompensations(): string
    {
        return Money::money($this->creditOperations()->where('type', 'compensation')->where('status', 'pending')->sum('amount'));
    }

    /** Disponible = montant − affecté − remboursé/reporté (ou réservé). */
    public function availableAmount(): string
    {
        return Money::money(Money::of($this->amount)->minus(Money::of($this->allocatedAmount()))
            ->minus(Money::of($this->creditUsedAmount()))->minus(Money::of($this->pendingCompensations())));
    }

    public function isFinal(): bool
    {
        return in_array($this->status, ['rejected', 'reversed'], true);
    }
}
