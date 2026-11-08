<?php

use App\Support\Labels;
use App\Support\Money;
use App\Support\Settings;

if (! function_exists('money')) {
    /** Montant formaté avec sa devise : 1 250,00 USD. Jamais d'addition entre devises. */
    function money(mixed $value, ?string $currency = null): string
    {
        return Money::format($value, $currency);
    }
}

if (! function_exists('setting')) {
    function setting(string $key, mixed $default = null): mixed
    {
        return Settings::get($key, $default);
    }
}

if (! function_exists('can_do')) {
    /** Permission de l'utilisateur connecté (dans au moins un périmètre). */
    function can_do(string $permission): bool
    {
        return (bool) auth()->user()?->hasPermission($permission);
    }
}

if (! function_exists('status_label')) {
    function status_label(string $group, ?string $status): string
    {
        return Labels::status($group, $status);
    }
}
