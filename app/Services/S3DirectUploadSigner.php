<?php

namespace App\Services;

use App\Contracts\DirectUploadSigner;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Creates the presigned upload URLs for the bucket that holds the photos.
 *
 * The URL only grants access to one object for a few minutes, and the bucket
 * itself is never made public: the photos are served through the application.
 */
class S3DirectUploadSigner implements DirectUploadSigner
{
    /**
     * @return array{url: string, headers: array<string, string>, method: string}
     */
    public function sign(string $path, string $mimeType, int $ttl): array
    {
        $disk = Storage::disk((string) config('photobackup.photo_disk', 'public'));

        $result = $disk->temporaryUploadUrl($path, now()->addSeconds(max(1, $ttl)), [
            'ContentType' => $mimeType,
        ]);

        $url = is_array($result) ? (string) ($result['url'] ?? '') : (string) $result;
        $headers = is_array($result) ? ($result['headers'] ?? []) : [];

        if ($url === '') {
            throw new RuntimeException('The storage bucket did not return an upload URL.');
        }

        return [
            'url' => $url,
            'headers' => $this->browserHeaders($headers, $mimeType),
            'method' => 'PUT',
        ];
    }

    /**
     * The headers the browser has to send along with the file.
     *
     * Only the content type and the x-amz-* headers the signature covers are
     * kept. Everything else (the host, the content length, ...) is set by the
     * browser itself and would break the signature.
     *
     * @param  array<string, mixed>  $headers
     * @return array<string, string>
     */
    protected function browserHeaders(array $headers, string $mimeType): array
    {
        $result = [];

        foreach ($headers as $name => $value) {
            $name = (string) $name;

            if (is_array($value)) {
                $value = (string) reset($value);
            }

            $lower = strtolower($name);

            if ($lower === 'content-type' || str_starts_with($lower, 'x-amz-')) {
                $result[$name] = (string) $value;
            }
        }

        $result['Content-Type'] = $mimeType;

        return $result;
    }
}
