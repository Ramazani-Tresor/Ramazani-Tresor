<?php

namespace App\Models;

use App\Support\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class Product extends Model
{
    protected $fillable = [
        'reference', 'name', 'type', 'category_id', 'unit_id', 'tax_id', 'description', 'barcode',
        'photo_path', 'cost_price', 'sale_price', 'alert_threshold', 'is_active',
    ];

    // stock_quantity, damaged_quantity et average_cost ne sont jamais assignables en masse :
    // ils ne changent que via StockService.

    protected $casts = [
        'is_active' => 'boolean',
        'cost_price' => 'decimal:2',
        'sale_price' => 'decimal:2',
        'average_cost' => 'decimal:4',
        'alert_threshold' => 'decimal:3',
        'stock_quantity' => 'decimal:3',
        'damaged_quantity' => 'decimal:3',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function tax(): BelongsTo
    {
        return $this->belongsTo(Tax::class);
    }

    public function movements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    public function isService(): bool
    {
        return $this->type === 'service';
    }

    public function isLowStock(): bool
    {
        return ! $this->isService()
            && Money::cmp($this->stock_quantity, $this->alert_threshold) <= 0;
    }

    public function scopeActive(Builder $q): Builder
    {
        return $q->where('is_active', true);
    }

    public function scopeSearch(Builder $q, ?string $term): Builder
    {
        if (! $term) {
            return $q;
        }

        return $q->where(function (Builder $w) use ($term) {
            $w->where('name', 'like', "%{$term}%")
                ->orWhere('reference', 'like', "%{$term}%")
                ->orWhere('barcode', $term);
        });
    }

    public function scopeLowStock(Builder $q): Builder
    {
        return $q->where('type', 'product')->whereColumn('stock_quantity', '<=', 'alert_threshold');
    }

    public function photoUrl(): ?string
    {
        return $this->photo_path ? Storage::disk('public')->url($this->photo_path) : null;
    }
}
