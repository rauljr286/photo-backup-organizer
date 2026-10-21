<?php

namespace App\Support;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Filesystem\LocalFilesystemAdapter;
use Illuminate\Support\Facades\Storage;
use League\Flysystem\UnableToReadFile;
use RuntimeException;

/**
 * Gives a callback a real local file for a file that lives on a disk.
 *
 * Local disks expose an actual path on the server, so the callback receives
 * it directly. Remote disks (S3 and friends) have no path on the server, so
 * the file is streamed into a temporary copy first and the copy is removed
 * again as soon as the callback is done with it.
 */
class LocalFileCopy
{
    /**
     * Run a callback against a local copy of the file stored at $path.
     *
     * The callback receives the path of the local copy and a flag telling
     * whether that copy is a temporary one. Returning a FileWrite stores the
     * given bytes back on the disk, overwriting the original file.
     *
     * @param  callable(string $localPath, bool $isTemporary): mixed  $callback
     */
    public static function withLocalCopy(string $disk, string $path, callable $callback, bool $preserveTempFile = false): mixed
    {
        $storage = Storage::disk($disk);

        if ($storage instanceof LocalFilesystemAdapter) {
            $result = $callback($storage->path($path), false);

            if ($result instanceof FileWrite) {
                $storage->put($path, $result->contents);
            }

            return $result;
        }

        $temporary = self::copyToTemp($storage, $path);
        $keep = false;

        try {
            $result = $callback($temporary, true);
            $keep = $preserveTempFile;

            if ($result instanceof FileWrite) {
                $storage->put($path, $result->contents);
            }

            return $result;
        } finally {
            if (! $keep) {
                self::delete($temporary);
            }
        }
    }

    /**
     * Stream the file into a temporary copy and return its path. When the
     * file does not exist on the disk, the temporary path is removed so the
     * callback can tell the file is missing.
     */
    protected static function copyToTemp(Filesystem $storage, string $path): string
    {
        $temporary = tempnam(sys_get_temp_dir(), 'photo_copy_');

        if ($temporary === false) {
            throw new RuntimeException('Unable to create a temporary copy of the file.');
        }

        try {
            $stream = $storage->readStream($path);
        } catch (UnableToReadFile) {
            $stream = null;
        }

        if (! is_resource($stream)) {
            @unlink($temporary);

            return $temporary;
        }

        $target = fopen($temporary, 'wb');
        stream_copy_to_stream($stream, $target);
        fclose($target);
        fclose($stream);

        return $temporary;
    }

    /**
     * Remove a temporary copy, ignoring files that are already gone.
     */
    protected static function delete(string $path): void
    {
        if (is_file($path)) {
            @unlink($path);
        }
    }
}
