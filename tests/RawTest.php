<?php
declare(strict_types=1);

namespace Database\Tests;

use Database\Driver;

/**
 * @coversDefaultClass \Database\Driver
 */
class RawTest extends DriverTestCase
{
    /**
     * @covers ::raw()
     * @covers ::isRaw()
     * @covers ::buildRaw()
     * @dataProvider typesProvider
     */
    public function testRawWithPlaceholder($type)
    {
        $this->setType($type);

        $this->database->select('account', [
            'score' => Driver::raw('SUM(<age> + <experience>)')
        ]);

        $this->assertQuery(
            <<<EOD
            SELECT SUM("age" + "experience") AS "score"
            FROM "account"
            EOD,
            $this->database->queryString
        );
    }
}
