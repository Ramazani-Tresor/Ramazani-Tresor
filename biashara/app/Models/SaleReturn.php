<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SaleReturn extends Model
{
    protected $fillable = [
        'number', 'client_uuid', 'sale_id', 'user_id', 'authorized_by', 'cash_session_id', 'refund_method_id',
        'reason', 'total_value', 'due_reduction', 'refund_amount', 'is_cancellation', 'returned_at',
    ];

    protected $casts = [
        'returned_at' => 'datetime',
        'is_cancellation' => 'boolean',
        'total_value' => 'decimal:2',
        'due_reduction' => 'decimal:2',
        'refund_amount' => 'decimal:2',
    ];

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(SaleReturnItem::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function authorizer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'authorized_by');
    }

    public function refundMethod(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class, 'refund_method_id');
    }
}
