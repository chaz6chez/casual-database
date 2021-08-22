<?php
declare(strict_types=1);

namespace Database\Tests;

/**
 * @coversDefaultClass \Database\Driver
 */
class DeleteTest extends DriverTestCase
{
    /**
     * @covers ::delete()
     * @dataProvider typesProvider
     */
    public function testDelete($type)
    {
        $this->setType($type);

        $this->database->delete("account", [
            "AND" => [
                "type" => "business",
                "age[<]" => 18
            ]
        ]);

        $this->assertQuery(
            <<<EOD
            DELETE FROM `account`
            WHERE (`type` = 'business' AND `age` < 18)
            EOD,
            $this->database->queryString
        );
    }
}
