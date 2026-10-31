<?php

use App\Support\Money;
use App\Support\Settings;

if (! function_exists('money')) {
    /** Montant formaté en devise principale : 12 500,00 FC */
    function money(mixed $value, ?string $currency = null): string
    {
        return Money::format($value, $currency);
    }
}

if (! function_exists('qty')) {
    function qty(mixed $value): string
    {
        return Money::formatQty($value);
    }
}

if (! function_exists('setting')) {
    function setting(string $key, mixed $default = null): mixed
    {
        return Settings::get($key, $default);
    }
}

if (! function_exists('can_do')) {
    function can_do(string $permission): bool
    {
        return (bool) auth()->user()?->hasPermission($permission);
    }
}

if (! function_exists('report_cell')) {
    /** Formatage d'une cellule de rapport selon son échelle : 2 décimales = montant, 3 = quantité. */
    function report_cell(mixed $value): string
    {
        $v = (string) $value;
        if (preg_match('/^-?\d+\.\d{2}$/', $v)) {
            return Money::format($v, '');
        }
        if (preg_match('/^-?\d+\.\d{3}$/', $v)) {
            return Money::formatQty($v);
        }

        return preg_match('/^-?\d+\.\d+$/', $v) ? str_replace('.', ',', $v) : $v;
    }
}
