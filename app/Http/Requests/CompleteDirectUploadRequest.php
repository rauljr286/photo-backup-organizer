<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * The request that finishes a direct upload: the browser tells the server
 * which prepared upload it just finished.
 */
class CompleteDirectUploadRequest extends FormRequest
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
            'upload_id' => ['required', 'string', 'max:64'],
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
            'upload_id.required' => 'This upload could not be found.',
        ];
    }
}
