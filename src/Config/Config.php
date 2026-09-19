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
 * closed type the graph classifies its edges with, the analyzer and the output format
 * are closed kinds, and the debug settings always exist rather than existing only when
 * the debug analyzer happens to be selected.
 *
 * @phpstan-import-type ConfigFields from ConfigReader
 */
final class Config
{
    /**
     * How far a query's repetition goes when it writes no upper bound of its own.
     *
     * A pattern like `-[:call]->{1,}` asks for everything reachable, and on a graph
     * with cycles that is bounded only by the path mode — which makes it finite and
     * not necessarily small. Ten steps is far enough to answer the questions a reader
     * asks in practice and near enough to answer them while they wait.
     */
    public const HOPS = 10;

    /**
     * @param string              $basePath  The base path for the PHP project to analyze
     * @param Direction           $direction Which way the dependency graph is read
     * @param null|int            $level     Deepest level to report, or null for the whole graph
     * @param OutputFormat        $output    Which format the report is written in
     * @param list<string>        $includes  File path patterns to include in analysis
     * @param list<string>        $excludes  File path patterns to exclude from analysis
     * @param AnalyzerKind        $analyzer  Which analyzer builds the graph
     * @param int                 $hops      How far a query's repetition goes when it writes no upper bound
     * @param DebugAnalyzerConfig $debug     Settings for the synthetic graph of the debug analyzer
     */
    public function __construct(
        public readonly string $basePath,
        public readonly Direction $direction,
        public readonly ?int $level = null,
        public readonly OutputFormat $output = OutputFormat::Tree,
        public readonly array $includes = [],
        public readonly array $excludes = [],
        public readonly AnalyzerKind $analyzer = AnalyzerKind::PhpStan,
        public readonly int $hops = self::HOPS,
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
     * The format a report is written in is the one field no caller has to supply. It
     * has an answer that holds everywhere — a person at a terminal — where the base
     * path, the direction and the analyzer each have to be decided, so a configuration
     * that says nothing about it is read as asking for a tree rather than refused.
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
     * @example A configuration that names no format is written as a tree
     *     \App\Config\Config::fromArray([
     *         'basePath' => '/project', 'direction' => 'uses', 'type' => 'native',
     *     ])->output // => \App\Config\OutputFormat::Tree
     * @example A format nothing can be written in is rejected by name
     *     \App\Config\Config::fromArray([
     *         'basePath' => '/project', 'direction' => 'uses', 'output' => 'ascii', 'type' => 'native',
     *     ]) // throws \App\Config\ConfigException: output
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
            output: $raw->has('output') ? $raw->enum('output', OutputFormat::class) : OutputFormat::Tree,
            includes: $raw->stringList('includes'),
            excludes: $raw->stringList('excludes'),
            analyzer: $raw->oneOf('type', AnalyzerKind::available()),
            hops: $raw->optionalPositiveInt('hops') ?? self::HOPS,
            debug: DebugAnalyzerConfig::fromRaw($raw->nested('debug')),
        );
    }
}
