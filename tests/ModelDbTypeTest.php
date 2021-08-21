<?php
declare(strict_types=1);

namespace Database\Tests;

/**
 * @coversDefaultClass \Database\Connection
 */
class ModelDbTypeTest extends ModelTestCase
{
    /**
     * @covers ::insert()
     * @covers ::table()
     * @covers \Database\AbstractModel::table()
     * @dataProvider typesProvider
     */
    public function testMasterInsert($type)
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
     * @covers ::table()
     * @covers \Database\AbstractModel::table()
     * @dataProvider typesProvider
     */
    public function testSlaveInsert($type)
    {
        $this->database = $this->setType($type, false);

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
}