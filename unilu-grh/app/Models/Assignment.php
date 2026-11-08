<?php

namespace App\Models;

use App\Models\Concerns\RecordsChanges;
use App\Models\Concerns\Undeletable;
use Illuminate\Database\Eloquent\Model;

class Assignment extends Model
{
    use RecordsChanges, Undeletable;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'is_primary' => 'boolean',
        ];
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function fromUnit()
    {
        return $this->belongsTo(OrgUnit::class, 'from_unit_id');
    }

    public function orgUnit()
    {
        return $this->belongsTo(OrgUnit::class, 'org_unit_id');
    }

    public function position()
    {
        return $this->belongsTo(Position::class);
    }

    public function jobFunction()
    {
        return $this->belongsTo(JobFunction::class);
    }

    public function document()
    {
        return $this->belongsTo(EmployeeDocument::class, 'document_id');
    }
}
