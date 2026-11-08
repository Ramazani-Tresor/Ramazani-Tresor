<?php

namespace App\Models;

use App\Models\Concerns\Undeletable;
use Illuminate\Database\Eloquent\Model;

class PrimeAdjustment extends Model
{
    use Undeletable;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
        ];
    }

    public function entitlement()
    {
        return $this->belongsTo(PrimeEntitlement::class, 'prime_entitlement_id');
    }

    public function author()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
