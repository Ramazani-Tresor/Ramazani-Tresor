<?php

namespace App\Models;

use App\Models\Concerns\Undeletable;
use Illuminate\Database\Eloquent\Model;

class AssetMovement extends Model
{
    use Undeletable;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'movement_date' => 'date',
        ];
    }

    public function asset()
    {
        return $this->belongsTo(Asset::class);
    }

    public function fromUnit()
    {
        return $this->belongsTo(OrgUnit::class, 'from_unit_id');
    }

    public function toUnit()
    {
        return $this->belongsTo(OrgUnit::class, 'to_unit_id');
    }

    public function responsible()
    {
        return $this->belongsTo(Employee::class, 'responsible_employee_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
