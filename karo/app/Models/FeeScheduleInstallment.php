<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FeeScheduleInstallment extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['due_date' => 'date', 'amount' => 'decimal:2', 'value' => 'decimal:2'];
}
