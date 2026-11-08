<?php

namespace App\Support;

use Illuminate\Database\QueryException;

final class DbErrors
{
    /** Violation de contrainte d'unicité (MySQL 23000/1062, PostgreSQL 23505, SQLite 19). */
    public static function isUniqueViolation(QueryException $e): bool
    {
        $code = (string) $e->getCode();
        $driverCode = (string) ($e->errorInfo[1] ?? '');

        return in_array($code, ['23000', '23505'], true)
            || $driverCode === '1062'
            || str_contains(strtolower($e->getMessage()), 'unique constraint');
    }
}
