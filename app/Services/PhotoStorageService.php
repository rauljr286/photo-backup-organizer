<?php

namespace App\Services;

use App\Models\Album;
use App\Models\Photo;
use App\Models\User;
use App\Support\FileWrite;
use App\Support\LocalFileCopy;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;
use ZipArchive;

class PhotoStorageService
{
    protected const THUMBNAIL_MAX_DIMENSION = 400;

    public const ALLOWED_EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp', 'gif'];

    protected string $fallbackCloudDisk;

    public function __construct()
    {
        $this->fallbackCloudDisk = config('photobackup.local_mirror_disk', 'local');
    }

    /**
     * The disk holding the photo originals, the generated thumbnails and the
     * profile pictures. Defaults to the local "public" disk.
     */
    public function photoDisk(): string
    {
        return (string) config('photobackup.photo_disk', 'public');
    }

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

    public function store(UploadedFile $file, User $user, ?Album $album, ?string $altText = null, bool $private = false): Photo
    {
        $error = $this->validateUpload($file);
        if ($error !== null) {
            throw new \InvalidArgumentException($error);
        }

        if (! $user->hasStorageFor($file->getSize())) {
            throw new \RuntimeException('Storage quota is full; free up space before uploading more photos.');
        }

        $hash = $this->hashFile($file->getPathname());
        $extension = strtolower($file->getClientOriginalExtension());
        $directory = "photos/{$user->id}";
        $name = Str::uuid().'.'.$extension;
        $path = $file->storeAs($directory, $name, $this->photoDisk());

        $photo = new Photo([
            'user_id' => $user->id,
            'file_path' => $path,
            'original_filename' => $file->getClientOriginalName(),
            'file_hash' => $hash,
            'alt_text' => trim((string) $altText) !== '' ? trim((string) $altText) : null,
            'taken_at' => $this->readTakenAt($file->getPathname()),
            'size_bytes' => $file->getSize(),
            'mime_type' => $file->getMimeType(),
            'is_private' => $private,
        ]);
        $photo->save();

        if ($album instanceof Album) {
            $photo->albums()->attach($album);
        }

        $this->generateThumbnail($photo);

        return $photo;
    }

    /**
     * Process an entire batch of uploads. Each file is validated, de-duplicated
     * and stored independently so a single bad file never fails a whole batch.
     *
     * @param  array<int, UploadedFile>  $files
     * @param  array<int, mixed>  $altTexts
     * @param  array<int, mixed>  $keys
     * @param  array<int, string>|string|null  $tags
     * @return array{uploaded: array<int, array{photo: Photo, key: string}>, failed: array<int, array{key: string, original_filename: string, error: string}>, duplicates: array<int, array{key: string, original_filename: string, error: string}>}
     */
    public function storeMany(array $files, User $user, ?Album $album, array $altTexts, array $keys, array|string|null $tags, bool $private = false): array
    {
        $uploaded = [];
        $failed = [];
        $duplicates = [];

        $normalizedTags = $this->normalizeTags($tags);

        foreach ($files as $index => $file) {
            $key = (string) ($keys[$index] ?? '');
            $originalName = $file->getClientOriginalName();

            $error = $this->validateUpload($file);
            if ($error !== null) {
                $failed[] = [
                    'key' => $key,
                    'original_filename' => $originalName,
                    'error' => $error,
                ];

                continue;
            }

            if (! $user->hasStorageFor($file->getSize())) {
                $failed[] = [
                    'key' => $key,
                    'original_filename' => $originalName,
                    'error' => 'Storage quota is full; free up space before uploading more photos.',
                ];

                continue;
            }

            $hash = $this->hashFile($file->getPathname());

            $existing = Photo::query()
                ->where('user_id', $user->id)
                ->where('is_private', $private)
                ->where('file_hash', $hash)
                ->first();

            if ($existing !== null) {
                $duplicates[] = [
                    'key' => $key,
                    'original_filename' => $originalName,
                    'error' => 'This photo is already backed up.',
                ];

                continue;
            }

            try {
                $altText = null;
                if (array_key_exists($key, $altTexts)) {
                    $altText = (string) $altTexts[$key];
                } elseif (array_key_exists($index, $altTexts)) {
                    $altText = (string) $altTexts[$index];
                }

                $photo = $this->store($file, $user, $private ? null : $album, $altText, $private);

                if ($normalizedTags !== []) {
                    $photo->tags = $normalizedTags;
                    $photo->save();
                }

                $uploaded[] = ['photo' => $photo, 'key' => $key];
            } catch (Throwable $e) {
                report($e);
                $failed[] = [
                    'key' => $key,
                    'original_filename' => $originalName,
                    'error' => $e->getMessage(),
                ];
            }
        }

        return [
            'uploaded' => $uploaded,
            'failed' => $failed,
            'duplicates' => $duplicates,
        ];
    }

    /**
     * The user's photos with optional search, album, tag and date filters.
     * Archived photos are excluded unless the "archived" flag is set, and the
     * result is paginated unless a per-page count of zero is requested.
     */
    public function filteredPhotos(User $user, array $filters, int $perPage): LengthAwarePaginator
    {
        return $this->photosQuery($user, $filters)
            ->orderByDesc('taken_at')
            ->orderByDesc('id')
            ->paginate(max(1, $perPage));
    }

    /**
     * Photos the user owns that match the given filters and have an id greater
     * than $afterId, ordered oldest-first so the front-end can prepend them in
     * the order they were created. Powers the live grid refresher that keeps
     * other devices in sync without a full page reload.
     *
     * @param  array<string, mixed>  $filters
     * @return Collection<int, Photo>
     */
    public function latestPhotos(User $user, array $filters, int $afterId, int $limit = 60): Collection
    {
        return $this->photosQuery($user, $filters)
            ->where('photos.id', '>', $afterId)
            ->orderBy('photos.id')
            ->limit($limit)
            ->get();
    }

    /**
     * Base query for a user's photos with the shared search/album/tag/date
     * filters applied. Archived photos are excluded unless the "archived" flag
     * is set.
     *
     * @param  array<string, mixed>  $filters
     */
    protected function photosQuery(User $user, array $filters)
    {
        $query = $user->photos()
            ->with('albums')
            ->where('is_private', false)
            ->when(! empty($filters['archived']), fn ($q) => $q->whereNotNull('archived_at'))
            ->when(empty($filters['archived']), fn ($q) => $q->whereNull('archived_at'));

        if (! empty($filters['search'])) {
            $term = '%'.$filters['search'].'%';
            $query->where(function ($q) use ($term) {
                $q->where('original_filename', 'like', $term)
                    ->orWhere('alt_text', 'like', $term);
            });
        }

        if (! empty($filters['album'])) {
            $query->whereHas('albums', fn ($q) => $q->whereKey($filters['album']));
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

        if (! empty($filters['from'])) {
            $query->whereDate('taken_at', '>=', $filters['from']);
        }

        if (! empty($filters['to'])) {
            $query->whereDate('taken_at', '<=', $filters['to']);
        }

        return $query;
    }

    /**
     * Every distinct tag used across the user's photos, sorted alphabetically.
     *
     * @return array<int, string>
     */
    public function allTagsFor(User $user): array
    {
        return $user->photos()
            ->where('is_private', false)
            ->pluck('tags')
            ->filter()
            ->flatten()
            ->unique()
            ->sort()
            ->values()
            ->all();
    }

    /**
     * Apply the same metadata to a set of the user's photos. alt_text and tags
     * replace the current values on each photo; an album, when provided and
     * owned by the user, is attached without removing existing albums.
     *
     * @param  array<int, string|int>  $ids
     * @return array{count: int}
     */
    public function applyBulkUpdate(User $user, array $ids, ?string $altText, ?string $tags, ?int $albumId): array
    {
        $photos = $user->photos()->whereIn('photos.id', $ids)->where('is_private', false)->get();

        if ($photos->isEmpty()) {
            return ['count' => 0];
        }

        $normalizedTags = $this->normalizeTags($tags);
        $album = $albumId !== null ? $user->albums()->find($albumId) : null;

        foreach ($photos as $photo) {
            if ($altText !== null) {
                $photo->alt_text = trim($altText) !== '' ? trim($altText) : null;
            }

            if ($tags !== null) {
                $photo->tags = $normalizedTags;
            }

            $photo->save();

            if ($album instanceof Album) {
                $photo->albums()->syncWithoutDetaching($album);
            }
        }

        return ['count' => $photos->count()];
    }

    /**
     * Mirror a photo to the configured cloud disk, or to the local mirror disk
     * when no cloud destination is configured.
     */
    public function backupToCloud(Photo $photo): void
    {
        $disk = $this->photoDisk();

        if (! Storage::disk($disk)->exists($photo->file_path)) {
            throw new \RuntimeException('Photo file missing on disk.');
        }

        $cloudDisk = config('photobackup.cloud_disk') ?: $this->fallbackCloudDisk;
        $remotePath = "backups/{$photo->user_id}/".basename($photo->file_path);

        $stream = Storage::disk($disk)->readStream($photo->file_path);

        if (! is_resource($stream)) {
            throw new \RuntimeException('Photo file missing on disk.');
        }

        Storage::disk($cloudDisk)->put($remotePath, $stream);

        if (is_resource($stream)) {
            fclose($stream);
        }

        $photo->remote_path = $remotePath;
        $photo->backed_up_at = now();
        $photo->save();
    }

    /**
     * Permanently remove a photo and every file associated with it. Only used
     * for admin/system-level removal (e.g. account deletion) — never exposed to
     * regular users, who archive instead.
     */
    public function purge(Photo $photo): void
    {
        $disk = $this->photoDisk();

        Storage::disk($disk)->delete($photo->file_path);

        if (filled($photo->thumbnail_path)) {
            Storage::disk($disk)->delete($photo->thumbnail_path);
        }

        if (filled($photo->remote_path)) {
            $cloudDisk = config('photobackup.cloud_disk') ?: $this->fallbackCloudDisk;
            Storage::disk($cloudDisk)->delete($photo->remote_path);
        }

        $photo->forceDelete();
    }

    /**
     * Create a square-ish thumbnail capped at 400px on its longest edge.
     * The JPEG is stored on the photo disk and the path is persisted on the
     * photo. Returns the stored thumbnail path, or null when the source file
     * cannot be processed.
     */
    public function generateThumbnail(Photo $photo): ?string
    {
        $disk = $this->photoDisk();
        $relativePath = "thumbnails/{$photo->user_id}/".Str::uuid().'.jpg';

        $generated = LocalFileCopy::withLocalCopy(
            $disk,
            $photo->file_path,
            function (string $source) use ($photo, $disk, $relativePath): ?string {
                if (! is_file($source)) {
                    return null;
                }

                $info = @getimagesize($source);

                if ($info === false) {
                    return null;
                }

                [$width, $height] = $info;

                $sourceImage = match ($info[2]) {
                    IMAGETYPE_JPEG => @imagecreatefromjpeg($source),
                    IMAGETYPE_PNG => @imagecreatefrompng($source),
                    IMAGETYPE_WEBP => @imagecreatefromwebp($source),
                    IMAGETYPE_GIF => @imagecreatefromgif($source),
                    default => false,
                };

                if ($sourceImage === false) {
                    return null;
                }

                $scale = min(1, self::THUMBNAIL_MAX_DIMENSION / max($width, $height));
                $newWidth = max(1, (int) round($width * $scale));
                $newHeight = max(1, (int) round($height * $scale));

                $thumb = imagecreatetruecolor($newWidth, $newHeight);
                imagecopyresampled($thumb, $sourceImage, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);

                ob_start();
                imagejpeg($thumb, null, 82);
                $data = ob_get_clean();

                imagedestroy($sourceImage);
                imagedestroy($thumb);

                Storage::disk($disk)->put($relativePath, $data);

                return $data;
            },
        );

        if ($generated === null) {
            return null;
        }

        $photo->thumbnail_path = $relativePath;
        $photo->save();

        return $relativePath;
    }

    /**
     * SHA-256 content hash of a file, used for duplicate detection. Returns an
     * empty string when the file cannot be read.
     */
    public function hashFile(string $path): string
    {
        if (! is_file($path) || ! is_readable($path)) {
            return '';
        }

        $hash = @hash_file('sha256', $path);

        return $hash === false ? '' : $hash;
    }

    /**
     * SHA-256 content hash of a file stored on the given disk. Works for
     * remote disks too, where the file is copied to a temporary file first.
     * Returns an empty string when the file cannot be read.
     */
    public function hashStoredFile(string $disk, string $path): string
    {
        return (string) LocalFileCopy::withLocalCopy(
            $disk,
            $path,
            fn (string $localPath): string => $this->hashFile($localPath),
        );
    }

    /**
     * Build a ZIP archive containing the given photos' original files. Returns
     * the path to a temporary archive, or null when nothing could be packed.
     */
    public function buildZip(Collection $photos, string $prefix): ?string
    {
        if ($photos->isEmpty()) {
            return null;
        }

        $tmp = tempnam(sys_get_temp_dir(), 'photo_backup_');
        $zip = new ZipArchive;

        if ($zip->open($tmp, ZipArchive::OVERWRITE) !== true) {
            return null;
        }

        $added = 0;
        $disk = $this->photoDisk();
        $temporaryFiles = [];

        try {
            foreach ($photos as $photo) {
                $entry = $photo->original_filename ?: basename($photo->file_path);

                // The archive reads the file when it is closed, so temporary
                // copies have to stay around until then.
                LocalFileCopy::withLocalCopy(
                    $disk,
                    $photo->file_path,
                    function (string $source, bool $isTemporary) use ($zip, $entry, &$added, &$temporaryFiles): ?string {
                        if (! is_file($source)) {
                            return null;
                        }

                        if ($zip->addFile($source, $entry)) {
                            $added++;
                        }

                        if ($isTemporary) {
                            $temporaryFiles[] = $source;
                        }

                        return null;
                    },
                    preserveTempFile: true,
                );
            }
        } finally {
            $zip->close();

            foreach ($temporaryFiles as $temporaryFile) {
                if (is_file($temporaryFile)) {
                    @unlink($temporaryFile);
                }
            }
        }

        if ($added === 0) {
            @unlink($tmp);

            return null;
        }

        return $tmp;
    }

    public function archive(Photo $photo): void
    {
        $photo->archive();
    }

    public function unarchive(Photo $photo): void
    {
        $photo->unarchive();
    }

    /**
     * Rotate a photo 90 degrees clockwise ("right") or counter-clockwise
     * ("left"). The stored original is overwritten, the thumbnail regenerated,
     * and any cloud backup for the photo is invalidated since the bytes changed.
     */
    public function rotate(Photo $photo, string $direction): bool
    {
        $degrees = strtolower($direction) === 'left' ? 90.0 : -90.0;

        return $this->applyRotation($photo, $degrees);
    }

    /**
     * Re-encode a photo after rotating it by the given number of degrees
     * (counter-clockwise positive). Returns false when the file cannot be
     * processed so the caller can surface a message.
     */
    protected function applyRotation(Photo $photo, float $degrees): bool
    {
        $disk = $this->photoDisk();

        $result = LocalFileCopy::withLocalCopy(
            $disk,
            $photo->file_path,
            function (string $sourcePath) use ($photo, $degrees) {
                if (! is_file($sourcePath)) {
                    return false;
                }

                $source = $this->loadEditableImage($sourcePath);

                if ($source === null) {
                    return false;
                }

                $hasAlpha = in_array($photo->mime_type, ['image/png', 'image/webp'], true);
                $background = $hasAlpha
                    ? imagecolorallocatealpha($source, 0, 0, 0, 127)
                    : imagecolorallocate($source, 255, 255, 255);

                $rotated = @imagerotate($source, $degrees, $background);
                imagedestroy($source);

                if ($rotated === false) {
                    return false;
                }

                $data = $this->encodeEditableImage($photo, $rotated);
                imagedestroy($rotated);

                if ($data === null) {
                    return false;
                }

                // The rotated bytes replace the original file on the disk.
                return FileWrite::of($data);
            },
        );

        if (! $result instanceof FileWrite) {
            return false;
        }

        $photo->remote_path = null;
        $photo->backed_up_at = null;
        $photo->save();

        $this->generateThumbnail($photo);

        return true;
    }

    /**
     * Load an image by its file type into a GD image. JPEG, PNG and WebP are
     * editable; GIFs (and anything else) are not.
     */
    protected function loadEditableImage(string $path): ?\GdImage
    {
        $info = @getimagesize($path);

        if ($info === false) {
            return null;
        }

        return match ($info[2]) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($path) ?: null,
            IMAGETYPE_PNG => @imagecreatefrompng($path) ?: null,
            IMAGETYPE_WEBP => @imagecreatefromwebp($path) ?: null,
            default => null,
        };
    }

    /**
     * Encode a GD image back to the photo's original format (JPEG by default).
     */
    protected function encodeEditableImage(Photo $photo, \GdImage $image): ?string
    {
        ob_start();

        $ok = match (strtolower(pathinfo($photo->original_filename, PATHINFO_EXTENSION))) {
            'png' => imagepng($image),
            'webp' => imagewebp($image, null, 90),
            default => imagejpeg($image, null, 90),
        };

        $data = ob_get_clean();

        return $ok ? $data : null;
    }

    /**
     * Parse the EXIF capture date (if any) from a photo file.
     */
    protected function readTakenAt(string $path): ?Carbon
    {
        $exif = @exif_read_data($path, 'EXIF');

        $date = $exif['DateTimeOriginal'] ?? $exif['DateTime']
            ?? ($exif['FILE']['FileDateTime'] ?? null);

        if (empty($date) || is_string($date) === false) {
            return null;
        }

        try {
            return Carbon::parse($date);
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Normalize a comma-separated string or array of tags into a de-duplicated,
     * trimmed array.
     *
     * @param  array<int, string>|string|null  $tags
     * @return array<int, string>
     */
    protected function normalizeTags(array|string|null $tags): array
    {
        if ($tags === null) {
            return [];
        }

        $parts = is_array($tags) ? $tags : explode(',', $tags);

        return collect($parts)
            ->map(fn ($tag) => trim((string) $tag))
            ->filter(fn ($tag) => $tag !== '')
            ->unique()
            ->values()
            ->all();
    }
}
