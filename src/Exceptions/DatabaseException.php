<?php
declare(strict_types=1);

namespace Database\Exceptions;

use RuntimeException;
use Throwable;

class DatabaseException extends RuntimeException {
    public function __construct($message = "", $code = 0, Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}