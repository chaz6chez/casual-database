<?php
declare(strict_types=1);

namespace Database\Tests;

use Database\Driver;

/**
 * @coversDefaultClass \Database\Connection
 */
class ModelInsertTest extends ModelTestCase
{
    /**
     * @covers ::insert()
     * @covers ::typeMap()
     * @dataProvider typesProvider
     */
    public function testInsert($type)
    {
        $this->database = $this->setType($type, true);

        $this->database->table($this->model->table())->insert([
            "user_name" => "foo",
            "email" => "foo@bar.com"
        ]);

        $this->assertQuery(
            <<<EOD
            INSERT INTO "demo" ("user_name", "email")
            VALUES ('foo', 'foo@bar.com')
            EOD,
            $this->database->driver()->queryString
        );
    }

    /**
     * @covers ::insert()
     * @covers ::typeMap()
     * @dataProvider typesProvider
     */
    public function testInsertWithArray($type)
    {
        $this->database = $this->setType($type, true);

        $this->database->table($this->model->table())->insert([
            "user_name" => "foo",
            "lang" => ["en", "fr"]
        ]);

        $this->assertQuery([
            'default' => <<<EOD
                INSERT INTO "demo" ("user_name", "lang")
                VALUES ('foo', 'a:2:{i:0;s:2:"en";i:1;s:2:"fr";}')
                EOD,
            'mysql' => <<<EOD
                INSERT INTO "demo" ("user_name", "lang")
                VALUES ('foo', 'a:2:{i:0;s:2:\"en\";i:1;s:2:\"fr\";}')
                EOD
        ], $this->database->driver()->queryString);
    }

    /**
     * @covers ::insert()
     * @covers ::typeMap()
     * @dataProvider typesProvider
     */
    public function testInsertWithJSON($type)
    {
        $this->database = $this->setType($type, true);

        $this->database->table($this->model->table())->insert([
            "user_name" => "foo",
            "lang [JSON]" => ["en", "fr"]
        ]);

        $this->assertQuery([
            'default' => <<<EOD
                INSERT INTO "demo" ("user_name", "lang")
                VALUES ('foo', '["en","fr"]')
                EOD,
            'mysql' => <<<EOD
                INSERT INTO `demo` (`user_name`, `lang`)
                VALUES ('foo', '[\"en\",\"fr\"]')
                EOD
        ], $this->database->driver()->queryString);
    }

    /**
     * @covers ::insert()
     * @dataProvider typesProvider
     */
    public function testInsertWithRaw($type)
    {
        $this->database = $this->setType($type, true);

        $this->database->table($this->model->table())->insert([
            "user_name" => Driver::raw("UUID()")
        ]);

        $this->assertQuery(
            <<<EOD
            INSERT INTO "demo" ("user_name")
            VALUES (UUID())
            EOD,
            $this->database->driver()->queryString
        );
    }

    /**
     * @covers ::insert()
     * @covers ::typeMap()
     * @dataProvider typesProvider
     */
    public function testInsertWithNull($type)
    {
        $this->database = $this->setType($type, true);

        $this->database->table($this->model->table())->insert([
            "location" => null
        ]);

        $this->assertQuery(
            <<<EOD
            INSERT INTO "demo" ("location")
            VALUES (NULL)
            EOD,
            $this->database->driver()->queryString
        );
    }

    /**
     * @covers ::insert()
     * @covers ::typeMap()
     * @dataProvider typesProvider
     */
    public function testInsertWithObject($type)
    {
        $this->database = $this->setType($type, true);

        $objectData = new Foo();

        $this->database->table($this->model->table())->insert([
            "object" => $objectData
        ]);

        $this->assertQuery([
            'default' => <<<EOD
            INSERT INTO "demo" ("object")
            VALUES ('O:18:"Database\Tests\Foo":1:{s:3:"bar";s:3:"cat";}')
            EOD,
            'mysql' => <<<EOD
            INSERT INTO "demo" ("object")
            VALUES ('O:18:\"Database\Tests\Foo\":1:{s:3:\"bar\";s:3:\"cat\";}')
            EOD,
        ], $this->database->driver()->queryString
        );
    }

    /**
     * @covers ::insert()
     * @dataProvider typesProvider
     */
    public function testMultiInsert($type)
    {
        $this->database = $this->setType($type, true);

        $this->database->table($this->model->table())->insert([
            [
                "user_name" => "foo",
                "email" => "foo@bar.com"
            ],
            [
                "user_name" => "bar",
                "email" => "bar@foo.com"
            ]
        ]);

        $this->assertQuery(
            <<<EOD
            INSERT INTO "demo" ("user_name", "email")
            VALUES ('foo', 'foo@bar.com'), ('bar', 'bar@foo.com')
            EOD,
            $this->database->driver()->queryString
        );
    }
}
