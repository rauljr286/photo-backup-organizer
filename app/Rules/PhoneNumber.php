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
            $fail('Please enter a valid Philippine mobile number.');

            return;
        }

        $normalized = preg_replace('/[^0-9+]/', '', $value);

        // Accept 09XXXXXXXXX (11 digits starting with 09) or +639XXXXXXXXX (12 digits after +63)
        if (preg_match('/^09\d{9}$/', $normalized)) {
            return;
        }

        if (preg_match('/^\+639\d{9}$/', $normalized)) {
            return;
        }

        $fail('Please enter a valid Philippine mobile number.');
    }
}
