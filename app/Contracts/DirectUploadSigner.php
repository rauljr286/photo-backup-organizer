<?php

namespace App\Contracts;

/**
 * Hands out a signed URL the browser can upload a file to. The bucket itself
 * stays private: only the (short-lived) signed URL grants access.
 */
interface DirectUploadSigner
{
    /**
     * Create a signed upload URL for the given path.
     *
     * @return array{url: string, headers: array<string, string>, method: string}
     */
    public function sign(string $path, string $mimeType, int $ttl): array;
}
