<?php

namespace App\Services;

use App\Models\Album;
use App\Models\Photo;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;
use ZipArchive;

/**
 * Owns every piece of photo upload / backup / retrieval logic.
 *
 * Controllers only marshal HTTP input and call into this service; no business
 * logic lives in controllers or models.
 */
class PhotoStorageService
{
    /**
     * Local disk that stores the user-facing photo originals (public web access).
     */
    protected const LOCAL_DISK = 'public';

    /**
     * Longest side (in pixels) of generated thumbnails.
     */
    protected const THUMBNAIL_MAX_DIMENSION = 400;

    /**
     * File extensions accepted for photo uploads.
     */
    public const ALLOWED_EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp', 'gif'];

    /**
     * Name of the "local mirror" disk used to simulate cloud backup when no
     * real cloud disk is configured. Set PHOTOS_LOCAL_MIRROR_DISK in .env.
     */
    protected string $fallbackCloudDisk;

    public function __construct()
    {
        $this->fallbackCloudDisk = config('photobackup.local_mirror_disk', 'local');
    }

    /**
     * Validate an individual uploaded file.
     *
     * Returns null when the file is acceptable, otherwise a human-readable
     * reason the caller can surface to the client. Validation runs per file so
     * one bad upload never fails the rest of the batch.
     */
    public function validateUpload(UploadedFile $file): ?string
    {
        if (! $file->isValid()) {
            return match ($file->getError()) {
                UPLOAD_ERR_INI_SIZE => 'File too large: the server rejected it because it exceeds the configured upload_max_filesize.',
                UPLOAD_ERR_PARTIAL => 'The file upload was interrupted, so the file is incomplete/corrupted.',
                UPLOAD_ERR_NO_FILE => 'No file was received for this upload slot.',
                UPLOAD_ERR_EXTENSION => 'The upload was stopped because of a server configuration.',
                default => 'The file could not be uploaded (error code '.$file->getError().').',
            };
        }

        $maxMb = (int) config('photobackup.max_upload_mb', 20);

        if ($file->getSize() > $maxMb * 1024 * 1024) {
            return "File too large: max {$maxMb}MB per photo.";
        }

        $extension = strtolower($file->getClientOriginalExtension());

        if (! in_array($extension, self::ALLOWED_EXTENSIONS, true)) {
            return 'Unsupported format: only JPG, PNG, WebP and GIF images are allowed.';
        }

        $info = @getimagesize($file->getPathname());

        if ($info === false) {
            return 'The file is corrupted or is not a valid image.';
        }

        return null;
    }

    /**
     * Compute a stable content hash for a photo file used to detect duplicates
     * within a single user's library.
     */
    public function hashFile(string $absolutePath): string
    {
        return hash_file('sha256', $absolutePath) ?: '';
    }

    /**
     * Whether the user already owns a photo with the given content hash.
     * Trashed photos count so a re-upload is not silently duplicated while the
     * original is merely awaiting permanent deletion.
     */
    public function isDuplicateFor(User $user, string $fileHash): bool
    {
        if ($fileHash === '') {
            return false;
        }

        return $user->photos()->withTrashed()->where('file_hash', $fileHash)->exists();
    }

    /**
     * Store one uploaded photo for the user and return the created model.
     */
    public function store(UploadedFile $file, User $user, ?string $altText = null, ?Album $album = null): Photo
    {
        $extension = strtolower($file->getClientOriginalExtension());

        if (! in_array($extension, ['jpg', 'jpeg', 'png', 'webp', 'gif'], true)) {
            $extension = mb_strtolower(pathinfo($file->getClientOriginalName(), PATHINFO_EXTENSION));
        }

        $storedName = Str::uuid()->toString().'.'.$extension;
        $path = "photos/{$user->id}/{$storedName}";

        $file->storeAs('photos/'.$user->id, $storedName, self::LOCAL_DISK);

        $absolutePath = Storage::disk(self::LOCAL_DISK)->path($path);
        $thumbnailPath = $this->makeThumbnail($absolutePath, strtolower($extension));

        $photo = new Photo([
            'user_id' => $user->id,
            'file_path' => $path,
            'thumbnail_path' => $thumbnailPath,
            'original_filename' => $file->getClientOriginalName(),
            'alt_text' => filled($altText) ? $altText : null,
            'file_hash' => $this->hashFile($absolutePath),
            'taken_at' => $this->extractTakenAt($absolutePath),
            'size_bytes' => $file->getSize(),
            'mime_type' => $file->getMimeType(),
        ]);

        $photo->save();

        if ($album !== null) {
            $album->photos()->attach($photo);
        }

        return $photo;
    }

    /**
     * Generate a JPEG thumbnail for the stored original using the GD extension
     * and persist it next to the original in the same user folder.
     *
     * Thumbnails are a pure optimisation — any failure (unreadable file, GD
     * missing, transient disk error, encode failure) returns null so uploads
     * always succeed and the grid transparently falls back to the full-size
     * image. Generation can therefore never break an upload.
     */
    protected function makeThumbnail(string $originalPath, string $extension): ?string
    {
        if (! extension_loaded('gd')) {
            return null;
        }

        $info = @getimagesize($originalPath);

        if ($info === false) {
            return null;
        }

        [$width, $height, $type] = $info;

        if ($width <= 0 || $height <= 0) {
            return null;
        }

        $source = match ($type) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($originalPath),
            IMAGETYPE_PNG => @imagecreatefrompng($originalPath),
            IMAGETYPE_WEBP => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($originalPath) : null,
            IMAGETYPE_GIF => @imagecreatefromgif($originalPath),
            default => null,
        };

        if (! $source instanceof \GdImage) {
            return null;
        }

        try {
            $max = self::THUMBNAIL_MAX_DIMENSION;

            $scale = min(1.0, $max / max($width, $height));
            $thumbWidth = max(1, (int) round($width * $scale));
            $thumbHeight = max(1, (int) round($height * $scale));

            $thumb = imagecreatetruecolor($thumbWidth, $thumbHeight);

            if (! $thumb instanceof \GdImage) {
                return null;
            }

            if (in_array($type, [IMAGETYPE_PNG, IMAGETYPE_WEBP, IMAGETYPE_GIF], true)) {
                imagealphablending($thumb, false);
                imagesavealpha($thumb, true);
                imagefill($thumb, 0, 0, imagecolorallocatealpha($thumb, 0, 0, 0, 127));
            }

            imagecopyresampled($thumb, $source, 0, 0, 0, 0, $thumbWidth, $thumbHeight, $width, $height);

            $base = pathinfo($originalPath, PATHINFO_FILENAME);
            $thumbName = 'thumb_'.$base.'.jpg';
            $absoluteThumb = dirname($originalPath).DIRECTORY_SEPARATOR.$thumbName;

            if (! @imagejpeg($thumb, $absoluteThumb, 82)) {
                return null;
            }

            $relative = Str::after($absoluteThumb, Storage::disk(self::LOCAL_DISK)->path(''));

            return ltrim(str_replace('\\', '/', $relative), '/');
        } finally {
            imagedestroy($source);

            if (isset($thumb) && $thumb instanceof \GdImage) {
                imagedestroy($thumb);
            }
        }
    }

    /**
     * Generate (and persist) a thumbnail for an existing photo, e.g. photos
     * uploaded before thumbnails existed. Returns the stored thumbnail path,
     * or null when generation failed or the original is missing on disk.
     */
    public function generateThumbnail(Photo $photo): ?string
    {
        $absolutePath = Storage::disk(self::LOCAL_DISK)->path($photo->file_path);

        if (! file_exists($absolutePath)) {
            return null;
        }

        $thumbnail = $this->makeThumbnail(
            $absolutePath,
            strtolower(pathinfo($photo->file_path, PATHINFO_EXTENSION)),
        );

        if ($thumbnail === null) {
            return null;
        }

        $photo->thumbnail_path = $thumbnail;
        $photo->saveQuietly();

        return $thumbnail;
    }

    /**
     * Store a batch of uploaded files, processing each one independently.
     *
     * A file that fails validation, duplicates an existing photo, or fails
     * storage is reported alongside the successes; it never throws or aborts
     * the rest of the batch. Real exceptions are logged via report() so
     * failures are debuggable.
     *
     * @param  array<int, UploadedFile|null>  $files
     * @param  array<int, string>  $altTexts
     * @param  array<int, string|null>  $keys
     * @return array{uploaded: array<int, array{key: string, photo: Photo}>, failed: array<int, array{key: string, original_filename: string, error: string}>, duplicates: array<int, array{key: string, original_filename: string}>}
     */
    public function storeMany(
        array $files,
        User $user,
        ?Album $album = null,
        array $altTexts = [],
        array $keys = [],
        ?string $rawTags = null,
    ): array {
        $usedBytes = $this->totalBytesFor($user);
        $limitBytes = $user->storageLimitBytes();
        $tags = filled($rawTags) ? $this->normalizeTags($rawTags) : null;

        $uploaded = [];
        $failed = [];
        $duplicates = [];
        $hashesSeen = [];

        foreach ($files as $index => $file) {
            $key = (string) ($keys[$index] ?? $index);
            $name = $file instanceof UploadedFile ? $file->getClientOriginalName() : 'file '.($index + 1);

            $error = $file instanceof UploadedFile ? $this->validateUpload($file) : 'No file was received for this upload slot.';

            // Content-hash duplicate detection (within this user's library only).
            $fileHash = '';
            if ($error === null && $file->getSize() > 0) {
                $fileHash = $this->hashFile($file->getPathname());

                if (isset($hashesSeen[$fileHash]) || $this->isDuplicateFor($user, $fileHash)) {
                    $duplicates[] = [
                        'key' => $key,
                        'original_filename' => strval($name),
                    ];

                    continue;
                }
            }

            if ($error === null && $file->getSize() > 0 && $fileHash !== '' && $usedBytes + $file->getSize() > $limitBytes) {
                $error = 'Storage quota is full; free up space before uploading more photos.';
            }

            if ($error !== null) {
                $failed[] = [
                    'key' => $key,
                    'original_filename' => strval($name),
                    'error' => $error,
                ];

                continue;
            }

            try {
                $photo = $this->store($file, $user, $altTexts[$index] ?? null, $album);

                if ($tags !== null) {
                    $photo->tags = $tags;
                    $photo->save();
                }

                $hashesSeen[$fileHash] = true;
                $usedBytes += $photo->size_bytes;

                $uploaded[] = ['key' => $key, 'photo' => $photo];
            } catch (Throwable $e) {
                report($e);
                $failed[] = [
                    'key' => $key,
                    'original_filename' => strval($name),
                    'error' => 'The photo could not be saved on the server. Please try again.',
                ];
            }
        }

        return compact('uploaded', 'failed', 'duplicates');
    }

    /**
     * Move a photo to the trash (soft delete). Files are kept on disk so the
     * delete can be undone during the retention window.
     */
    public function trash(Photo $photo): void
    {
        $photo->delete();
    }

    /**
     * Restore a photo that is currently in the trash.
     */
    public function restore(Photo $photo): void
    {
        if ($photo->trashed()) {
            $photo->restore();
        }
    }

    /**
     * Permanently delete a photo: destroy the record and remove its local file
     * (and thumbnail, if any).
     */
    public function purge(Photo $photo): void
    {
        $path = $photo->file_path;
        $thumbnailPath = $photo->thumbnail_path;

        $photo->forceDelete();

        if ($path !== null) {
            try {
                Storage::disk(self::LOCAL_DISK)->delete($path);
            } catch (Throwable) {
                // Best effort: the DB row is gone; orphan files are cleaned by maintenance.
            }
        }

        if ($thumbnailPath !== null) {
            try {
                Storage::disk(self::LOCAL_DISK)->delete($thumbnailPath);
            } catch (Throwable) {
                // Best effort.
            }
        }
    }

    /**
     * Permanently delete a batch of trashed photos with a single DELETE query,
     * then remove their local files in one storage call. Returns the number of
     * photos actually removed.
     */
    public function purgeMany(Collection $photos): int
    {
        $ids = [];
        $paths = [];

        foreach ($photos as $photo) {
            if (!$photo instanceof Photo || !$photo->trashed()) {
                continue;
            }

            $ids[] = $photo->id;

            if ($photo->file_path !== null) {
                $paths[] = $photo->file_path;
            }

            if ($photo->thumbnail_path !== null) {
                $paths[] = $photo->thumbnail_path;
            }
        }

        if ($ids !== []) {
            Photo::query()->whereKey($ids)->forceDelete();
        }

        if ($paths !== []) {
            try {
                Storage::disk(self::LOCAL_DISK)->delete($paths);
            } catch (Throwable) {
                // Best effort: the rows are gone; orphan files are cleaned by maintenance.
            }
        }

        return count($ids);
    }

    /**
     * Upload a photo to the configured cloud backup destination.
     *
     * When the cloud disk is enabled via PHOTOS_CLOUD_DISK it streams the file
     * there. Otherwise it mirrors the file to the local "private" mirror disk so
     * the backup flow stays fully functional in local development.
     *
     * @throws \RuntimeException When the file is already backed up or missing.
     */
    public function backupToCloud(Photo $photo): bool
    {
        if ($photo->isBackedUp()) {
            throw new \RuntimeException('This photo has already been backed up.');
        }

        $local = Storage::disk(self::LOCAL_DISK);

        if (! $local->exists($photo->file_path)) {
            throw new \RuntimeException('The photo file is missing locally and cannot be backed up.');
        }

        $cloudDiskName = config('photobackup.cloud_disk');

        if (filled($cloudDiskName)) {
            $cloud = Storage::disk($cloudDiskName);
            $remotePath = 'photos/'.$photo->user_id.'/'.basename($photo->file_path);
            $cloud->putFileAs(dirname($remotePath), $local->path($photo->file_path), basename($remotePath));
            $photo->remote_path = $remotePath;
        } else {
            $mirror = Storage::disk($this->fallbackCloudDisk);
            $mirrorPath = 'photos_backup/'.$photo->user_id.'/'.basename($photo->file_path);
            $mirror->putFileAs(dirname($mirrorPath), $local->path($photo->file_path), basename($mirrorPath));
            $photo->remote_path = $mirrorPath;
        }

        $photo->backed_up_at = now();
        $photo->save();

        return true;
    }

    /**
     * Total photo bytes the user has stored, including items in the trash.
     */
    public function totalBytesFor(User $user): int
    {
        return (int) $user->photos()->withTrashed()->sum('size_bytes');
    }

    /**
     * Return the user's photos filtered by the given criteria.
     *
     * @param  array{search?: string, album?: int|string, tags?: mixed, from?: string, to?: string, trashed?: string}  $filters
     * @return LengthAwarePaginator
     */
    public function filteredPhotos(User $user, array $filters, int $perPage = 30)
    {
        $albumId = $filters['album'] ?? null;

        if (! empty($filters['trashed'])) {
            $query = $user->photos()->onlyTrashed();
        } else {
            $query = $user->photos();
        }

        if ($albumId) {
            $query->whereHas('albums', fn ($q) => $q->where('albums.id', $albumId));
        }

        if (! empty($filters['search'])) {
            $term = trim((string) $filters['search']);
            $query->where(function ($q) use ($term) {
                $q->where('original_filename', 'like', "%{$term}%")
                    ->orWhere('alt_text', 'like', "%{$term}%");
            });
        }

        if (! empty($filters['from'])) {
            try {
                $query->whereDate('taken_at', '>=', Carbon::parse($filters['from'])->toDateString());
            } catch (Throwable) {
                // Ignore malformed dates.
            }
        }

        if (! empty($filters['to'])) {
            try {
                $query->whereDate('taken_at', '<=', Carbon::parse($filters['to'])->toDateString());
            } catch (Throwable) {
                // Ignore malformed dates.
            }
        }

        if (! empty($filters['tags'])) {
            $tags = $this->normalizeTags($filters['tags']);
            if ($tags !== []) {
                $query->where(function ($q) use ($tags) {
                    foreach ($tags as $tag) {
                        $q->orWhereJsonContains('tags', $tag);
                    }
                });
            }
        }

        if ($perPage === 0) {
            return $query->orderByDesc('taken_at')->orderByDesc('id')->get();
        }

        return $query->orderByDesc('taken_at')->orderByDesc('id')->paginate($perPage);
    }

    /**
     * Normalize a tag list (array, JSON string or comma-separated string) into
     * a cleaned, unique array of lowercase tag names.
     *
     * @return array<int, string>
     */
    public function normalizeTags(mixed $tags): array
    {
        if (is_string($tags)) {
            $decoded = json_decode($tags, true);
            $tags = is_array($decoded) ? $decoded : preg_split('/\s*,\s*/', $tags);
        }

        if (! is_array($tags)) {
            return [];
        }

        $cleaned = [];
        foreach ($tags as $tag) {
            $tag = trim(Str::lower((string) $tag));
            if ($tag !== '' && ! in_array($tag, $cleaned, true)) {
                $cleaned[] = $tag;
            }
        }

        return $cleaned;
    }

    /**
     * All distinct tags used by the user's photos.
     *
     * @return array<int, string>
     */
    public function allTagsFor(User $user): array
    {
        $tags = [];

        $user->photos()->whereNotNull('tags')->pluck('tags')->each(function ($photoTags) use (&$tags) {
            foreach ((array) $photoTags as $tag) {
                $tags[Str::lower(trim((string) $tag))] = true;
            }
        });

        $sorted = array_keys($tags);
        sort($sorted, SORT_STRING | SORT_FLAG_CASE);

        return $sorted;
    }

    /**
     * Build a ZIP archive containing the given photos and return its temporary path.
     *
     * @param  iterable<Photo>  $photos
     */
    public function buildZip(iterable $photos, string $archiveName): ?string
    {
        $disk = Storage::disk(self::LOCAL_DISK);
        $tmpFile = tempnam(sys_get_temp_dir(), 'pbo_').'.zip';

        $zip = new ZipArchive;
        if ($zip->open($tmpFile, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            return null;
        }

        foreach ($photos as $photo) {
            if ($photo->trashed() || ! $disk->exists($photo->file_path)) {
                continue;
            }

            $realName = basename($photo->original_filename) ?: basename($photo->file_path);
            $entry = $this->uniqueZipName($zip, $realName);
            $zip->addFile($disk->path($photo->file_path), $entry);
        }

        $zip->close();

        return $tmpFile;
    }

    /**
     * Read the camera capture timestamp from the photo's EXIF metadata, if any.
     */
    protected function extractTakenAt(?string $absolutePath): ?CarbonImmutable
    {
        if ($absolutePath === null || ! file_exists($absolutePath) || ! function_exists('exif_read_data')) {
            return null;
        }

        try {
            $exif = @exif_read_data($absolutePath, 'IFD0,EXIF', true);
        } catch (Throwable) {
            return null;
        }

        $raw = $exif['EXIF']['DateTimeOriginal']
            ?? $exif['EXIF']['DateTimeDigitized']
            ?? $exif['IFD0']['DateTime']
            ?? null;

        if ($raw === null) {
            return null;
        }

        try {
            return CarbonImmutable::createFromFormat('Y:m:d H:i:s', trim($raw));
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Ensure a unique entry name inside the archive.
     */
    protected function uniqueZipName(ZipArchive $zip, string $name): string
    {
        $base = pathinfo($name, PATHINFO_FILENAME);
        $ext = pathinfo($name, PATHINFO_EXTENSION);
        $candidate = $name;
        $i = 1;

        while ($zip->locateName($candidate) !== false) {
            $candidate = $ext === '' ? "{$base}_{$i}" : "{$base}_{$i}.".($ext ?: '');
            $i++;
        }

        return $candidate;
    }
}
