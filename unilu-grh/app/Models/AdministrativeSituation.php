<?php

namespace App\Models;

use App\Models\Concerns\Undeletable;
use Illuminate\Database\Eloquent\Model;

class AdministrativeSituation extends Model
{
    use Undeletable;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'planned_end_date' => 'date',
            'actual_end_date' => 'date',
            'approved_at' => 'datetime',
            'alert_sent_at' => 'datetime',
        ];
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function type()
    {
        return $this->belongsTo(SituationType::class, 'situation_type_id');
    }

    public function extended()
    {
        return $this->belongsTo(AdministrativeSituation::class, 'extends_id');
    }

    public function document()
    {
        return $this->belongsTo(EmployeeDocument::class, 'document_id');
    }

    public function author()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /** Durée en jours calendaires (bornes incluses), sur la fin effective, sinon prévue. */
    public function durationDays(): ?int
    {
        $end = $this->actual_end_date ?? $this->planned_end_date;

        return $end ? (int) $this->start_date->diffInDays($end) + 1 : null;
    }

    /** Fin retenue pour les calculs (null = situation ouverte). */
    public function effectiveEnd()
    {
        return $this->actual_end_date ?? $this->planned_end_date;
    }
}
