<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class JobFunction extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_responsibility' => 'boolean',
        ];
    }
}
