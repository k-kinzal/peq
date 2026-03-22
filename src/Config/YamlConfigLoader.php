<?php

declare(strict_types=1);

namespace App\Config;

use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

/**
 * Reads configuration from a YAML file.
 *
 * This reader loads configuration data from a YAML file (typically .peq.yaml)
 * using Symfony's YAML parser. If the file doesn't exist, it returns an empty
 * array, allowing the application to continue with other configuration sources.
 * YAML parsing errors are wrapped in ConfigException for consistent error handling.
 */
final readonly class YamlConfigLoader implements ConfigReader
{
    /**
     * @param string $path Path to the YAML configuration file
     */
    public function __construct(
        private string $path,
    ) {}

    /**
     * {@inheritdoc}
     */
    public function read(): array
    {
        if (!file_exists($this->path)) {
            return [];
        }

        $contents = @file_get_contents($this->path);
        if ($contents === false) {
            throw new ConfigException(sprintf('Failed to read configuration file: %s', $this->path));
        }

        try {
            $config = Yaml::parse($contents);
        } catch (ParseException $e) {
            throw new ConfigException(
                sprintf('Failed to parse YAML configuration file "%s": %s', $this->path, $e->getMessage()),
                $e->getCode(),
                $e
            );
        }

        if (!is_array($config)) {
            throw new ConfigException(
                sprintf(
                    'Invalid configuration format in "%s": Expected array, got %s',
                    $this->path,
                    get_debug_type($config)
                )
            );
        }

        /** @var array<string, mixed> $config */
        return $config;
    }
}
