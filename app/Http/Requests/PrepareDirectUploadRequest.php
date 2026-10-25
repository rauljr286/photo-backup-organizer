<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * The request that prepares a direct upload: the browser describes the file
 * it is about to send and gets a signed URL to send it to.
 */
class PrepareDirectUploadRequest extends FormRequest
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
     * Only structural rules live here. The size, the format and the storage
     * quota are checked by the upload service, so a refused upload is
     * reported with the same reason as in the classic upload flow.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'filename' => ['required', 'string', 'max:255'],
            'size' => ['required', 'integer', 'min:1'],
            'mime_type' => ['sometimes', 'nullable', 'string', 'max:100'],
            'album_id' => ['sometimes', 'nullable', 'integer'],
            'alt_text' => ['sometimes', 'nullable', 'string', 'max:500'],
            'tags' => ['sometimes', 'nullable', 'string', 'max:255'],
            'private' => ['sometimes', 'boolean'],
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
            'filename.required' => 'Please choose a photo to upload.',
            'size.required' => 'Please choose a photo to upload.',
        ];
    }
}
