<?php

declare(strict_types=1);

namespace App\Config;

/**
 * Orchestrates configuration loading from multiple sources.
 *
 * This class implements a strategy pattern for configuration loading, merging
 * data from multiple ConfigReader sources in the order they are provided.
 * Later readers override values from earlier readers, allowing a priority-based
 * configuration cascade (e.g., defaults < file config < environment < CLI args).
 */
final class ConfigLoader
{
    /**
     * @param ConfigReader[] $readers Array of configuration readers to merge, in priority order
     */
    public function __construct(
        private readonly array $readers,
    ) {}

    /**
     * Loads and merges configuration from all registered readers.
     *
     * Iterates through all readers in order, merging their configuration data.
     * Later readers override earlier ones for any conflicting keys. The merged
     * data is then validated and converted to a Config object.
     *
     * @return Config A validated configuration object
     *
     * @throws ConfigException If the merged configuration fails validation
     */
    public function load(): Config
    {
        $config = [];
        foreach ($this->readers as $reader) {
            $config = array_merge($config, $reader->read());
        }

        return Config::fromArray($config);
    }
}
