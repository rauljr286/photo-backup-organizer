<?php

namespace App;

trait HasPhoneNormalization
{
    public static function normalizePhone(?string $phone): ?string
    {
        if (empty($phone)) {
            return null;
        }

        $phone = trim($phone);
        $digits = preg_replace('/[^0-9]/', '', $phone);

        if (empty($digits)) {
            return $phone;
        }

        // Convert +639XXXXXXXXX to 639XXXXXXXXX or 09XXXXXXXXX to 09XXXXXXXXX
        if (str_starts_with($digits, '63') && strlen($digits) === 12) {
            return '0'.substr($digits, 2);
        }

        if (str_starts_with($digits, '9') && strlen($digits) === 10 && str_starts_with($phone, '+63')) {
            return '0'.$digits;
        }

        return $phone;
    }
}
