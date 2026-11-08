<?php

namespace App\Models;

use App\Models\Concerns\Undeletable;
use Illuminate\Database\Eloquent\Model;

class Mutation extends Model
{
    use Undeletable;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'decision_date' => 'date',
            'effective_date' => 'date',
            'validated_at' => 'datetime',
            'financial_snapshot' => 'array',
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

    public function toUnit()
    {
        return $this->belongsTo(OrgUnit::class, 'to_unit_id');
    }

    public function toPosition()
    {
        return $this->belongsTo(Position::class, 'to_position_id');
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
