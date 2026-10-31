<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReconciliationMatch extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['amount' => 'decimal:2', 'criteria' => 'array', 'cancelled_at' => 'datetime'];

    public function line(): BelongsTo
    {
        return $this->belongsTo(StatementLine::class, 'statement_line_id');
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
