<?php

namespace App\Models;

use App\Models\Concerns\Undeletable;
use Illuminate\Database\Eloquent\Model;

class Candidate extends Model
{
    use Undeletable;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'birth_date' => 'date',
            'decision_date' => 'date',
            'engagement_date' => 'date',
        ];
    }

    public function category()
    {
        return $this->belongsTo(StaffCategory::class, 'staff_category_id');
    }

    public function grade()
    {
        return $this->belongsTo(Grade::class);
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

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function documents()
    {
        return $this->hasMany(CandidateDocument::class);
    }

    public function fullName(): string
    {
        return trim($this->last_name.' '.$this->middle_name.' '.$this->first_name);
    }
}
