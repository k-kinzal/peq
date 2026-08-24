<?php

declare(strict_types=1);

namespace Tests\Fixture\Source;

use DateTime;

use const PHP_VERSION;

use stdClass;

class MethodBodyClass
{
    /**
     * @return array{object, DateTime|false, string, string}
     */
    public function testMethod(): array
    {
        // Instantiation
        $obj = new stdClass();

        // Static Call
        $date = DateTime::createFromFormat('Y-m-d', '2023-01-01');

        // Const Fetch
        $version = PHP_VERSION;

        // Class Const Fetch
        $atom = DateTime::ATOM;

        return [$obj, $date, $version, $atom];
    }
}
