<?php

namespace App\Exceptions;

use Exception;

/**
 * The photo that was uploaded is already backed up. The upload itself was
 * fine, so the photo is reported as a duplicate instead of a failure.
 */
class DuplicatePhotoException extends Exception
{
    public function __construct(string $message = 'This photo is already backed up.')
    {
        parent::__construct($message);
    }
}
