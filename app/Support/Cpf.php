<?php

namespace App\Support;

final class Cpf
{
    public static function digits(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (! is_scalar($value) && ! $value instanceof \Stringable) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', (string) $value) ?? '';

        return $digits === '' ? null : $digits;
    }

    public static function format(mixed $value): ?string
    {
        $digits = self::digits($value);

        if ($digits === null || strlen($digits) !== 11) {
            return $digits;
        }

        return substr($digits, 0, 3).'.'
            .substr($digits, 3, 3).'.'
            .substr($digits, 6, 3).'-'
            .substr($digits, 9, 2);
    }
}
