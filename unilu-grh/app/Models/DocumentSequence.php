<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DocumentSequence extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'yearly_reset' => 'boolean',
        ];
    }
}
