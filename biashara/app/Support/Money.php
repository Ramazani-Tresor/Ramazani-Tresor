<?php

namespace App\Support;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

/**
 * Calculs décimaux exacts (aucun float) pour les montants et les quantités.
 *
 * Conventions :
 *  - montants : 2 décimales (DECIMAL(15,2)) ;
 *  - quantités : 3 décimales (DECIMAL(15,3)) ;
 *  - coûts unitaires moyens : 4 décimales (DECIMAL(15,4)).
 */
final class Money
{
    public const MONEY_SCALE = 2;

    public const QTY_SCALE = 3;

    public const COST_SCALE = 4;

    public static function of(mixed $value): BigDecimal
    {
        if ($value instanceof BigDecimal) {
            return $value;
        }
        if ($value === null || $value === '') {
            return BigDecimal::zero();
        }
        if (is_float($value)) {
            // Conversion défensive : on passe par une représentation texte bornée.
            $value = number_format($value, 6, '.', '');
        }

        return BigDecimal::of(str_replace([' ', ','], ['', '.'], (string) $value));
    }

    public static function money(mixed $value): string
    {
        return (string) self::of($value)->toScale(self::MONEY_SCALE, RoundingMode::HALF_UP);
    }

    public static function qty(mixed $value): string
    {
        return (string) self::of($value)->toScale(self::QTY_SCALE, RoundingMode::HALF_UP);
    }

    public static function cost(mixed $value): string
    {
        return (string) self::of($value)->toScale(self::COST_SCALE, RoundingMode::HALF_UP);
    }

    public static function add(mixed ...$values): BigDecimal
    {
        $sum = BigDecimal::zero();
        foreach ($values as $v) {
            $sum = $sum->plus(self::of($v));
        }

        return $sum;
    }

    public static function mul(mixed $a, mixed $b, int $scale = self::MONEY_SCALE): BigDecimal
    {
        return self::of($a)->multipliedBy(self::of($b))->toScale($scale, RoundingMode::HALF_UP);
    }

    public static function div(mixed $a, mixed $b, int $scale = self::COST_SCALE): BigDecimal
    {
        return self::of($a)->dividedBy(self::of($b), $scale, RoundingMode::HALF_UP);
    }

    /** Pourcentage : $base * $rate / 100. */
    public static function percent(mixed $base, mixed $rate): BigDecimal
    {
        return self::of($base)->multipliedBy(self::of($rate))
            ->dividedBy(100, self::MONEY_SCALE, RoundingMode::HALF_UP);
    }

    public static function cmp(mixed $a, mixed $b): int
    {
        return self::of($a)->compareTo(self::of($b));
    }

    public static function min(mixed $a, mixed $b): BigDecimal
    {
        return self::cmp($a, $b) <= 0 ? self::of($a) : self::of($b);
    }

    public static function max(mixed $a, mixed $b): BigDecimal
    {
        return self::cmp($a, $b) >= 0 ? self::of($a) : self::of($b);
    }

    public static function isZero(mixed $v): bool
    {
        return self::of($v)->isZero();
    }

    public static function isPositive(mixed $v): bool
    {
        return self::of($v)->isPositive();
    }

    public static function isNegative(mixed $v): bool
    {
        return self::of($v)->isNegative();
    }

    /** Affichage : 12 500,00 FC */
    public static function format(mixed $value, ?string $currency = null, int $decimals = 2): string
    {
        $currency ??= Settings::get('currency_symbol', 'FC');
        $str = (string) self::of($value)->toScale($decimals, RoundingMode::HALF_UP);
        $negative = str_starts_with($str, '-');
        $str = ltrim($str, '-');
        [$int, $dec] = array_pad(explode('.', $str), 2, '');
        $groups = array_map('strrev', array_reverse(str_split(strrev($int), 3)));
        $int = implode("\u{202F}", $groups);
        $out = ($negative ? '-' : '').$int.($decimals > 0 ? ','.$dec : '');

        return $currency === '' ? $out : $out.' '.$currency;
    }

    public static function formatQty(mixed $value): string
    {
        $str = (string) self::of($value)->toScale(self::QTY_SCALE, RoundingMode::HALF_UP);
        $str = rtrim(rtrim($str, '0'), '.');

        return str_replace('.', ',', $str === '-0' ? '0' : $str);
    }
}
