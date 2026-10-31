<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class BankAccount extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['is_active' => 'boolean'];

    public function bank(): BelongsTo
    {
        return $this->belongsTo(Bank::class);
    }

    public function feeCategories(): BelongsToMany
    {
        return $this->belongsToMany(FeeCategory::class);
    }

    public function display(): string
    {
        return $this->bank->name.' — '.$this->account_number.' ('.$this->currency.')';
    }
}
