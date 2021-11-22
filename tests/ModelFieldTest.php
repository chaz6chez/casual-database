<?php

namespace Database\Tests;

use Database\Driver;

/**
 * @coversDefaultClass \Database\Connection
 */
class ModelFieldTest extends ModelTestCase
{
    /**
     * @covers ::select()
     * @covers ::field()
     * @covers ::where()
     * @covers ::order()
     * @dataProvider typesProvider
     */
    public function testStringField($type)
    {
        $this->database = $this->setType($type, true);
// one
        $this->database->table($this->model->table())->field("user_name")
            ->where([
                "user_id" => 200
            ])->select();

        $this->assertQuery(
            <<<EOD
            SELECT "user_name"
            FROM "demo"
            WHERE
            "user_id" = 200
            EOD,
            $this->database->driver()->queryString
        );
// two
        $this->database->table($this->model->table())->field("user_name(username))")
            ->where([
                "user_id" => 200
            ])->select();

        $this->assertQuery(
            <<<EOD
            SELECT "user_name" AS "username"
            FROM "demo"
            WHERE
            "user_id" = 200
            EOD,
            $this->database->driver()->queryString
        );
//three
        $this->database->table($this->model->table())->field("user_name,user_id")
            ->where([
                "user_id" => 200
            ])->select();

        $this->assertQuery(
            <<<EOD
            SELECT "user_name","user_id"
            FROM "demo"
            WHERE
            "user_id" = 200
            EOD,
            $this->database->driver()->queryString
        );
    }

    /**
     * @covers ::select()
     * @covers ::field()
     * @covers ::where()
     * @covers ::order()
     * @dataProvider typesProvider
     */
    public function testArrayField($type)
    {
        $this->database = $this->setType($type, true);
// one
        $this->database->table($this->model->table())->field([
            "user_name",
            "user_id"
        ])->where([
            "user_id" => 200
        ])->select();

        $this->assertQuery(
            <<<EOD
            SELECT "user_name","user_id"
            FROM "demo"
            WHERE
            "user_id" = 200
            EOD,
            $this->database->driver()->queryString
        );
// two
        $this->database->table($this->model->table())->field([
            "user_id" => [
                "user_name","user_sex"
            ]
        ])->where([
            "user_id" => 200
        ])->select();

        $this->assertQuery(
            <<<EOD
            SELECT "user_id","user_name","user_sex"
            FROM "demo"
            WHERE
            "user_id" = 200
            EOD,
            $this->database->driver()->queryString
        );
    }
}
