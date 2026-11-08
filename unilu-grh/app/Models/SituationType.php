<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SituationType extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'closes_assignment' => 'boolean',
            'ends_employment' => 'boolean',
            'requires_end_date' => 'boolean',
            'requires_approval' => 'boolean',
        ];
    }
}
