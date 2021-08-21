<?php
declare(strict_types=1);

namespace Database\Tests;

/**
 * @coversDefaultClass \Database\Driver
 */
class DropTest extends DriverTestCase
{
    /**
     * @covers ::drop()
     * @dataProvider typesProvider
     */
    public function testDrop($type)
    {
        $this->setType($type);

        $this->database->drop("account");

        $this->assertQuery(
            <<<EOD
            DROP TABLE IF EXISTS "account"
            EOD,
            $this->database->queryString
        );
    }
}
