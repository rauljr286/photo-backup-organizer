<?php

namespace Tests\Support;

use App\Contracts\DirectUploadSigner;

/**
 * A signer that hands out a URL without touching a real bucket, so the
 * direct upload flow can be tested without a network connection.
 */
class FakeDirectUploadSigner implements DirectUploadSigner
{
    /**
     * Every URL that was signed.
     *
     * @var array<int, array{path: string, mimeType: string, ttl: int}>
     */
    public array $signed = [];

    /**
     * @return array{url: string, headers: array<string, string>, method: string}
     */
    public function sign(string $path, string $mimeType, int $ttl): array
    {
        $this->signed[] = [
            'path' => $path,
            'mimeType' => $mimeType,
            'ttl' => $ttl,
        ];

        return [
            'url' => 'https://bucket.test/'.ltrim($path, '/').'?signature=fake',
            'headers' => ['Content-Type' => $mimeType],
            'method' => 'PUT',
        ];
    }
}
