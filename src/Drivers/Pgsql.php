<?php
declare(strict_types=1);

namespace Database\Drivers;

use Database\Driver;
use Database\Protocols\DriverInterface;
use Database\Tools\SQLSTATE;

class Pgsql implements DriverInterface {

    /**
     * @inheritDoc
     */
    public function construct(Driver &$driver): void
    {
        $attr = [
            'driver' => $driver->options()->driver = 'mysql',
            'dbname' => $driver->options()->dbname,
            'port'   => $driver->options()->port,
            'host'   => $driver->options()->host
        ];
        foreach ($attr as $key => $value) {
            $stack[] = $key . '=' . $value;
        }
        $driver->setDsn($driver->options()->driver . ':' . implode(';', $stack));

        $commands[] = "SET NAMES '{$driver->options()->charset}'";
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