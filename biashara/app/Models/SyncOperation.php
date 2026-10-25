<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class SyncOperation extends Model
{
    public const STATUSES = ['synced' => 'Synchronisée', 'conflict' => 'En conflit', 'rejected' => 'Rejetée'];

    protected $fillable = [
        'client_uuid', 'type', 'device_id', 'user_id', 'payload', 'status', 'conflicts', 'message', 'result_type',
        'result_id', 'created_offline_at', 'attempts', 'resolved_by', 'resolved_at', 'resolution_note',
    ];

    protected $casts = [
        'payload' => 'array',
        'conflicts' => 'array',
        'created_offline_at' => 'datetime',
        'resolved_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    public function result(): MorphTo
    {
        return $this->morphTo();
    }
}
