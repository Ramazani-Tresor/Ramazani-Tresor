<?php

namespace App\Models;

use App\Models\Concerns\Undeletable;
use Illuminate\Database\Eloquent\Model;

class Asset extends Model
{
    use Undeletable;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'acquisition_date' => 'date',
            'value' => 'decimal:2',
        ];
    }

    public function category()
    {
        return $this->belongsTo(AssetCategory::class, 'asset_category_id');
    }

    public function parent()
    {
        return $this->belongsTo(Asset::class, 'parent_id');
    }

    public function orgUnit()
    {
        return $this->belongsTo(OrgUnit::class, 'org_unit_id');
    }

    public function responsible()
    {
        return $this->belongsTo(Employee::class, 'responsible_employee_id');
    }

    public function movements()
    {
        return $this->hasMany(AssetMovement::class);
    }

    public function children()
    {
        return $this->hasMany(Asset::class, 'parent_id');
    }
}
