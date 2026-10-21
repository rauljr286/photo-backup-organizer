<?php

namespace App\Support;

/**
 * Marker returned by a LocalFileCopy callback to have the produced bytes
 * stored back on the disk, overwriting the original file.
 */
class FileWrite
{
    public function __construct(public readonly string $contents) {}

    public static function of(string $contents): self
    {
        return new self($contents);
    }
}
