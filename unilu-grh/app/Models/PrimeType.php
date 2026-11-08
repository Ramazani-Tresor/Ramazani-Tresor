<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PrimeType extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'prorata' => 'boolean',
        ];
    }

    public function rates()
    {
        return $this->hasMany(PrimeRate::class);
    }
}
