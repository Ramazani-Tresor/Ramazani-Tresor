<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;

/** Contrôle d'habilitation au niveau des services (défense en profondeur, en plus des routes). */
final class Authz
{
    public static function need(User $user, string $permission, ?int $facultyId = null, ?int $yearId = null): void
    {
        if (! $user->hasPermission($permission, $facultyId, $yearId)) {
            throw new AuthorizationException("Habilitation requise : {$permission}.");
        }
    }
}
