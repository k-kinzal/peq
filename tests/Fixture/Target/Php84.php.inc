<?php

namespace Tests\Fixture\Target\Php84;

class Slug
{
    public private(set) string $source = '';

    public string $value {
        get => strtolower($this->source);
    }

    public function of(string $source): string
    {
        $this->source = $source;

        return new Slug()->value;
    }
}
