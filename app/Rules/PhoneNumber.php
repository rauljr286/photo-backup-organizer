<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

class PhoneNumber implements ValidationRule
{
    /**
     * Run the validation rule.
     *
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || $value === '') {
            $fail('Please enter a valid mobile number.');

            return;
        }

        $normalized = preg_replace('/[^0-9+]/', '', $value);

        // Accept local or international numbers with an optional leading "+".
        // E.164 numbers are at most 15 digits long.
        if (preg_match('/^\+?[0-9]{7,15}$/', $normalized)) {
            return;
        }

        $fail('Please enter a valid mobile number.');
    }
}
