<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KinshipDegree extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'support_amount' => 'decimal:2',
        ];
    }
}
