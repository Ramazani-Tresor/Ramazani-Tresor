<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SupportType extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'uses_kinship' => 'boolean',
            'default_amount' => 'decimal:2',
            'max_amount' => 'decimal:2',
        ];
    }
}
