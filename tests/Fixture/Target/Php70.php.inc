<?php

namespace Tests\Fixture\Target\Php70;

class Ranking
{
    const TIE = 0;

    public function compare(int $left, int $right): int
    {
        return $left <=> $right;
    }

    public function describe(array $scores): string
    {
        $first = $scores[0] ?? self::TIE;

        return (string) $this->compare((int) $first, count($scores));
    }
}
