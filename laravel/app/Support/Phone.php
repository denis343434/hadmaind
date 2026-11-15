<?php

namespace App\Support;

class Phone
{
    /** 8XXXXXXXXXX и XXXXXXXXXX считаются российскими, результат +7XXXXXXXXXX. */
    public static function normalize(?string $raw): string
    {
        $digits = preg_replace('/\D/', '', (string) $raw) ?? '';
        if (strlen($digits) === 11 && $digits[0] === '8') {
            $digits = '7'.substr($digits, 1);
        }
        if (strlen($digits) === 10 && $digits[0] === '9') {
            $digits = '7'.$digits;
        }

        return '+'.$digits;
    }

    public static function isValid(string $normalized): bool
    {
        return (bool) preg_match('/^\+\d{10,15}$/', $normalized);
    }
}
