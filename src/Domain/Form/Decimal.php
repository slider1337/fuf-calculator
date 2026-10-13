<?php

declare(strict_types=1);
namespace App\Domain\Form;

/** Exact fixed point values, at most six decimals, never converted through float. */
final class Decimal
{
    public static function normalize(mixed $value): ?string
    {
        if (!is_string($value) && !is_int($value)) { return null; }
        $value = (string) $value;
        if (!preg_match('/^-?\d{1,12}(?:\.\d{1,6})?$/D', $value)) { return null; }
        [$whole, $fraction] = array_pad(explode('.', $value), 2, '');
        $negative = str_starts_with($whole, '-');
        $whole = ltrim($whole, '-0'); $whole = $whole === '' ? '0' : $whole;
        $fraction = rtrim($fraction, '0');
        return ($negative && ($whole !== '0' || $fraction !== '') ? '-' : '') . $whole . ($fraction !== '' ? '.' . $fraction : '');
    }
    public static function compare(string $a, string $b): int
    {
        $scaled = static function (string $v): int {
            [$whole, $fraction] = array_pad(explode('.', ltrim($v, '-')), 2, '');
            return ((int) $whole * 1000000 + (int) str_pad($fraction, 6, '0')) * (str_starts_with($v, '-') ? -1 : 1);
        };
        return $scaled($a) <=> $scaled($b);
    }
}
