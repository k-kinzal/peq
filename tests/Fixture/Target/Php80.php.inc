<?php

namespace Tests\Fixture\Target\Php80;

class Money
{
    public function __construct(private int|float $amount = 0)
    {
    }

    public function label(?Money $other = null): string
    {
        return match (true) {
            $other?->amount === $this->amount => 'same',
            default => str_pad(string: 'different', length: 10),
        };
    }
}
