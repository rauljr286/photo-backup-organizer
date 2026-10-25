<?php

namespace App\Support;

use App\Models\Photo;

/**
 * The JSON body both upload flows answer with.
 *
 * The classic server-side upload and the direct (presigned) upload report
 * their results in exactly the same way, so the browser renders them with
 * the same code.
 */
class PhotoUploadResponse
{
    /**
     * Serialize a successfully stored photo for the per-file response.
     *
     * @return array<string, mixed>
     */
    public static function photoPayload(Photo $photo, string $key = ''): array
    {
        return [
            'key' => $key,
            'id' => $photo->id,
            'url' => $photo->url(),
            'thumbnail_url' => $photo->thumbnailUrl(),
            'description' => $photo->description(),
            'original_filename' => $photo->original_filename,
            'size_bytes' => $photo->size_bytes,
            'mime_type' => $photo->mime_type,
            'taken_at' => $photo->taken_at?->toISOString(),
            'tags' => $photo->tags ?? [],
            'backed_up' => $photo->isBackedUp(),
        ];
    }

    /**
     * Human-readable summary of a batch, e.g. "18 of 20 photos uploaded
     * successfully, 2 failed."
     *
     * @param  array<int, array<string, mixed>>  $uploaded
     * @param  array<int, array{key: string, original_filename: string, error: string}>  $failed
     * @param  array<int, array{key: string, original_filename: string, error: string}>  $duplicates
     */
    public static function summaryMessage(array $uploaded, array $failed, array $duplicates = []): string
    {
        $ok = count($uploaded);
        $bad = count($failed);
        $dup = count($duplicates);
        $total = $ok + $bad + $dup;

        if ($ok === 0 && $total >= 1) {
            return $dup === $total
                ? ($total === 1 ? 'This photo is already backed up.' : "{$dup} photos are already backed up.")
                : 'Upload failed: '.($failed[0]['error'] ?? 'unknown error.');
        }

        $parts = [];

        if ($bad === 0) {
            $parts[] = $total === 1
                ? '1 photo uploaded successfully.'
                : $ok.' photo(s) uploaded successfully.';
        } else {
            $parts[] = "{$ok} of {$total} photos uploaded successfully, {$bad} failed.";
        }

        if ($dup > 0) {
            $parts[] = $dup === 1 ? '1 duplicate already backed up.' : $dup.' duplicates already backed up.';
        }

        return implode(' ', $parts);
    }

    /**
     * The full body of an upload response.
     *
     * @param  array<int, array<string, mixed>>  $uploaded
     * @param  array<int, array{key: string, original_filename: string, error: string}>  $failed
     * @param  array<int, array{key: string, original_filename: string, error: string}>  $duplicates
     * @return array{message: string, uploaded: array<int, array<string, mixed>>, failed: array<int, array<string, string>>, duplicates: array<int, array<string, string>>}
     */
    public static function make(array $uploaded, array $failed, array $duplicates = []): array
    {
        return [
            'message' => self::summaryMessage($uploaded, $failed, $duplicates),
            'uploaded' => $uploaded,
            'failed' => $failed,
            'duplicates' => $duplicates,
        ];
    }
}
