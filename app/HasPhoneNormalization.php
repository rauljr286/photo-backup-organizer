<?php

namespace App;

trait HasPhoneNormalization
{
    /**
     * Join a dial code with the local number the user typed. Numbers that
     * already start with "+" are treated as fully international.
     */
    public static function combinePhone(?string $prefix, ?string $phone): ?string
    {
        $phone = trim((string) $phone);

        if ($phone === '') {
            return null;
        }

        if (str_starts_with($phone, '+')) {
            return $phone;
        }

        $prefix = trim((string) $prefix);

        if ($prefix === '') {
            return $phone;
        }

        $digits = preg_replace('/[^0-9]/', '', $phone);

        // Drop the national trunk prefix (the leading 0) before adding the
        // country code, so 0917... becomes +63917...
        $digits = ltrim($digits, '0');

        return $digits === '' ? null : $prefix.$digits;
    }

    public static function normalizePhone(?string $phone): ?string
    {
        if (empty($phone)) {
            return null;
        }

        $phone = trim($phone);
        $hasPlus = str_starts_with($phone, '+');
        $digits = preg_replace('/[^0-9]/', '', $phone);

        if (empty($digits)) {
            return $phone;
        }

        // Convert +639XXXXXXXXX to 09XXXXXXXXX.
        if (str_starts_with($digits, '63') && strlen($digits) === 12) {
            return '0'.substr($digits, 2);
        }

        if (str_starts_with($digits, '9') && strlen($digits) === 10 && str_starts_with($phone, '+63')) {
            return '0'.$digits;
        }

        // Keep international numbers in one canonical form so the same number
        // always matches at login, regardless of spaces or punctuation.
        return $hasPlus ? '+'.$digits : $digits;
    }
}
