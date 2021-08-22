<?php
declare(strict_types=1);

namespace Database\Tests;

use PHPUnit\Framework\TestCase;

class BaseTestCase extends TestCase
{
    protected function _expectedQuery($expected): string
    {
        return preg_replace(
            '/(?!\'[^\s]+\s?)"([\p{L}_][\p{L}\p{N}@$#\-_]*)"(?!\s?[^\s]+\')/u',
            '`$1`',
            str_replace(PHP_EOL,' ', $expected)
        );
    }
}