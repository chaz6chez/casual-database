<?php
declare(strict_types=1);

namespace Database\Tests;

use Database\Exceptions\DatabaseInvalidArgumentException;

/**
 * @coversDefaultClass \Database\Connection
 */
class ModelReplaceTest extends ModelTestCase
{
    /**
     * @covers ::replace()
     * @covers ::table()
     * @covers ::where()
     * @dataProvider typesProvider
     */
    public function testReplace($type)
    {
        $this->database = $this->setType($type,true);

        $this->database->table($this->model->table())->where([
            "user_id[>]" => 1000
        ])->replace([
            "type" => [
                "user" => "new_user",
                "business" => "new_business"
            ],
            "column" => [
                "old_value" => "new_value"
            ]
        ]);

        $this->assertQuery(
            <<<EOD
            UPDATE "demo"
            SET "type" = REPLACE("type", 'user', 'new_user'),
            "type" = REPLACE("type", 'business', 'new_business'),
            "column" = REPLACE("column", 'old_value', 'new_value')
            WHERE "user_id" > 1000
            EOD,
            $this->database->driver()->queryString
        );
    }

    /**
     * @covers ::replace()
     * @dataProvider typesProvider
     */
    public function testReplaceEmptyColumns($type)
    {
        $this->expectException(DatabaseInvalidArgumentException::class);

        $this->database = $this->setType($type,true);
        $this->database->table($this->model->table())->where([
            "user_id[>]" => 1000
        ])->replace([]);
    }
}
