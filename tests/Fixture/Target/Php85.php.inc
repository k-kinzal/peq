<?php

namespace Tests\Fixture\Target\Php85;

class Pipeline
{
    #[\NoDiscard]
    public function shout(string $text): string
    {
        return $text |> trim(...) |> strtoupper(...);
    }

    public function greeting(): string
    {
        return $this->shout('  hello  ');
    }
}
