<?php
declare(strict_types=1);

namespace Database\Tests;

/**
 * @coversDefaultClass \Database\Driver
 */
class CreateTest extends DriverTestCase
{
    /**
     * @covers ::create()
     * @dataProvider typesProvider
     */
    public function testCreate($type)
    {
        $this->setType($type);

        $this->database->create("account", [
            "id" => [
                "INT",
                "NOT NULL",
                "AUTO_INCREMENT"
            ],
            "email" => [
                "VARCHAR(70)",
                "NOT NULL",
                "UNIQUE"
            ],
            "PRIMARY KEY (<id>)"
        ], [
            "AUTO_INCREMENT" => 200
        ]);

        $this->assertQuery(
            [
            'default' => <<<EOD
                CREATE TABLE IF NOT EXISTS "account"
                ("id" INT NOT NULL AUTO_INCREMENT,
                "email" VARCHAR(70) NOT NULL UNIQUE,
                PRIMARY KEY ("id"))
                AUTO_INCREMENT = 200
                EOD
            ],
            $this->database->queryString
        );
    }

    /**
     * @covers ::create()
     * @dataProvider typesProvider
     */
    public function testCreateWithStringDefinition($type)
    {
        $this->setType($type);

        $this->database->create("account", [
            "id" => "INT NOT NULL AUTO_INCREMENT",
            "email" => "VARCHAR(70) NOT NULL UNIQUE"
        ]);

        $this->assertQuery(
            [
            'default' => <<<EOD
                CREATE TABLE IF NOT EXISTS "account"
                ("id" INT NOT NULL AUTO_INCREMENT,
                "email" VARCHAR(70) NOT NULL UNIQUE)
                EOD,
        ],
            $this->database->queryString
        );
    }

    /**
     * @covers ::create()
     * @dataProvider typesProvider
     */
    public function testCreateWithSingleOption($type)
    {
        $this->setType($type);

        $this->database->create("account", [
            "id" => [
                "INT",
                "NOT NULL",
                "AUTO_INCREMENT"
            ],
            "email" => [
                "VARCHAR(70)",
                "NOT NULL",
                "UNIQUE"
            ]
        ], "TABLESPACE tablespace_name");

        $this->assertQuery(
            [
            'default' => <<<EOD
                CREATE TABLE IF NOT EXISTS "account"
                ("id" INT NOT NULL AUTO_INCREMENT,
                "email" VARCHAR(70) NOT NULL UNIQUE)
                TABLESPACE tablespace_name
                EOD,
        ],
            $this->database->queryString
        );
    }
}
