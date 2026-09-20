<?php

namespace Tests\Fixture\Target\Php81;

enum Suit: string
{
    case Hearts = 'H';
    case Spades = 'S';
}

class Card
{
    public function __construct(public readonly Suit $suit)
    {
    }

    public function isRed(): bool
    {
        return $this->suit === Suit::Hearts;
    }

    public function shout(): never
    {
        throw new \RuntimeException(array_map(strtoupper(...), ['stop'])[0]);
    }
}
