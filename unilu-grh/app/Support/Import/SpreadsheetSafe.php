<?php

namespace App\Support\Import;

/** Neutralise les formules dans les cellules exportées (injection de formules Excel/CSV). */
final class SpreadsheetSafe
{
    public static function cell(string $v): string
    {
        return $v !== '' && in_array($v[0], ['=', '+', '-', '@', "\t", "\r"], true) && ! is_numeric(str_replace([' ', "\u{202F}", ','], ['', '', '.'], $v)) ? "'".$v : $v;
    }
}
