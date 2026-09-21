<?php

declare(strict_types=1);

namespace App\Config;

/**
 * The fork reads native PHP versions; production analyzer selection never opts in.
 */
final class ExperimentalConfigReader implements ConfigReader
{
    /**
     * Reads an optional positive source coordinate with the standard config rules.
     *
     * @throws ConfigException If the coordinate is not a positive integer
     */
    public static function coordinate(InputConfigReader $input, string $name): ?int
    {
        return (new RawConfig([$name => $input->option($name)]))->optionalPositiveInt($name);
    }

    /**
     * @return array{type: string}
     */
    public function read(): array
    {
        return ['type' => 'native'];
    }
}
