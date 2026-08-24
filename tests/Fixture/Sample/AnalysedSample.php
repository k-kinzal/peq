<?php

declare(strict_types=1);

namespace Tests\Fixture\Sample;

class AnalysedSample
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
