<?php

namespace App\Models;

use App\Models\Concerns\Undeletable;
use Illuminate\Database\Eloquent\Model;

class PrimePayment extends Model
{
    use Undeletable;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'payment_date' => 'date',
            'validated_at' => 'datetime',
        ];
    }

    public function entitlement()
    {
        return $this->belongsTo(PrimeEntitlement::class, 'prime_entitlement_id');
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function reverses()
    {
        return $this->belongsTo(PrimePayment::class, 'reverses_id');
    }

    public function author()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function validator()
    {
        return $this->belongsTo(User::class, 'validated_by');
    }
}
