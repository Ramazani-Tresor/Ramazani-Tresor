<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Unit extends Model
{
    protected $fillable = ['name', 'symbol', 'allows_decimal'];

    protected $casts = ['allows_decimal' => 'boolean'];
}
