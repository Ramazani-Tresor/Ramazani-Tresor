<?php

namespace App\Support;

use App\Exceptions\BusinessException;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;

/** Contrôles d'habilitation au niveau des services (défense en profondeur, en plus des routes). */
final class Authz
{
    public static function need(User $user, string $permission, ?Employee $employee = null): void
    {
        $ok = $employee ? $user->canOnEmployee($permission, $employee) : $user->hasPermission($permission);
        if (! $ok) {
            throw new AuthorizationException("Habilitation requise : {$permission}.");
        }
    }

    /** Séparation des tâches : celui qui valide ne peut pas être l'auteur (paramètre segregation_of_duties). */
    public static function differentFrom(User $validator, ?int $authorId, string $what = 'cette opération'): void
    {
        if (Settings::bool('segregation_of_duties') && $authorId && $authorId === $validator->id) {
            throw new BusinessException("Séparation des tâches : vous ne pouvez pas valider {$what} que vous avez vous-même saisie.");
        }
    }
}
