<?php
declare(strict_types=1);

namespace Database\Drivers;

use Database\Driver;
use Database\Protocols\DriverInterface;
use Database\Protocols\StateInterface;
use Database\Tools\SQLSTATE;

class Mysql implements DriverInterface {

    /**
     * @inheritDoc
     */
    public function construct(Driver &$driver): void
    {
        if(!$dsn = $driver->options()->dsn){
            $attr = [
                'driver' => $driver->options()->driver = 'mysql',
                'dbname' => $driver->options()->dbname,
                'port'   => $driver->options()->port,
                'host'   => $driver->options()->host
            ];
            foreach ($attr as $key => $value) {
                $stack[] = $key . '=' . $value;
            }
            $dsn = $driver->options()->driver . ':' . implode(';', $stack);
        }
        $driver->setDsn($dsn);
        $commands[] = "SET NAMES '{$driver->options()->charset}'";
    }

    /**
     * @inheritDoc
     */
    public function destruct(Driver &$driver): void
    {
        try {
            if(
                $driver->pdo() and
                $driver->pdo()->inTransaction()
            ){
                $driver->pdo()->rollBack();
                $driver->pdoReset();
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
