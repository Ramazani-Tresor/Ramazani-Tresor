<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Inventory extends Model
{
    protected $fillable = ['number', 'category_id', 'status', 'notes', 'opened_by', 'validated_by', 'opened_at', 'validated_at'];

    protected $casts = ['opened_at' => 'datetime', 'validated_at' => 'datetime'];

    public function items(): HasMany
    {
        return $this->hasMany(InventoryItem::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function opener(): BelongsTo
    {
        return $this->belongsTo(User::class, 'opened_by');
    }

    public function validator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'validated_by');
    }

    public function isOpen(): bool
    {
        return $this->status === 'open';
    }
}
