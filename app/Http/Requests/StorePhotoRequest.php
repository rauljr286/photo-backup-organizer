<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePhotoRequest extends FormRequest
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
     * Only structural rules live here. Per-file validation (size, type,
     * corruption) is performed per file in the controller/service so a single
     * bad file never fails the entire batch — it is reported alongside the
     * successful files instead.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'photos' => ['required_without:photo', 'array', 'max:50'],
            'photo' => ['required_without:photos', 'file'],
            'keys' => ['sometimes', 'array'],
            'keys.*' => ['nullable', 'string', 'max:255'],
            'album_id' => ['sometimes', 'nullable', 'integer', 'exists:albums,id'],
            'alt_texts' => ['sometimes', 'array'],
            'alt_texts.*' => ['nullable', 'string', 'max:500'],
            'tags' => ['sometimes', 'nullable', 'string', 'max:255'],
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
            'photos.required_without' => 'Please choose at least one photo to upload.',
            'photo.required_without' => 'Please choose at least one photo to upload.',
            'photos.max' => 'Upload failed: too many files in one request (max 50).',
        ];
    }
}
