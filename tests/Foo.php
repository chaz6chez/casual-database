<?php
declare(strict_types=1);

namespace Database\Tests;

class Foo
{
    public $bar = "cat";

    public function __wakeup()
    {
        $this->bar = "dog";
    }
}