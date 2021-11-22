<?php

namespace Database\Tests;

use Database\Driver;

/**
 * @coversDefaultClass \Database\Connection
 */
class ModelOrderTest extends ModelTestCase
{
    /**
     * @covers ::select()
     * @covers ::field()
     * @covers ::where()
     * @covers ::order()
     * @dataProvider typesProvider
     */
    public function testStringOrder($type)
    {
        $this->database = $this->setType($type, true);
// one
        $this->database->table($this->model->table())->field("user_name")
            ->order('user_name DESC')
            ->where([
                "user_id" => 200
            ])->select();

        $this->assertQuery(
            <<<EOD
            SELECT "user_name"
            FROM "demo"
            WHERE
            "user_id" = 200
            ORDER BY `user_name` DESC
            EOD,
            $this->database->driver()->queryString
        );
// two
        $this->database->table($this->model->table())->field("user_name)")
            ->order('user_name ASC')
            ->where([
                "user_id" => 200
            ])->select();

        $this->assertQuery(
            <<<EOD
            SELECT "user_name"
            FROM "demo"
            WHERE
            "user_id" = 200
            ORDER BY `user_name` ASC
            EOD,
            $this->database->driver()->queryString
        );
//three
        $this->database->table($this->model->table())->field("user_name")
            ->order('user_name')
            ->where([
                "user_id" => 200
            ])->select();

        $this->assertQuery(
            <<<EOD
            SELECT "user_name"
            FROM "demo"
            WHERE
            "user_id" = 200
            ORDER BY `user_name`
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
    public function testArrayOrder($type)
    {
        $this->database = $this->setType($type, true);
// one
        $this->database->table($this->model->table())->field([
            "user_name",
        ])->order(['user_name' => 'ASC'])->where([
            "user_id" => 200
        ])->select();

        $this->assertQuery(
            <<<EOD
            SELECT "user_name"
            FROM "demo"
            WHERE
            "user_id" = 200
            ORDER BY `user_name` ASC
            EOD,
            $this->database->driver()->queryString
        );
// two
        $this->database->table($this->model->table())->field([
            "user_name",
        ])->order([
            'user_name' => [
                'John','Smith','Lucy'
            ],
            'user_id',
            'user_id' => 'ASC'
        ])->where([
            "user_id" => 200
        ])->select();

        $this->assertQuery(
            <<<EOD
            SELECT "user_name"
            FROM "demo"
            WHERE
            "user_id" = 200
            ORDER BY FIELD(`user_name`, 'John','Smith','Lucy'),`user_id`,`user_id` ASC
            EOD,
            $this->database->driver()->queryString
        );
    }
}
