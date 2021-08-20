<?php
declare(strict_types=1);

namespace Database\Drivers;

use Database\Driver;
use Database\Protocols\DriverInterface;
use Database\Tools\SQLSTATE;

class Odbc implements DriverInterface {

    /**
     * @inheritDoc
     */
    public function construct(Driver &$driver): void
    {
        $driver->setDsn($driver->options()->dsn);
    }

    /**
     * @inheritDoc
     */
    public function destruct(Driver &$driver): void
    {
        // TODO: Implement destruct() method.
    }

    /**
     * @inheritDoc
     */
    public function recognizer(string $sqlstate): int
    {
        return SQLSTATE::getStateArray($sqlstate)[1];
    }
}