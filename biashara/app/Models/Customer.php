<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Customer extends Model
{
    protected $fillable = ['code', 'name', 'phone', 'email', 'address', 'credit_limit', 'notes', 'is_active'];

    protected $casts = ['is_active' => 'boolean', 'credit_limit' => 'decimal:2'];

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }

    public function settlements(): HasMany
    {
        return $this->hasMany(CustomerSettlement::class);
    }

    /** Solde restant dû : somme des créances des ventes validées. */
    public function balanceDue(): string
    {
        return (string) $this->sales()->where('status', 'validated')->sum('amount_due');
    }
}
