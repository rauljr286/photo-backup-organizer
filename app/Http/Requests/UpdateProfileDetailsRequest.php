<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

class UpdateProfileDetailsRequest extends FormRequest
{
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
            'gender' => ['nullable', 'in:'.implode(',', User::GENDERS)],
            'birthdate' => ['nullable', 'date', 'before:today', 'after:-120 years'],
            'country' => ['nullable', 'string', 'max:100'],
            'province' => ['nullable', 'string', 'max:100'],
            'city_municipality' => ['nullable', 'string', 'max:100'],
            'barangay' => ['nullable', 'string', 'max:100'],
            'street' => ['nullable', 'string', 'max:255'],
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
            'gender.in' => 'Please choose a valid gender.',
            'birthdate.date' => 'Please enter a valid birthdate.',
            'birthdate.before' => 'Your birthdate must be in the past.',
            'birthdate.after' => 'Your birthdate cannot be more than 120 years ago.',
            'country.max' => 'Country cannot be longer than 100 characters.',
            'province.max' => 'Province cannot be longer than 100 characters.',
            'city_municipality.max' => 'City / Municipality cannot be longer than 100 characters.',
            'barangay.max' => 'Barangay cannot be longer than 100 characters.',
            'street.max' => 'Street cannot be longer than 255 characters.',
        ];
    }
}
