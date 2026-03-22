<?php

declare(strict_types=1);

namespace Tests\App\Config;

use App\Config\ConfigReader;

final readonly class StubConfigReader implements ConfigReader
{
    /**
     * @param array<string, mixed> $data
     */
    public function __construct(private array $data) {}

    /**
     * @return array<string, mixed>
     */
    public function read(): array
    {
        return $this->data;
    }
}
