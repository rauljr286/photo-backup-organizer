<?php

namespace Tests\Support;

use Illuminate\Filesystem\FilesystemAdapter;
use RuntimeException;

/**
 * A disk that behaves like a bucket: files are read and written through the
 * filesystem API, but the disk has no path on the server, so the application
 * has to work on a temporary copy of every file it touches.
 */
class RemoteFilesystemAdapter extends FilesystemAdapter
{
    /**
     * Buckets have no path on the server.
     *
     * @param  string  $path
     * @return string
     *
     * @throws RuntimeException
     */
    public function path($path)
    {
        throw new RuntimeException('This driver does not support retrieving paths.');
    }
}
