<?php

declare(strict_types=1);

namespace App\Config;

/**
 * Interface for configuration data sources.
 *
 * Implementations of this interface read configuration data from various sources
 * (YAML files, environment variables, CLI input, etc.) and return them as an
 * associative array. The ConfigLoader uses multiple readers in a specific order
 * to build the final merged configuration, with later readers overriding earlier ones.
 */
interface ConfigReader
{
    /**
     * Reads configuration data from the source.
     *
     * Returns an associative array of configuration key-value pairs.
     * Keys should match the property names in the Config class.
     * Returns an empty array if no configuration is available.
     *
     * @return array<string, mixed> Associative array of configuration data
     *
     * @throws ConfigException If reading configuration fails
     */
    public function read(): array;
}
