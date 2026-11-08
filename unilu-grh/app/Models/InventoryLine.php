<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InventoryLine extends Model
{
    protected $guarded = ['id'];

    public function inventory()
    {
        return $this->belongsTo(Inventory::class);
    }

    public function asset()
    {
        return $this->belongsTo(Asset::class);
    }
}
