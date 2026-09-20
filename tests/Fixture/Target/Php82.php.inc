<?php

namespace Tests\Fixture\Target\Php82;

interface Countable
{
    public function count(): int;
}

interface Named
{
    public function name(): string;
}

trait Described
{
    private const SEPARATOR = ': ';
}

final readonly class Catalogue
{
    use Described;

    public function __construct(private string $title)
    {
    }

    public function describe((Countable&Named)|null $entry): string
    {
        return $entry === null
            ? $this->title
            : $this->title . self::SEPARATOR . $entry->name();
    }
}
