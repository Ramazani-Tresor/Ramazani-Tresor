<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SaleReturnItem extends Model
{
    protected $fillable = ['sale_return_id', 'sale_item_id', 'product_id', 'quantity', 'condition', 'unit_value', 'line_value', 'unit_cost'];

    protected $casts = [
        'quantity' => 'decimal:3',
        'unit_value' => 'decimal:4',
        'line_value' => 'decimal:2',
        'unit_cost' => 'decimal:4',
    ];

    public function saleItem(): BelongsTo
    {
        return $this->belongsTo(SaleItem::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
