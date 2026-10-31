<?php

namespace App\Models;

use App\Support\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SaleItem extends Model
{
    protected $fillable = [
        'sale_id', 'product_id', 'product_reference', 'product_name', 'product_type', 'unit_symbol', 'quantity',
        'unit_price', 'discount_amount', 'tax_name', 'tax_rate', 'tax_amount', 'line_total', 'unit_cost',
        'returned_quantity',
    ];

    protected $casts = [
        'quantity' => 'decimal:3',
        'unit_price' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'tax_rate' => 'decimal:4',
        'tax_amount' => 'decimal:2',
        'line_total' => 'decimal:2',
        'unit_cost' => 'decimal:4',
        'returned_quantity' => 'decimal:3',
    ];

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function returnableQuantity(): string
    {
        return (string) Money::of($this->quantity)->minus(Money::of($this->returned_quantity));
    }

    /** Valeur TTC unitaire effectivement facturée (remise et taxe incluses). */
    public function unitValue(): string
    {
        return (string) Money::div($this->line_total, $this->quantity, Money::COST_SCALE);
    }
}
