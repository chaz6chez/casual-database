<?php
declare(strict_types=1);

namespace Database\Tools;

use stdClass;

class Raw extends stdClass
{
    /**
     * The array of mapping data for the raw string.
     *
     * @var array
     */
    public $map;

    /**
     * The raw string.
     *
     * @var string
     */
    public $value;
}