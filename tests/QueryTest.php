<?php
declare(strict_types=1);

namespace Database\Tests;

use Database\Driver;

/**
 * @coversDefaultClass \Database\Driver
 */
class QueryTest extends DriverTestCase
{
    /**
     * @covers ::query()
     * @covers ::isRaw()
     * @covers ::buildRaw()
     * @dataProvider typesProvider
     */
    public function testQuery($type)
    {
        $this->setType($type);

        $this->database->query("SELECT <account.email>,<account.nickname> FROM <account> WHERE <id> != 100");

        $this->assertQuery(
            <<<EOD
            SELECT `account`.`email`,`account`.`nickname`
            FROM `account`
            WHERE `id` != 100
            EOD,
            $this->database->queryString
        );
    }

    /**
     * @covers ::query()
     * @covers ::isRaw()
     * @covers ::buildRaw()
     */
    public function testQueryWithPrefix()
    {
        $database = new Driver([
            'driver' => 'mysql',
            'host' => 'localhost',
            'port' => 3306,
            'dbname' => 'test',
            'dsn' => '',
            'debug' => true,
            'prefix' => 'PREFIX_'
        ]);

        $database->options()->driver = "sqlite";

        $database->query("SELECT <account.name> FROM <account>");

        $this->assertQuery(
            <<<EOD
            SELECT `PREFIX_account`.`name`
            FROM `PREFIX_account`
            EOD,
            $database->queryString
        );
    }

    /**
     * @covers ::query()
     * @covers ::isRaw()
     * @covers ::buildRaw()
     * @dataProvider typesProvider
     */
    public function testPreparedStatementQuery($type)
    {
        $this->setType($type);

        $this->database->query(
            "SELECT * FROM <account> WHERE <user_name> = :user_name AND <age> = :age",
            [
                ":user_name" => "John Smite",
                ":age" => 20
            ]
        );

        $this->assertQuery(
            <<<EOD
            SELECT *
            FROM `account`
            WHERE `user_name` = 'John Smite' AND `age` = 20
            EOD,
            $this->database->queryString
        );
    }

    /**
     * @covers ::query()
     * @covers ::isRaw()
     * @covers ::buildRaw()
     */
    public function testQueryEscape()
    {
        $database = new Driver([
            'driver' => 'mysql',
            'host' => 'localhost',
            'port' => 3306,
            'dbname' => 'test',
            'dsn' => '',
            'debug' => true,
            'prefix' => 'PREFIX_'
        ]);

        $database->options()->driver = "sqlite";

        $database->query("SELECT * FROM <account> WHERE <name> = '<John>'");

        $this->assertQuery(
            <<<EOD
            SELECT *
            FROM `PREFIX_account`
            WHERE `name` = '<John>'
            EOD,
            $database->queryString
        );
    }
}
