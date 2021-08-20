<?php
declare(strict_types=1);

namespace Database\Exceptions;

use Throwable;

class DatabaseConnectionException extends DatabaseException
{
    public function __construct($message = 'Database Connection Exception', $code = 0, Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}