<?php

declare(strict_types=1);

namespace App\Config;

/**
 * Settings for the synthetic graph the debug analyzer produces.
 *
 * Both settings have a meaningful default, so these settings always exist: the
 * debug analyzer is never left without them and no caller has to handle their
 * absence.
 */
final class DebugAnalyzerConfig
{
    /**
     * @param int      $depth How many levels deep the generated graph goes
     * @param null|int $seed  Seed making the generated graph reproducible, or null for a fresh one
     */
    public function __construct(
        public readonly int $depth = 5,
        public readonly ?int $seed = null,
    ) {
        assert($this->depth > 0, 'A generated graph depth must be a positive number of levels');
    }

    /**
     * Reads the settings out of configuration data as a source reported it.
     *
     * @param RawConfig $raw The nested "debug" group of the merged configuration
     *
     * @return self The settings, with defaults for whatever the source left unset
     *
     * @throws ConfigException If a setting is present but cannot be read as its type
     */
    public static function fromRaw(RawConfig $raw): self
    {
        return new self(
            depth: $raw->has('depth') ? $raw->requiredPositiveInt('depth') : 5,
            seed: $raw->optionalInt('seed'),
        );
    }
}
