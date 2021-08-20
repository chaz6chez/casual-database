<?php
declare(strict_types=1);

namespace Database\Exceptions;

use Throwable;

class DbnameException extends DatabaseException {
    public function __construct(string $message = 'dbName Exception', $code = 0, Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}