<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Purchase extends Model
{
    protected $fillable = [
        'number', 'supplier_id', 'supplier_reference', 'purchase_date', 'status', 'total', 'paid_from_cash',
        'notes', 'user_id', 'validated_by', 'validated_at',
    ];

    protected $casts = [
        'purchase_date' => 'date',
        'validated_at' => 'datetime',
        'paid_from_cash' => 'boolean',
        'total' => 'decimal:2',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseItem::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function validator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'validated_by');
    }

    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }
}
