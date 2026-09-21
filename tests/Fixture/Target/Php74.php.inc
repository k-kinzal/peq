<?php

namespace Tests\Fixture\Target\Php74;

class Totals
{
    private ?int $cached = null;

    /** @var array<int, int> */
    private array $amounts = [];

    public function total(int ...$amounts): int
    {
        $this->amounts = [...$this->amounts, ...$amounts];
        $this->cached ??= 0;

        return array_sum(array_map(fn (int $amount): int => $amount * 2, $this->amounts));
    }
}
