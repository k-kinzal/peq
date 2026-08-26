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
 *
 * This is the one source whose contents are written by hand and parsed as whatever
 * they happen to describe, so it is where the shape of a setting is checked. Every
 * other source can only report what its own medium can carry.
 *
 * @phpstan-import-type ConfigLeaf from ConfigReader
 * @phpstan-import-type ConfigField from ConfigReader
 * @phpstan-import-type ConfigGroup from ConfigReader
 * @phpstan-import-type ConfigFields from ConfigReader
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
     * @return ConfigFields The settings the file holds
     *
     * @throws ConfigException If the file cannot be read, cannot be parsed, or does not hold named settings
     */
    public function read(): array
    {
        if (!file_exists($this->path)) {
            return [];
        }

        return $this->settings($this->parse($this->contents()));
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
     * Parses the file contents into whatever they describe.
     *
     * What a YAML file describes is decided by what someone wrote in it, so this
     * reports it as it is and leaves judging its shape to settings().
     *
     * @param string $contents What the file holds
     *
     * @return mixed The parsed contents, which are null when the file is empty
     *
     * @throws ConfigException If the contents are not YAML
     */
    public function parse(string $contents): mixed
    {
        try {
            return Yaml::parse($contents);
        } catch (ParseException $failure) {
            throw new ConfigException(
                sprintf('Failed to parse YAML configuration file "%s": %s', $this->path, $failure->getMessage()),
                $failure->getCode(),
                $failure,
            );
        }
    }

    /**
     * Reads parsed contents as the settings a source is allowed to report.
     *
     * An empty file describes no settings rather than an error: it is a file whose
     * contents have not been decided yet.
     *
     * @param mixed $parsed What the file described
     *
     * @return ConfigFields The settings it holds
     *
     * @throws ConfigException If the contents describe something other than named settings
     */
    public function settings(mixed $parsed): array
    {
        if ($parsed === null) {
            return [];
        }
        if (!is_array($parsed)) {
            throw new ConfigException(sprintf(
                'Invalid configuration format in "%s": expected named settings, got %s',
                $this->path,
                get_debug_type($parsed),
            ));
        }

        $settings = [];
        foreach ($parsed as $name => $value) {
            if (!is_string($name)) {
                throw new ConfigException(sprintf(
                    'Invalid configuration format in "%s": every setting must be named, got the key %s',
                    $this->path,
                    get_debug_type($name),
                ));
            }
            $settings[$name] = $this->field($name, $value);
        }

        return $settings;
    }

    /**
     * Reads one parsed value as the setting it is meant to be.
     *
     * A setting is a single value, a list of values, or a group of named values.
     * Anything else — a list of groups, a group nested inside a group — is not
     * something any setting peq knows is written as, so it is reported against the
     * name it was written under rather than carried further as data of no shape.
     *
     * @param string $name  The name the value was written under
     * @param mixed  $value What was written under it
     *
     * @return ConfigField The value, read as a setting
     *
     * @throws ConfigException If the value is not shaped like a setting
     */
    public function field(string $name, mixed $value): array|bool|float|int|string|null
    {
        if (!is_array($value)) {
            return $this->leaf($name, $value);
        }
        if (array_is_list($value)) {
            return $this->values($name, $value);
        }

        return $this->group($name, $value);
    }

    /**
     * Reads a parsed value as a group of named settings.
     *
     * @param string       $name  The name the group was written under
     * @param array<mixed> $value What was written under it
     *
     * @return ConfigGroup The settings it holds
     *
     * @throws ConfigException If a setting in it is unnamed or is itself a group
     */
    public function group(string $name, array $value): array
    {
        $read = [];
        foreach ($value as $key => $item) {
            if (!is_string($key)) {
                throw new ConfigException(sprintf(
                    'Invalid configuration "%s" in "%s": every setting must be named, got the key %s',
                    $name,
                    $this->path,
                    get_debug_type($key),
                ));
            }
            $read[$key] = is_array($item)
                ? $this->values($name.'.'.$key, $item)
                : $this->leaf($name.'.'.$key, $item);
        }

        return $read;
    }

    /**
     * Reads a parsed value as a list of single values.
     *
     * @param string       $name  The name the list was written under
     * @param array<mixed> $value What was written under it
     *
     * @return list<ConfigLeaf> The values it holds
     *
     * @throws ConfigException If it holds anything other than single values
     */
    public function values(string $name, array $value): array
    {
        if (!array_is_list($value)) {
            throw new ConfigException(sprintf(
                'Invalid configuration "%s" in "%s": expected a list of settings, got named entries',
                $name,
                $this->path,
            ));
        }

        $read = [];
        foreach ($value as $item) {
            $read[] = $this->leaf($name, $item);
        }

        return $read;
    }

    /**
     * Reads a parsed value as a single value.
     *
     * @param string $name  The name the value was written under
     * @param mixed  $value What was written under it
     *
     * @return ConfigLeaf The value
     *
     * @throws ConfigException If it is not a single value
     */
    public function leaf(string $name, mixed $value): bool|float|int|string|null
    {
        if ($value !== null && !is_scalar($value)) {
            throw new ConfigException(sprintf(
                'Invalid configuration "%s" in "%s": expected a setting, got %s',
                $name,
                $this->path,
                get_debug_type($value),
            ));
        }

        return $value;
    }
}
