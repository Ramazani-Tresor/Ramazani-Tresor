<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FeeCategory extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['applicable_statuses' => 'array', 'final_promotions_only' => 'boolean', 'is_active' => 'boolean', 'valid_from' => 'date', 'valid_until' => 'date'];

    public function bankAccounts(): BelongsToMany
    {
        return $this->belongsToMany(BankAccount::class);
    }

    public function schedules(): HasMany
    {
        return $this->hasMany(FeeSchedule::class);
    }
}
