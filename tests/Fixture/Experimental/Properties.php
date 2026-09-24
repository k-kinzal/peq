<?php

declare(strict_types=1);

namespace Tests\Fixture\Experimental;

final class Properties
{
    public array $sources = [];

    public function read(bool $enabled): array
    {
        $sources = [];
        if ($enabled) {
            return $this->sources;
        }

        return $sources;
    }
}
