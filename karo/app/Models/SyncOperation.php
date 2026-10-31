<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class SyncOperation extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['payload' => 'array', 'messages' => 'array', 'prepared_at' => 'datetime', 'resolved_at' => 'datetime'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function result(): MorphTo
    {
        return $this->morphTo();
    }
}
