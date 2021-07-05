<?php
declare(strict_types=1);

namespace Database\Exception;

class ExpireException extends \RuntimeException {
    public function __construct(string $message = 'Expire Exception', $code = 0, \Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}