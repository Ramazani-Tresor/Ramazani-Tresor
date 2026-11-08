<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Promotion extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['is_final' => 'boolean', 'is_active' => 'boolean'];

    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class);
    }

    public function facultyId(): int
    {
        return $this->program->department->faculty_id;
    }

    public function fullLabel(): string
    {
        return $this->code.' — '.$this->name;
    }
}
