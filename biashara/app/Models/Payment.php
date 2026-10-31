<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    public const KINDS = [
        'sale' => 'Encaissement vente',
        'debt_settlement' => 'Règlement de créance',
        'refund' => 'Remboursement',
    ];

    protected $fillable = [
        'kind', 'sale_id', 'customer_settlement_id', 'sale_return_id', 'customer_id', 'payment_method_id',
        'cash_session_id', 'user_id', 'amount', 'currency', 'currency_amount', 'exchange_rate', 'reference', 'paid_at',
    ];

    protected $casts = [
        'paid_at' => 'datetime',
        'amount' => 'decimal:2',
        'currency_amount' => 'decimal:2',
        'exchange_rate' => 'decimal:4',
    ];

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function method(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class, 'payment_method_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
