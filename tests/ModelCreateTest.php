<?php
declare(strict_types=1);

namespace Database\Tests;

/**
 * @coversDefaultClass \Database\Connection
 */
class ModelCreateTest extends ModelTestCase
{
    /**
     * @covers ::create
     * @covers \Database\AbstractModel::table()
     * @dataProvider typesProvider
     */
    public function testMasterCreate($type)
    {
        $this->database = $this->setType($type, true);

        $this->database->create($this->model->table(), [
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
                CREATE TABLE IF NOT EXISTS "demo"
                ("id" INT NOT NULL AUTO_INCREMENT,
                "email" VARCHAR(70) NOT NULL UNIQUE,
                PRIMARY KEY ("id"))
                AUTO_INCREMENT = 200
                EOD
        ],
            $this->database->driver()->queryString
        );
    }

    /**
     * @covers ::create()
     * @covers \Database\AbstractModel::table()
     * @dataProvider typesProvider
     */
    public function testSlaveCreate($type)
    {
        $this->setType($type, false);

        $this->database->create($this->model->table(), [
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
                CREATE TABLE IF NOT EXISTS "demo"
                ("id" INT NOT NULL AUTO_INCREMENT,
                "email" VARCHAR(70) NOT NULL UNIQUE,
                PRIMARY KEY ("id"))
                AUTO_INCREMENT = 200
                EOD
            ],
            $this->database->driver()->queryString
        );
    }
}
