<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PrimeRate extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'valid_from' => 'date',
            'valid_to' => 'date',
            'amount' => 'decimal:2',
        ];
    }

    public function primeType()
    {
        return $this->belongsTo(PrimeType::class);
    }

    public function academicYear()
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function category()
    {
        return $this->belongsTo(StaffCategory::class, 'staff_category_id');
    }

    public function grade()
    {
        return $this->belongsTo(Grade::class);
    }

    public function jobFunction()
    {
        return $this->belongsTo(JobFunction::class);
    }

    public function orgUnit()
    {
        return $this->belongsTo(OrgUnit::class, 'org_unit_id');
    }
}
