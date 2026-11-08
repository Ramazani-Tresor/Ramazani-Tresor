<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Position extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function orgUnit()
    {
        return $this->belongsTo(OrgUnit::class, 'org_unit_id');
    }

    public function jobFunction()
    {
        return $this->belongsTo(JobFunction::class);
    }

    public function assignments()
    {
        return $this->hasMany(Assignment::class);
    }
}
