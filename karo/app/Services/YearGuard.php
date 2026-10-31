<?php

namespace App\Services;

use App\Exceptions\BusinessException;
use App\Models\AcademicYear;

/**
 * Point de contrôle unique : aucune écriture ordinaire sur une année clôturée,
 * que l'appel vienne de l'interface, de l'API ou de la synchronisation.
 */
class YearGuard
{
    public static function assertWritable(int|AcademicYear|null $year): AcademicYear
    {
        $y = $year instanceof AcademicYear ? AcademicYear::query()->findOrFail($year->id) : AcademicYear::query()->find($year);
        if (! $y) {
            throw new BusinessException('Année académique introuvable.');
        }
        if ($y->status === 'closed') {
            throw new BusinessException("L'année académique {$y->code} est clôturée : aucune écriture n'est possible. Une réouverture motivée et autorisée est nécessaire.");
        }
        if ($y->status === 'planned') {
            throw new BusinessException("L'année académique {$y->code} n'est pas encore ouverte.");
        }

        return $y;
    }
}
