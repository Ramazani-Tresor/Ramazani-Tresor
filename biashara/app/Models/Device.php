<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Device extends Model
{
    protected $fillable = ['device_id', 'label', 'user_id', 'user_agent', 'last_seen_at', 'last_sync_at'];

    protected $casts = ['last_seen_at' => 'datetime', 'last_sync_at' => 'datetime'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
