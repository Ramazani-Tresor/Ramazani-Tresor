<?php

namespace App\Models;

use App\Models\Concerns\Undeletable;
use Illuminate\Database\Eloquent\Model;

class SocialSupport extends Model
{
    use Undeletable;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'event_date' => 'date',
            'paid_on' => 'date',
            'approved_at' => 'datetime',
            'amount_requested' => 'decimal:2',
            'amount_granted' => 'decimal:2',
        ];
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function type()
    {
        return $this->belongsTo(SupportType::class, 'support_type_id');
    }

    public function kinship()
    {
        return $this->belongsTo(KinshipDegree::class, 'kinship_degree_id');
    }

    public function death()
    {
        return $this->belongsTo(Death::class);
    }

    public function author()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function documents()
    {
        return $this->belongsToMany(EmployeeDocument::class, 'social_support_documents', 'social_support_id', 'employee_document_id');
    }
}
