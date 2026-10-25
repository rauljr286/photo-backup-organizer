<?php

namespace App\Services;

use App\Contracts\DirectUploadSigner;
use App\Exceptions\DirectUploadException;
use App\Exceptions\DuplicatePhotoException;
use App\Models\Album;
use App\Models\PendingUpload;
use App\Models\Photo;
use App\Models\User;
use App\Support\LocalFileCopy;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Direct uploads: the browser sends the photo straight to the bucket.
 *
 * The flow has two steps. "Prepare" checks the request, reserves the space
 * the photo needs in the quota and hands out a short-lived signed URL.
 * "Complete" is called once the browser uploaded the file: the object is
 * checked, moved to its final place and registered as a photo.
 *
 * The object the browser uploads to is a temporary one (incoming/...) that is
 * removed as soon as the upload is finished, so the signed URL is worthless
 * afterwards. The bucket itself is never made public.
 */
class DirectUploadService
{
    public function __construct(
        protected PhotoStorageService $photos,
        protected DirectUploadSigner $signer,
    ) {}

    /**
     * Prepare a direct upload and hand out the signed URL for it.
     *
     * @return array{upload_id: string, key: string, url: string, headers: array<string, string>, method: string, expires_in: int}
     */
    public function prepare(
        User $user,
        string $filename,
        int $size,
        ?string $mimeType = null,
        ?int $albumId = null,
        ?string $altText = null,
        array|string|null $tags = null,
        bool $private = false,
    ): array {
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        if (! in_array($extension, PhotoStorageService::ALLOWED_EXTENSIONS, true)) {
            throw new DirectUploadException(PhotoStorageService::ERROR_UNSUPPORTED_FORMAT, 422);
        }

        if ($size <= 0) {
            throw new DirectUploadException('The file is empty.', 422);
        }

        $maxMb = (int) config('photobackup.max_upload_mb', 20);

        if ($size > $maxMb * 1024 * 1024) {
            throw new DirectUploadException(PhotoStorageService::tooLargeMessage($maxMb), 422);
        }

        $album = null;

        if ($albumId !== null) {
            $album = $user->albums()->find($albumId);

            if (! $album instanceof Album) {
                throw new DirectUploadException('The album could not be found.', 422);
            }
        }

        // Uploads that were never finished are cleaned up first, so their
        // reservation does not block the quota forever.
        $this->pruneExpired($user);

        if (! $user->hasStorageForWithReservations($size, $this->reservedBytes($user))) {
            throw new DirectUploadException(PhotoStorageService::ERROR_QUOTA, 422);
        }

        $uploadId = (string) Str::uuid();
        $incomingPath = "incoming/{$user->id}/{$uploadId}.{$extension}";

        $pending = PendingUpload::create([
            'id' => $uploadId,
            'user_id' => $user->id,
            'album_id' => $album?->id,
            'is_private' => $private,
            'original_filename' => $filename,
            'mime_type' => $mimeType,
            'size_bytes' => $size,
            'incoming_path' => $incomingPath,
            'alt_text' => $altText,
            'tags' => $this->photos->normalizeTags($tags),
            'status' => PendingUpload::STATUS_PENDING,
            'expires_at' => now()->addMinutes($this->pendingTtlMinutes()),
        ]);

        $ttl = $this->urlTtl();

        $signed = $this->signer->sign($incomingPath, $this->mimeFor($extension), $ttl);

        return [
            'upload_id' => $pending->id,
            'key' => $incomingPath,
            'url' => $signed['url'],
            'headers' => $signed['headers'],
            'method' => $signed['method'] ?? 'PUT',
            'expires_in' => $ttl,
        ];
    }

    /**
     * Finish a direct upload: the file that was uploaded straight to the
     * bucket is checked, moved to its final place and registered as a photo.
     *
     * @return array{uploaded: array<int, array{photo: Photo, key: string}>, failed: array<int, array{key: string, original_filename: string, error: string}>, duplicates: array<int, array{key: string, original_filename: string, error: string}>}
     */
    public function complete(User $user, string $uploadId): array
    {
        $pending = PendingUpload::query()
            ->where('user_id', $user->id)
            ->where('status', PendingUpload::STATUS_PENDING)
            ->whereKey($uploadId)
            ->first();

        if (! $pending instanceof PendingUpload) {
            throw new DirectUploadException('This upload could not be found.', 404);
        }

        if ($pending->isExpired()) {
            $this->discard($pending);

            throw new DirectUploadException('This upload has expired. Please upload the photo again.', 410);
        }

        $disk = $this->photos->photoDisk();

        if (! Storage::disk($disk)->exists($pending->incoming_path)) {
            $this->discard($pending);

            throw new DirectUploadException('The photo was not received. Please upload it again.', 422);
        }

        $extension = strtolower(pathinfo($pending->incoming_path, PATHINFO_EXTENSION));
        $finalPath = $this->photos->finalPathFor($user, $extension);
        $metadata = [];

        try {
            $this->storeFinalCopy($disk, $pending, $user, $finalPath, $metadata);
        } catch (DuplicatePhotoException $e) {
            $this->discard($pending);

            return [
                'uploaded' => [],
                'failed' => [],
                'duplicates' => [[
                    'key' => '',
                    'original_filename' => $pending->original_filename,
                    'error' => $e->getMessage(),
                ]],
            ];
        } catch (DirectUploadException $e) {
            Storage::disk($disk)->delete($finalPath);
            $this->discard($pending);

            // A photo the server refuses is reported like the classic upload
            // does: the reason is kept with the file, the request itself was
            // fine.
            if ($e->status() === 422) {
                return [
                    'uploaded' => [],
                    'failed' => [[
                        'key' => '',
                        'original_filename' => $pending->original_filename,
                        'error' => $e->getMessage(),
                    ]],
                    'duplicates' => [],
                ];
            }

            throw $e;
        }

        $album = $pending->album_id !== null ? $user->albums()->find($pending->album_id) : null;

        try {
            $photo = $this->photos->registerStoredFile(
                path: $finalPath,
                user: $user,
                album: $album,
                altText: $pending->alt_text,
                private: (bool) $pending->is_private,
                originalFilename: $pending->original_filename,
                sizeBytes: $metadata['size_bytes'] ?? $pending->size_bytes,
                mimeType: $metadata['mime_type'] ?? $pending->mime_type,
                fileHash: $metadata['file_hash'] ?? null,
                takenAt: $metadata['taken_at'] ?? null,
                tags: $pending->tags,
            );
        } catch (Throwable $e) {
            Storage::disk($disk)->delete($finalPath);
            $this->discard($pending);

            throw $e;
        }

        // The temporary object is not needed anymore; the signed URL that
        // pointed at it is worthless from here on.
        Storage::disk($disk)->delete($pending->incoming_path);
        $pending->update(['status' => PendingUpload::STATUS_COMPLETED]);

        return [
            'uploaded' => [['photo' => $photo, 'key' => '']],
            'failed' => [],
            'duplicates' => [],
        ];
    }

    /**
     * Remove the uploads that were never finished: the temporary objects in
     * the bucket and the rows that belong to them.
     *
     * @return int the number of uploads that were removed
     */
    public function pruneExpired(?User $user = null): int
    {
        $disk = $this->photos->photoDisk();

        $expired = PendingUpload::query()
            ->when($user !== null, fn ($query) => $query->where('user_id', $user->id))
            ->where('status', PendingUpload::STATUS_PENDING)
            ->where('expires_at', '<=', now())
            ->get();

        foreach ($expired as $pending) {
            Storage::disk($disk)->delete($pending->incoming_path);
            $pending->delete();
        }

        // The rows of finished uploads are only bookkeeping; they are removed
        // a day later.
        PendingUpload::query()
            ->whereIn('status', [PendingUpload::STATUS_COMPLETED, PendingUpload::STATUS_FAILED])
            ->where('updated_at', '<=', now()->subDay())
            ->delete();

        return $expired->count();
    }

    /**
     * Copy the uploaded object to its final place and collect the metadata of
     * the file while it is read.
     *
     * @param  array<string, mixed>  $metadata
     */
    protected function storeFinalCopy(string $disk, PendingUpload $pending, User $user, string $finalPath, array &$metadata): void
    {
        LocalFileCopy::withLocalCopy(
            $disk,
            $pending->incoming_path,
            function (string $local) use ($disk, $pending, $user, $finalPath, &$metadata): void {
                $info = @getimagesize($local);

                if ($info === false) {
                    throw new DirectUploadException(PhotoStorageService::ERROR_CORRUPTED, 422);
                }

                $size = (int) filesize($local);
                $maxMb = (int) config('photobackup.max_upload_mb', 20);

                if ($size > $maxMb * 1024 * 1024) {
                    throw new DirectUploadException(PhotoStorageService::tooLargeMessage($maxMb), 422);
                }

                $hash = $this->photos->hashFile($local);

                if ($this->photos->findDuplicate($user, $hash, (bool) $pending->is_private) !== null) {
                    throw new DuplicatePhotoException(PhotoStorageService::ERROR_DUPLICATE);
                }

                if (! $user->hasStorageForWithReservations($size, $this->reservedBytes($user, $pending->id))) {
                    throw new DirectUploadException(PhotoStorageService::ERROR_QUOTA, 422);
                }

                $stream = @fopen($local, 'rb');

                if (! is_resource($stream)) {
                    throw new DirectUploadException('The photo could not be read.', 422);
                }

                $stored = Storage::disk($disk)->put($finalPath, $stream);

                fclose($stream);

                if ($stored === false) {
                    throw new DirectUploadException('The photo could not be stored.', 500);
                }

                $metadata = [
                    'size_bytes' => $size,
                    'file_hash' => $hash,
                    'mime_type' => $this->detectMimeType($local, $info),
                    'taken_at' => $this->photos->readTakenAt($local),
                ];
            },
        );
    }

    /**
     * Throw away an upload that will not be finished: the temporary object is
     * removed and the row is marked as failed.
     */
    protected function discard(PendingUpload $pending): void
    {
        Storage::disk($this->photos->photoDisk())->delete($pending->incoming_path);

        $pending->update(['status' => PendingUpload::STATUS_FAILED]);
    }

    /**
     * The space that is spoken for by the uploads that are on their way to the
     * bucket.
     */
    protected function reservedBytes(User $user, ?string $exceptId = null): int
    {
        return (int) PendingUpload::query()
            ->where('user_id', $user->id)
            ->where('status', PendingUpload::STATUS_PENDING)
            ->where('expires_at', '>', now())
            ->when($exceptId !== null, fn ($query) => $query->where('id', '!=', $exceptId))
            ->sum('size_bytes');
    }

    /**
     * The mime type of the given file extension.
     */
    protected function mimeFor(string $extension): string
    {
        return match (strtolower($extension)) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'webp' => 'image/webp',
            'gif' => 'image/gif',
            default => 'application/octet-stream',
        };
    }

    /**
     * The mime type of the uploaded file. The type the browser announced is
     * never trusted: it is read from the content of the file.
     *
     * @param  array<int|int, mixed>  $info
     */
    protected function detectMimeType(string $path, array $info): ?string
    {
        if (function_exists('mime_content_type')) {
            $mime = @mime_content_type($path);

            if (is_string($mime) && $mime !== '') {
                return $mime;
            }
        }

        return match ((int) ($info[2] ?? 0)) {
            IMAGETYPE_JPEG => 'image/jpeg',
            IMAGETYPE_PNG => 'image/png',
            IMAGETYPE_GIF => 'image/gif',
            IMAGETYPE_WEBP => 'image/webp',
            default => null,
        };
    }

    /**
     * How long the signed upload URL stays valid.
     */
    protected function urlTtl(): int
    {
        return max(30, (int) config('photobackup.direct_upload_url_ttl', 300));
    }

    /**
     * How long the browser has to finish an upload.
     */
    protected function pendingTtlMinutes(): int
    {
        return max(1, (int) config('photobackup.pending_ttl_minutes', 60));
    }
}
