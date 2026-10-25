<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DocumentSequence extends Model
{
    protected $fillable = ['type', 'label', 'prefix', 'next_number', 'padding', 'yearly_reset', 'current_year'];

    protected $casts = ['yearly_reset' => 'boolean'];
}
