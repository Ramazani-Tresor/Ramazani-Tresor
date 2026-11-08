<?php

namespace App\Models;

use App\Models\Concerns\Undeletable;
use Illuminate\Database\Eloquent\Model;

class CareerEvent extends Model
{
    use Undeletable;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'effective_date' => 'date',
            'decision_date' => 'date',
            'new_mandate_end' => 'date',
            'validated_at' => 'datetime',
        ];
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function oldGrade()
    {
        return $this->belongsTo(Grade::class, 'old_grade_id');
    }

    public function newGrade()
    {
        return $this->belongsTo(Grade::class, 'new_grade_id');
    }

    public function oldCategory()
    {
        return $this->belongsTo(StaffCategory::class, 'old_category_id');
    }

    public function newCategory()
    {
        return $this->belongsTo(StaffCategory::class, 'new_category_id');
    }

    public function oldMandate()
    {
        return $this->belongsTo(Mandate::class, 'old_mandate_id');
    }

    public function newMandate()
    {
        return $this->belongsTo(Mandate::class, 'new_mandate_id');
    }

    public function document()
    {
        return $this->belongsTo(EmployeeDocument::class, 'document_id');
    }

    public function author()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function validator()
    {
        return $this->belongsTo(User::class, 'validated_by');
    }
}
