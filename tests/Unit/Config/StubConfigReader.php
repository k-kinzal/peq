<?php

declare(strict_types=1);

namespace Tests\Unit\Config;

use App\Config\ConfigReader;

final class StubConfigReader implements ConfigReader
{
    /**
     * @param array<string, mixed> $data
     */
    public function __construct(private readonly array $data) {}

    /**
     * @return array<string, mixed>
     */
    public function read(): array
    {
        return $this->data;
    }
}
