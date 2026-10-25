<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CustomerSettlement extends Model
{
    protected $fillable = [
        'number', 'client_uuid', 'customer_id', 'payment_method_id', 'cash_session_id', 'user_id', 'amount',
        'reference', 'notes', 'paid_at',
    ];

    protected $casts = ['paid_at' => 'datetime', 'amount' => 'decimal:2'];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function method(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class, 'payment_method_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(Payment::class);
    }
}
