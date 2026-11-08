<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChargeInstallment extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['due_date' => 'date', 'amount' => 'decimal:2'];

    public function charge(): BelongsTo
    {
        return $this->belongsTo(Charge::class);
    }
}
