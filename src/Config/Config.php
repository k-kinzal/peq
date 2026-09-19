<?php

declare(strict_types=1);

namespace App\Config;

use App\Analyzer\Graph\Direction;

/**
 * Represents the application's configuration settings.
 *
 * This value object holds all configuration parameters for the dependency analysis,
 * including the target path, analysis direction, depth limits, and file filtering rules.
 * Every field is typed as narrowly as its meaning allows: the direction is the same
 * closed type the graph classifies its edges with, the analyzer is a closed kind, and
 * the debug settings always exist rather than existing only when the debug analyzer
 * happens to be selected.
 *
 * @phpstan-import-type ConfigFields from ConfigReader
 */
final class Config
{
    /**
     * @param string              $basePath  The base path for the PHP project to analyze
     * @param Direction           $direction Which way the dependency graph is read
     * @param null|int            $level     Deepest level to report, or null for the whole graph
     * @param list<string>        $includes  File path patterns to include in analysis
     * @param list<string>        $excludes  File path patterns to exclude from analysis
     * @param AnalyzerKind        $analyzer  Which analyzer builds the graph
     * @param DebugAnalyzerConfig $debug     Settings for the synthetic graph of the debug analyzer
     */
    public function __construct(
        public readonly string $basePath,
        public readonly Direction $direction,
        public readonly ?int $level = null,
        public readonly array $includes = [],
        public readonly array $excludes = [],
        public readonly AnalyzerKind $analyzer = AnalyzerKind::PhpStan,
        public readonly DebugAnalyzerConfig $debug = new DebugAnalyzerConfig(),
    ) {
        assert($this->basePath !== '', 'A base path must name a location');
        assert($this->level === null || $this->level > 0, 'A reported level bound must be a positive number of levels');
    }

    /**
     * Builds the configuration from the merged data of every configuration source.
     *
     * Each field is read with the type the application needs it to be, so a value
     * that a source could only carry as text — an environment variable, a console
     * option — arrives here typed, and a value that cannot honestly be read as its
     * type is reported as a configuration error naming the field.
     *
     * @param ConfigFields $array The merged configuration data
     *
     * @return self The configuration
     *
     * @example Every field arrives typed, whatever the source could carry
     *     \App\Config\Config::fromArray([
     *         'basePath' => '/project', 'direction' => 'used-by', 'type' => 'native',
     *     ])->direction // => \App\Analyzer\Graph\Direction::UsedBy
     * @example A direction that names no way of reading the graph is rejected
     *     \App\Config\Config::fromArray([
     *         'basePath' => '/project', 'direction' => 'sideways', 'type' => 'native',
     *     ]) // throws \App\Config\ConfigException: direction
     * @example An analyzer this build does not carry is rejected the same way
     *     \App\Config\Config::fromArray([
     *         'basePath' => '/project', 'direction' => 'uses', 'type' => 'nonesuch',
     *     ]) // throws \App\Config\ConfigException: type
     *
     * @throws ConfigException If any field is missing or cannot be read as its type
     */
    public static function fromArray(array $array): self
    {
        $raw = new RawConfig($array);

        return new self(
            basePath: $raw->requiredString('basePath'),
            direction: $raw->enum('direction', Direction::class),
            level: $raw->optionalPositiveInt('level'),
            includes: $raw->stringList('includes'),
            excludes: $raw->stringList('excludes'),
            analyzer: $raw->oneOf('type', AnalyzerKind::available()),
            debug: DebugAnalyzerConfig::fromRaw($raw->nested('debug')),
        );
    }
}
