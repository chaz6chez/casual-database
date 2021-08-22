<?php
declare(strict_types=1);

namespace Database\Tests;

use Database\Driver;

class DriverTestCase extends BaseTestCase
{
    protected $database;

    public function setUp(): void
    {
        $this->database = new Driver([
            'driver' => 'mysql',
            'host' => 'localhost',
            'port' => 3306,
            'dbname' => 'test',
            'dsn' => '',
            'debug' => true,
        ]);
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

    public function setType($type): void
    {
        $this->database->options()->driver = $type;
    }

    public function assertQuery($expected, $query): void
    {
        if (is_array($expected)) {
            $this->assertEquals(
                $this->_expectedQuery($expected[$this->database->options()->driver] ?? $expected['default']),
                $query
            );
        } else {
            $this->assertEquals($this->_expectedQuery($expected), $query);
        }
    }
}
