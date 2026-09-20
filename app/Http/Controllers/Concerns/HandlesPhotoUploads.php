<?php

namespace App\Http\Controllers\Concerns;

use App\Http\Requests\StorePhotoRequest;
use App\Models\Photo;
use App\Services\PhotoStorageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Shared upload orchestration for the Web dashboard and the JSON API.
 *
 * Each file is validated, stored and reported independently: a bad file never
 * fails the whole batch, failures carry a concrete reason, real exceptions are
 * logged, and the client receives a per-file success/failure list.
 */
trait HandlesPhotoUploads
{
    public function uploadPhotos(StorePhotoRequest $request): JsonResponse
    {
        $service = app(PhotoStorageService::class);
        $user = $request->user();
        $album = null;

        if ($request->filled('album_id')) {
            $album = $user->albums()->findOrFail($request->album_id);
        }

        $files = $request->hasFile('photos')
            ? (array) $request->file('photos')
            : ($request->hasFile('photo') ? [$request->file('photo')] : []);

        if ($files === []) {
            return $this->rejectUnreceivedBatch($request);
        }

        $result = $service->storeMany(
            $files,
            $user,
            $album,
            $request->input('alt_texts', []),
            $request->input('keys', []),
            $request->input('tags'),
        );

        $uploaded = collect($result['uploaded'])
            ->map(fn (array $entry) => $this->uploadedPhotoPayload($entry['photo'], $entry['key']))
            ->values()
            ->all();

        $duplicates = collect($result['duplicates'] ?? [])
            ->map(fn (array $entry) => [
                'key' => $entry['key'],
                'original_filename' => $entry['original_filename'],
                'error' => 'This photo is already backed up.',
            ])
            ->values()
            ->all();

        return response()->json([
            'message' => $this->summaryMessage($uploaded, $result['failed'], $duplicates),
            'uploaded' => $uploaded,
            'failed' => $result['failed'],
            'duplicates' => $duplicates,
        ], $uploaded !== [] ? 201 : 200);
    }

    /**
     * Serialize a successfully stored photo for the per-file response.
     */
    protected function uploadedPhotoPayload(Photo $photo, string $key): array
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
    protected function summaryMessage(array $uploaded, array $failed, array $duplicates = []): string
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
     * Handle a request in which PHP never delivered the files — typically the
     * batch exceeded post_max_size and $_FILES was emptied by PHP.
     */
    protected function rejectUnreceivedBatch(Request $request): JsonResponse
    {
        $contentLength = (int) $request->header('Content-Length', 0);
        $postMaxBytes = $this->iniBytes(ini_get('post_max_size') ?: '128M');

        if ($contentLength > $postMaxBytes) {
            return response()->json([
                'message' => 'This batch is too large to upload in one go (it exceeded the server post_max_size limit). Upload fewer photos at a time.',
                'uploaded' => [],
                'failed' => [[
                    'key' => '',
                    'original_filename' => '',
                    'error' => 'Batch too large: the server could not receive the request. Please upload in smaller batches (try about 10 photos at a time).',
                ]],
            ], 413);
        }

        return response()->json([
            'message' => 'No photos were received with this upload.',
            'uploaded' => [],
            'failed' => [[
                'key' => '',
                'original_filename' => '',
                'error' => 'No photo files were received with the request. Please try again.',
            ]],
        ], 422);
    }

    /**
     * Convert a php.ini size value such as "128M" into bytes.
     */
    protected function iniBytes(string $value): int
    {
        $value = trim($value);
        $magnitude = strtolower(substr($value, -1));
        $number = (int) $value;

        return match ($magnitude) {
            'g' => $number * 1024 * 1024 * 1024,
            'm' => $number * 1024 * 1024,
            'k' => $number * 1024,
            default => $number,
        };
    }
}
