<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Inventory extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'started_on' => 'date',
            'closed_on' => 'date',
        ];
    }

    public function orgUnit()
    {
        return $this->belongsTo(OrgUnit::class, 'org_unit_id');
    }

    public function lines()
    {
        return $this->hasMany(InventoryLine::class);
    }
}
