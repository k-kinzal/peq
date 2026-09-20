<?php

namespace Tests\Fixture\Target\Php83;

interface Renderer
{
    public function render(): string;
}

final class Banner implements Renderer
{
    public const string PREFIX = '## ';

    public const int WIDTH = 40;

    #[\Override]
    public function render(): string
    {
        return str_pad(self::PREFIX, self::{'WIDTH'});
    }
}
