<?php

declare(strict_types=1);

namespace App\Config;

/**
 * Orchestrates configuration loading from multiple sources.
 *
 * Readers are consulted in the order they are given and each one overlays what the
 * previous ones reported, which is what makes the documented cascade — defaults,
 * then the configuration file, then the environment, then the command line — hold.
 * The overlay reaches individual settings rather than whole groups, so passing
 * `--debug-seed` on the command line does not discard a `debug.depth` written in
 * the configuration file.
 */
final class ConfigLoader
{
    /**
     * @param list<ConfigReader> $readers The readers to consult, from lowest to highest priority
     */
    public function __construct(
        private readonly array $readers,
    ) {}

    /**
     * Loads and merges configuration from all registered readers.
     *
     * @return Config A configuration whose every field was read as the type it needs to be
     *
     * @throws ConfigException If a reader fails, or if the merged data cannot be read as a configuration
     */
    public function load(): Config
    {
        $merged = [];
        foreach ($this->readers as $reader) {
            $merged = self::overlay($merged, $reader->read());
        }

        return Config::fromArray($merged);
    }

    /**
     * Overlays what one source reported on top of what earlier sources reported.
     *
     * A setting reported by the later source wins. Two groups of named settings are
     * combined setting by setting, because a source that mentions one setting of a
     * group is not saying anything about the rest of it. A list, on the other hand,
     * is one value: a source that reports `excludes` replaces the whole list rather
     * than adding to it, so a narrower configuration can always be narrower.
     *
     * @param array<string, mixed> $base    What earlier sources reported
     * @param array<string, mixed> $overlay What this source reported
     *
     * @return array<string, mixed> The combined data
     */
    public static function overlay(array $base, array $overlay): array
    {
        foreach ($overlay as $key => $value) {
            $existing = $base[$key] ?? null;
            if (self::isNamedGroup($existing) && self::isNamedGroup($value)) {
                $base[$key] = self::overlay($existing, $value);

                continue;
            }
            $base[$key] = $value;
        }

        return $base;
    }

    /**
     * Reports whether a value is a group of named settings rather than a single value.
     *
     * @param mixed $value The value to classify
     *
     * @return bool True when the value is a non-empty array with only string keys
     *
     * @phpstan-assert-if-true array<string, mixed> $value
     */
    public static function isNamedGroup(mixed $value): bool
    {
        if (!is_array($value) || $value === []) {
            return false;
        }

        foreach (array_keys($value) as $key) {
            if (!is_string($key)) {
                return false;
            }
        }

        return true;
    }
}
