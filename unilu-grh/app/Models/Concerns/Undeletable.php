<?php

namespace App\Models\Concerns;

use App\Exceptions\BusinessException;

/** Interdit toute suppression physique : la correction passe par un statut, une contrepassation ou une régularisation. */
trait Undeletable
{
    public static function bootUndeletable(): void
    {
        static::deleting(function () {
            throw new BusinessException('Suppression interdite : cet enregistrement fait partie de l’historique (utilisez une annulation, une clôture ou une régularisation).');
        });
    }
}
