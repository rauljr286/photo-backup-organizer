<?php

namespace App\Exceptions;

use Exception;
use Throwable;

/**
 * A direct upload could not be prepared or completed. The message is shown to
 * the user as-is, the status decides how the request is answered.
 */
class DirectUploadException extends Exception
{
    public function __construct(string $message, protected int $status = 422, ?Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
    }

    /**
     * The HTTP status the request is answered with.
     */
    public function status(): int
    {
        return $this->status;
    }
}
