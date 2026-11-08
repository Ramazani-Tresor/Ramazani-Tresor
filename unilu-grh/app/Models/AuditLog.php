<?php

namespace App\Models;

use App\Exceptions\BusinessException;
use Illuminate\Database\Eloquent\Model;

/** Entrée du journal d'audit : jamais modifiée ni supprimée (chaîne de hachage). */
class AuditLog extends Model
{
    public $timestamps = false;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['old_values' => 'array', 'new_values' => 'array', 'created_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new BusinessException('Le journal d’audit ne peut pas être modifié.'));
        static::deleting(fn () => throw new BusinessException('Le journal d’audit ne peut pas être supprimé.'));
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
