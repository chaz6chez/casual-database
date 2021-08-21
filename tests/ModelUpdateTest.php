<?php
declare(strict_types=1);

namespace Database\Tests;

use Database\Driver;

/**
 * @coversDefaultClass \Database\Connection
 */
class ModelUpdateTest extends ModelTestCase
{
    /**
     * @covers ::update()
     * @covers ::where()
     * @covers ::table()
     * @dataProvider typesProvider
     */
    public function testUpdate($type)
    {
        $this->database = $this->setType($type, true);

        $objectData = new Foo();

        $this->database->table($this->model->table())->where([
            "user_id[<]" => 1000
        ])->update([
            "type" => "user",
            "age[+]" => 1,
            "level[-]" => 5,
            "score[*]" => 2,
            "lang" => ["en", "fr"],
            "lang [JSON]" => ["en", "fr"],
            "is_locked" => true,
            "uuid" => Driver::raw("UUID()"),
            "object" => $objectData
        ]);

        $this->assertQuery([
            'default' => <<<EOD
                UPDATE "demo"
                SET "type" = 'user',
                "age" = "age" + 1,
                "level" = "level" - 5,
                "score" = "score" * 2,
                "lang" = 'a:2:{i:0;s:2:"en";i:1;s:2:"fr";}',
                "lang" = '["en","fr"]',
                "is_locked" = 1,
                "uuid" = UUID(),
                "object" = 'O:18:"Database\Tests\Foo":1:{s:3:"bar";s:3:"cat";}'
                WHERE "user_id" < 1000
                EOD,
            'mysql' => <<<EOD
                UPDATE "demo"
                SET "type" = 'user',
                "age" = "age" + 1,
                "level" = "level" - 5,
                "score" = "score" * 2,
                "lang" = 'a:2:{i:0;s:2:\"en\";i:1;s:2:\"fr\";}',
                "lang" = '[\"en\",\"fr\"]',
                "is_locked" = 1,
                "uuid" = UUID(),
                "object" = 'O:18:\"Database\Tests\Foo\":1:{s:3:\"bar\";s:3:\"cat\";}'
                WHERE "user_id" < 1000
                EOD,
        ], $this->database->queryString);
    }
}
