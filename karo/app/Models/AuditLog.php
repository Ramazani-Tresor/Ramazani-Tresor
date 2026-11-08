<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Journal d'audit en ajout seul : aucune mise à jour ni suppression via l'application. */
class AuditLog extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = ['id'];

    protected $casts = ['old_values' => 'array', 'new_values' => 'array', 'created_at' => 'datetime'];

    protected static function booted(): void
    {
        static::updating(fn () => throw new \LogicException('Le journal d’audit ne peut pas être modifié.'));
        static::deleting(fn () => throw new \LogicException('Le journal d’audit ne peut pas être supprimé.'));
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
