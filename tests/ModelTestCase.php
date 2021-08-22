<?php
declare(strict_types=1);

namespace Database\Tests;

use Database\AbstractModel;
use Database\Connection;

class ModelTestCase extends BaseTestCase
{
    protected $model;
    protected $database;

    public function setUp(): void
    {
        $this->model = new TestModel();
    }

    public function typesProvider(): array
    {
        return [
            'MySQL' => ['mysql'],
            'SQLite' => ['sqlite'],
            'PostgreSQL' => ['pgsql'],
            'Odbc' => ['odbc']
        ];
    }

    public function setType($type, bool $master): Connection
    {
        $this->database = $this->model->dbName($master);
        $this->database->driver()->options()->driver = $type;
        return $this->database;
    }

    public function assertQuery($expected, $query): void
    {
        if (is_array($expected)) {
            $this->assertEquals(
                $this->_expectedQuery(
                    $expected[$this->database->driver()->options()->driver] ?? $expected['default']
                ),
                $query
            );
        } else {
            $this->assertEquals($this->_expectedQuery($expected), $query);
        }
    }
}

class TestModel extends AbstractModel {
    protected $_dbName = 'demo';
    protected $_table = 'demo';

    protected function _masterConfig(): array
    {
        return [
            'driver' => 'mysql',
            'host' => 'localhost',
            'port' => 3306,
            'dbname' => 'test',
            'dsn' => '',
            'debug' => true,
        ];
    }

    protected function _slaveConfig(): array
    {
        return [
            'driver' => 'mysql',
            'host' => 'localhost',
            'port' => 3306,
            'dbname' => 'test',
            'dsn' => '',
            'debug' => true,
        ];
    }
}