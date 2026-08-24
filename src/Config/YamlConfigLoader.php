<?php

declare(strict_types=1);

namespace App\Config;

use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

/**
 * Reads configuration from a YAML file.
 *
 * The file is optional: a project that configures peq entirely from the command line
 * or the environment needs none, and an empty one is a file whose contents have not
 * been decided yet. A file that exists and holds something other than named settings
 * is a different matter — that is a mistake worth reporting rather than ignoring.
 */
final class YamlConfigLoader implements ConfigReader
{
    /**
     * @param string $path Path to the YAML configuration file
     */
    public function __construct(
        private readonly string $path,
    ) {}

    /**
     * Reports the configuration the YAML file carries.
     *
     * @return array<string, mixed> The settings the file holds
     *
     * @throws ConfigException If the file cannot be read, cannot be parsed, or does not hold named settings
     */
    public function read(): array
    {
        if (!file_exists($this->path)) {
            return [];
        }

        $parsed = $this->parse($this->contents());
        if ($parsed === null) {
            return [];
        }

        $settings = [];
        foreach ($parsed as $name => $value) {
            if (!is_string($name)) {
                throw new ConfigException(sprintf(
                    'Invalid configuration format in "%s": every setting must be named, got the key %s',
                    $this->path,
                    RawConfig::describe($name),
                ));
            }
            $settings[$name] = $value;
        }

        return $settings;
    }

    /**
     * Reads the file from disk.
     *
     * @return string What the file holds
     *
     * @throws ConfigException If the file cannot be opened or read
     */
    public function contents(): string
    {
        if (!is_readable($this->path)) {
            throw new ConfigException(sprintf('Failed to read configuration file: %s', $this->path));
        }

        $contents = file_get_contents($this->path);
        if ($contents === false) {
            throw new ConfigException(sprintf('Failed to read configuration file: %s', $this->path));
        }

        return $contents;
    }

    /**
     * Parses the file contents into the settings they describe.
     *
     * @param string $contents What the file holds
     *
     * @return null|array<mixed> The parsed settings, or null when the file is empty
     *
     * @throws ConfigException If the contents cannot be parsed, or describe something other than settings
     */
    public function parse(string $contents): ?array
    {
        try {
            $parsed = Yaml::parse($contents);
        } catch (ParseException $failure) {
            throw new ConfigException(
                sprintf('Failed to parse YAML configuration file "%s": %s', $this->path, $failure->getMessage()),
                $failure->getCode(),
                $failure,
            );
        }

        if ($parsed === null) {
            return null;
        }

        if (!is_array($parsed)) {
            throw new ConfigException(sprintf(
                'Invalid configuration format in "%s": Expected array, got %s',
                $this->path,
                get_debug_type($parsed),
            ));
        }

        return $parsed;
    }
}
