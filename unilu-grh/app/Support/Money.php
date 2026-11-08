<?php

namespace App\Support;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

/**
 * Arithmétique décimale exacte (aucun nombre flottant) pour montants et pourcentages.
 * Montants : 2 décimales, arrondi « demi supérieur ». Un montant n'a de sens qu'avec sa devise :
 * cette classe ne convertit jamais implicitement d'une devise à une autre.
 */
final class Money
{
    public const SCALE = 2;

    public static function of(mixed $value): BigDecimal
    {
        if ($value instanceof BigDecimal) {
            return $value;
        }
        if ($value === null || $value === '') {
            return BigDecimal::zero();
        }
        if (is_float($value)) {
            $value = number_format($value, 6, '.', '');
        }

        return BigDecimal::of(str_replace([' ', "\u{202F}", "\u{00A0}", ','], ['', '', '', '.'], trim((string) $value)));
    }

    public static function money(mixed $value): string
    {
        return (string) self::of($value)->toScale(self::SCALE, RoundingMode::HALF_UP);
    }

    public static function add(mixed ...$values): BigDecimal
    {
        $sum = BigDecimal::zero();
        foreach ($values as $v) {
            $sum = $sum->plus(self::of($v));
        }

        return $sum;
    }

    /** Somme d'une liste, renvoyée en chaîne à 2 décimales. */
    public static function sum(array $values): string
    {
        return self::money(self::add(...$values));
    }

    public static function sub(mixed $a, mixed $b): BigDecimal
    {
        return self::of($a)->minus(self::of($b));
    }

    public static function mul(mixed $a, mixed $b, int $scale = self::SCALE): BigDecimal
    {
        return self::of($a)->multipliedBy(self::of($b))->toScale($scale, RoundingMode::HALF_UP);
    }

    public static function percentOf(mixed $base, mixed $percent): BigDecimal
    {
        return self::of($base)->multipliedBy(self::of($percent))->dividedBy(100, self::SCALE, RoundingMode::HALF_UP);
    }

    /** Ratio en pourcentage (1 décimale), 0 si le dénominateur est nul. */
    public static function ratio(mixed $part, mixed $whole): string
    {
        if (self::of($whole)->isZero()) {
            return '0.0';
        }

        return (string) self::of($part)->multipliedBy(100)->dividedBy(self::of($whole), 1, RoundingMode::HALF_UP);
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

    public static function isPositive(mixed $v): bool
    {
        return self::of($v)->isPositive();
    }

    public static function isZero(mixed $v): bool
    {
        return self::of($v)->isZero();
    }

    public static function isNegative(mixed $v): bool
    {
        return self::of($v)->isNegative();
    }

    /** 1 250,50 USD */
    public static function format(mixed $value, ?string $currency = null): string
    {
        $str = self::money($value);
        $neg = str_starts_with($str, '-');
        [$int, $dec] = explode('.', ltrim($str, '-'));
        $groups = array_map('strrev', array_reverse(str_split(strrev($int), 3)));
        $out = ($neg ? '-' : '').implode("\u{202F}", $groups).','.$dec;

        return $currency ? $out.' '.$currency : $out;
    }
}
