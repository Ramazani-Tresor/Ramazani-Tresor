<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Grade extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'has_mandates' => 'boolean',
            'characteristics' => 'array',
        ];
    }

    public function category()
    {
        return $this->belongsTo(StaffCategory::class, 'staff_category_id');
    }

    public function mandates()
    {
        return $this->hasMany(Mandate::class);
    }
}
