<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class CashMovement extends Model
{
    public const TYPES = [
        'opening' => 'Fonds initial',
        'sale' => 'Encaissement vente',
        'debt_settlement' => 'Règlement de créance',
        'refund' => 'Remboursement client',
        'expense' => 'Dépense',
        'supplier_payment' => 'Paiement fournisseur',
        'deposit' => 'Apport en caisse',
        'withdrawal' => 'Retrait de caisse',
    ];

    protected $fillable = ['cash_session_id', 'payment_method_id', 'type', 'amount', 'description', 'source_type', 'source_id', 'user_id'];

    protected $casts = ['amount' => 'decimal:2'];

    public function session(): BelongsTo
    {
        return $this->belongsTo(CashSession::class, 'cash_session_id');
    }

    public function paymentMethod(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class);
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
