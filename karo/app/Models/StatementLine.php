<?php

namespace App\Models;

use App\Support\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StatementLine extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['operation_date' => 'date', 'value_date' => 'date', 'amount' => 'decimal:2', 'matched_amount' => 'decimal:2', 'reviewed_at' => 'datetime'];

    public function batch(): BelongsTo
    {
        return $this->belongsTo(ImportBatch::class, 'import_batch_id');
    }

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class);
    }

    public function matches(): HasMany
    {
        return $this->hasMany(ReconciliationMatch::class);
    }

    public function remaining(): string
    {
        return Money::money(Money::sub($this->amount, $this->matched_amount));
    }
}
