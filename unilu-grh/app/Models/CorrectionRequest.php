<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CorrectionRequest extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'handled_at' => 'datetime',
        ];
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function handler()
    {
        return $this->belongsTo(User::class, 'handled_by');
    }

    public function document()
    {
        return $this->belongsTo(EmployeeDocument::class, 'document_id');
    }
}
