<?php
declare(strict_types=1);

namespace Database\Tests;

use Database\Driver;
use PHPUnit\Framework\TestCase;

class DriverTestCase extends TestCase
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
        $this->database = $this->database->debug();
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

    public function expectedQuery($expected): string
    {
        return preg_replace(
            '/(?!\'[^\s]+\s?)"([\p{L}_][\p{L}\p{N}@$#\-_]*)"(?!\s?[^\s]+\')/u',
            '`$1`',
            str_replace("\n", " ", $expected)
        );
    }

    public function assertQuery($expected, $query): void
    {
        if (is_array($expected)) {
            $this->assertEquals(
                $this->expectedQuery($expected[$this->database->options()->driver] ?? $expected['default']),
                $query
            );
        } else {
            $this->assertEquals($this->expectedQuery($expected), $query);
        }
    }
}

class Foo
{
    public $bar = "cat";

    public function __wakeup()
    {
        $this->bar = "dog";
    }
}
