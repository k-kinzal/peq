<?php

declare(strict_types=1);

namespace Tests\Fixture\Config;

use App\Config\ConfigReader;

/**
 * A configuration source that reports exactly what it was given.
 *
 * The layering of sources is a rule of its own, separate from how any one source
 * reads its data. This double lets a test state what a source reported without
 * writing a file, setting an environment variable or building a console input.
 */
final class StubConfigReader implements ConfigReader
{
    /**
     * @param array<string, mixed> $data The settings this source reports
     */
    public function __construct(
        private readonly array $data,
    ) {}

    /**
     * Reports the settings this source was given.
     *
     * @return array<string, mixed> The settings
     */
    public function read(): array
    {
        return $this->data;
    }
}
