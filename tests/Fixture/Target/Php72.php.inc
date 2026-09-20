<?php

namespace Tests\Fixture\Target\Php72;

class Wrapper
{
    public function wrap(object $value): object
    {
        return (object) ['value' => $value];
    }

    public function describe(object $value): string
    {
        return get_class($this->wrap($value));
    }
}
