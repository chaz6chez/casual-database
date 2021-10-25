<?php
declare(strict_types=1);

namespace Database\Drivers;

use Database\Driver;
use Database\Protocols\DriverInterface;
use Database\Tools\SQLSTATE;

class Sqlite implements DriverInterface {

    /**
     * @inheritDoc
     */
    public function construct(Driver &$driver): void
    {
        $attr = [
            'driver' => $driver->options()->driver,
            $driver->options()->dbname
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
        try {
            if($driver->pdo()){
                $driver->close();
                $driver->rollback();
            }
        }catch (\PDOException $exception){}
    }

    /**
     * @inheritDoc
     */
    public function recognizer(string $sqlstate): int
    {
        return SQLSTATE::getStateArray($sqlstate)[1];
    }
}