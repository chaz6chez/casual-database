<?php
declare(strict_types=1);

namespace Database\Exceptions;

use Throwable;

class ExpireException extends DatabaseException {
    public function __construct(string $message = 'Expire Exception', $code = 0, Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}