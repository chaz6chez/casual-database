<?php
declare(strict_types=1);

namespace Database\Tests;

/**
 * @coversDefaultClass \Database\Connection
 */
class ModelDeleteTest extends ModelTestCase
{
    /**
     * @covers ::delete()
     * @covers ::where()
     * @covers ::table()
     * @dataProvider typesProvider
     */
    public function testDelete($type)
    {
        $this->database = $this->setType($type, true);

        $this->database->table($this->model->table())->where([
            "AND" => [
                "type" => "business",
                "age[<]" => 18
            ]
        ])->delete();

        $this->assertQuery(
            <<<EOD
            DELETE FROM "demo"
            WHERE ("type" = 'business' AND "age" < 18)
            EOD,
            $this->database->driver()->queryString
        );
    }
}
