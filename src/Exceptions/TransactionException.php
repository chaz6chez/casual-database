<?php
declare(strict_types=1);

namespace Database\Exceptions;

use Throwable;

class TransactionException extends DatabaseException {
    public function __construct(string $message = 'Transaction Exception', $code = 0, Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}