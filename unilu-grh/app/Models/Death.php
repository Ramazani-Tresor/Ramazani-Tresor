<?php

namespace App\Models;

use App\Models\Concerns\Undeletable;
use Illuminate\Database\Eloquent\Model;

class Death extends Model
{
    use Undeletable;

    protected $guarded = ['id'];

    protected $hidden = ['cause'];

    protected function casts(): array
    {
        return [
            'death_date' => 'date',
            'validated_at' => 'datetime',
            'financial_snapshot' => 'array',
            'cause' => 'encrypted',
        ];
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class);
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

    public array $maskedHistory = ['cause'];
}
