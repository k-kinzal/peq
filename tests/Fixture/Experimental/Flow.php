<?php

declare(strict_types=1);

namespace Tests\Fixture\Experimental;

final class Flow
{
    public function calculate(int $input, bool $flag): int
    {
        $value = $input;
        if ($flag) {
            $value = 2;
        }
        $copy = $value;
        $value = 9;

        return $copy;
    }

    public function invalid(): void
    {
        global $shared;
    }

    public function referenced(): int
    {
        $value = 1;
        self::replace($value);

        return $value;
    }

    public static function replace(int &$value): void
    {
        $value = 42;
    }
}
