<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Sale extends Model
{
    protected $fillable = [
        'number', 'client_uuid', 'customer_id', 'user_id', 'cash_session_id', 'status', 'origin', 'device_id',
        'subtotal', 'discount_total', 'tax_total', 'total', 'amount_paid', 'amount_due', 'amount_received',
        'change_given', 'returned_total', 'refunded_total', 'currency', 'exchange_rate', 'notes', 'sold_at',
        'cancelled_at', 'cancelled_by', 'cancel_reason',
    ];

    protected $casts = [
        'sold_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'subtotal' => 'decimal:2',
        'discount_total' => 'decimal:2',
        'tax_total' => 'decimal:2',
        'total' => 'decimal:2',
        'amount_paid' => 'decimal:2',
        'amount_due' => 'decimal:2',
        'amount_received' => 'decimal:2',
        'change_given' => 'decimal:2',
        'returned_total' => 'decimal:2',
        'refunded_total' => 'decimal:2',
        'exchange_rate' => 'decimal:4',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(SaleItem::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function returns(): HasMany
    {
        return $this->hasMany(SaleReturn::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function canceller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    public function cashSession(): BelongsTo
    {
        return $this->belongsTo(CashSession::class);
    }

    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }
}
