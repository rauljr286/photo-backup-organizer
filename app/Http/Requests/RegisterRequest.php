<?php

namespace App\Http\Requests;

use App\HasPhoneNormalization;
use App\Rules\PhoneNumber;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class RegisterRequest extends FormRequest
{
    /**
     * Whether the request supplied the structured name fields (first/last)
     * instead of the single legacy "name" field.
     */
    protected bool $structuredName = false;

    /**
     * Suffixes offered by the registration form's select.
     *
     * @var array<int, string>
     */
    public const SUFFIXES = ['Sr.', 'Jr.', 'II', 'III', 'IV', 'V'];

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['nullable', 'string', 'max:255'],
            'first_name' => ['nullable', 'string', 'max:100'],
            'middle_name' => ['nullable', 'string', 'max:100'],
            'last_name' => ['nullable', 'string', 'max:100'],
            'suffix' => ['nullable', 'string', 'in:'.implode(',', self::SUFFIXES)],
            'email' => ['nullable', 'string', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['nullable', 'string', new PhoneNumber(), 'unique:users,phone'],
            'password' => [
                'required',
                'string',
                Password::min(8)->mixedCase()->numbers()->symbols(),
                'confirmed',
            ],
        ];
    }

    /**
     * Get a descriptive message for each validation failure.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Please enter your name.',
            'name.max' => 'Your name cannot be longer than 255 characters.',
            'first_name.required' => 'Please enter your first name.',
            'first_name.max' => 'Your first name cannot be longer than 100 characters.',
            'middle_name.max' => 'Your middle name cannot be longer than 100 characters.',
            'last_name.required' => 'Please enter your last name.',
            'last_name.max' => 'Your last name cannot be longer than 100 characters.',
            'suffix.in' => 'Please choose a valid name extension.',
            'email.email' => 'Please enter a valid email address.',
            'email.unique' => 'An account with this email already exists. Try logging in instead.',
            'phone.unique' => 'An account with this phone number already exists. Try logging in instead.',
            'password.required' => 'Please choose a password.',
            'password.min' => 'Your password must be at least 8 characters long.',
            'password.mixed' => 'Your password must contain at least one uppercase letter.',
            'password.uppercase' => 'Your password must contain at least one uppercase letter.',
            'password.lowercase' => 'Your password must contain at least one lowercase letter.',
            'password.numbers' => 'Your password must contain at least one number.',
            'password.symbols' => 'Your password must contain at least one special character.',
            'password.confirmed' => 'The password confirmation does not match.',
        ];
    }

    protected function prepareForValidation(): void
    {
        // The web form submits first/middle/last/suffix; the API (and older
        // clients) submit a single "name". Support both by composing the parts
        // into the canonical "name" whenever the structured fields are present.
        $this->structuredName = $this->exists('first_name') || $this->exists('last_name');

        if ($this->structuredName) {
            $first = trim((string) $this->input('first_name'));
            $middle = trim((string) $this->input('middle_name'));
            $last = trim((string) $this->input('last_name'));
            $suffix = trim((string) $this->input('suffix'));

            $this->merge([
                'first_name' => $first,
                'middle_name' => $middle,
                'last_name' => $last,
                'suffix' => $suffix,
                'name' => collect([$first, $middle, $last, $suffix])->filter()->implode(' '),
            ]);
        }

        $this->merge([
            'email' => $this->input('email') ? trim($this->input('email')) : null,
            'phone' => HasPhoneNormalization::normalizePhone($this->input('phone')),
        ]);
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if ($this->structuredName) {
                if (blank($this->input('first_name'))) {
                    $validator->errors()->add('first_name', 'Please enter your first name.');
                }
                if (blank($this->input('last_name'))) {
                    $validator->errors()->add('last_name', 'Please enter your last name.');
                }
            } elseif (blank($this->input('name'))) {
                $validator->errors()->add('name', 'Please enter your name.');
            }

            if (empty($this->email) && empty($this->phone)) {
                $validator->errors()->add('email', 'Provide an email, a mobile number, or both.');
                $validator->errors()->add('phone', 'Provide an email, a mobile number, or both.');
            }
        });
    }
}
