<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class IssuedDocument extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['summary' => 'array', 'content' => 'array', 'issued_at' => 'datetime'];

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function issuer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by');
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }
}
