<?php

declare(strict_types=1);

namespace Tests\Unit\Analyzer\PhpStanAnalyzer\Fixture;

class TestClass
{
    public const CONSTANT = 1;

    public function method(): void
    {
        $a = new self();
        $b = self::CONSTANT;
        static::staticMethod();
    }

    public static function staticMethod(): void {}
}
