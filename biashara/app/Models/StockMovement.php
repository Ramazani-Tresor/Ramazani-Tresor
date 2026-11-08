<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class StockMovement extends Model
{
    public const TYPES = [
        'initial' => 'Stock initial',
        'purchase' => 'Entrée approvisionnement',
        'sale' => 'Sortie pour vente',
        'sale_cancel' => 'Annulation de vente',
        'customer_return' => 'Retour client remis en stock',
        'customer_return_damaged' => 'Retour client endommagé',
        'supplier_return' => 'Retour fournisseur',
        'inventory_adjustment' => "Ajustement d'inventaire",
        'loss' => 'Perte / casse / détérioration',
        'damaged_writeoff' => 'Mise au rebut (stock endommagé)',
    ];

    /** Types de sorties manuelles autorisées depuis l'écran « Stock ». */
    public const MANUAL_EXIT_TYPES = ['loss', 'supplier_return', 'damaged_writeoff'];

    protected $fillable = [
        'product_id', 'type', 'bucket', 'quantity', 'unit_cost', 'balance_after', 'reason', 'source_type',
        'source_id', 'source_number', 'user_id', 'occurred_at',
    ];

    protected $casts = [
        'occurred_at' => 'datetime',
        'quantity' => 'decimal:3',
        'unit_cost' => 'decimal:4',
        'balance_after' => 'decimal:3',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function source(): MorphTo
    {
        return $this->morphTo();
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->type] ?? $this->type;
    }
}
