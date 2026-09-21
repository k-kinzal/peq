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
 * are closed kinds, the PHP version of the analysed sources is one the analyzer is able
 * to read, and the debug settings always exist rather than existing only when the debug
 * analyzer happens to be selected.
 *
 * @phpstan-import-type ConfigFields from ConfigReader
 */
final readonly class Config
{
    /**
     * The largest upper bound a query's quantifier may be written with.
     *
     * GQL leaves this to the implementation (IL018). A walk may revisit what it has
     * crossed, so `-[e]->{1,20}` on a graph with cycles is a great deal of work for a
     * question a reader rarely means; ten steps is far enough for the questions asked
     * in practice. A quantifier with no upper bound is not limited by this, because GQL
     * allows one only under a restrictor that keeps its matches finite.
     */
    public const int HOPS = 10;

    /**
     * @param string              $basePath   The base path for the PHP project to analyze
     * @param Direction           $direction  Which way the dependency graph is read
     * @param null|int            $level      Deepest level to report, or null for the whole graph
     * @param OutputFormat        $output     Which format the report is written in
     * @param list<string>        $includes   File path patterns to include in analysis
     * @param list<string>        $excludes   File path patterns to exclude from analysis
     * @param null|PhpVersion     $phpVersion The PHP version the analysed sources are read as, or
     *                                        null to read them as the version peq runs on
     * @param AnalyzerKind        $analyzer   Which analyzer builds the graph
     * @param int                 $hops       The largest upper bound a query's quantifier may be written with
     * @param DebugAnalyzerConfig $debug      Settings for the synthetic graph of the debug analyzer
     */
    public function __construct(
        public string $basePath,
        public Direction $direction,
        public ?int $level = null,
        public OutputFormat $output = OutputFormat::Tree,
        public array $includes = [],
        public array $excludes = [],
        public ?PhpVersion $phpVersion = null,
        public AnalyzerKind $analyzer = AnalyzerKind::PhpStan,
        public int $hops = self::HOPS,
        public DebugAnalyzerConfig $debug = new DebugAnalyzerConfig(),
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
     * @example A PHP version is read against the analyzer that will read the sources
     *     \App\Config\Config::fromArray([
     *         'basePath' => '/project', 'direction' => 'uses', 'type' => 'native', 'phpVersion' => '5.6',
     *     ])->phpVersion?->toString() // => '5.6'
     * @example The same version with an analyzer that cannot read it is rejected
     *     \App\Config\Config::fromArray([
     *         'basePath' => '/project', 'direction' => 'uses', 'type' => 'phpstan', 'phpVersion' => '5.6',
     *     ]) // throws \App\Config\ConfigException: phpVersion
     *
     * @throws ConfigException If any field is missing or cannot be read as its type
     */
    public static function fromArray(array $array): self
    {
        $raw = new RawConfig($array);
        $analyzer = $raw->oneOf('type', AnalyzerKind::available());

        return new self(
            basePath: $raw->requiredString('basePath'),
            direction: $raw->enum('direction', Direction::class),
            level: $raw->optionalPositiveInt('level'),
            output: $raw->has('output') ? $raw->enum('output', OutputFormat::class) : OutputFormat::Tree,
            includes: $raw->stringList('includes'),
            excludes: $raw->stringList('excludes'),
            phpVersion: self::phpVersionFor($raw, $analyzer),
            analyzer: $analyzer,
            hops: $raw->optionalPositiveInt('hops') ?? self::HOPS,
            debug: DebugAnalyzerConfig::fromRaw($raw->nested('debug')),
        );
    }

    /**
     * Reads the PHP version the sources are analysed as, for the analyzer that reads them.
     *
     * A version is a setting like any other until it meets the analyzer it was chosen
     * with: the analyzer built on PHPStan reads nothing written before PHP 7.1, and a
     * run asking it for an older one would otherwise fail deep inside the analysis
     * with a message about PHPStan's configuration rather than about peq's.
     *
     * @param RawConfig    $raw      The merged configuration data
     * @param AnalyzerKind $analyzer The analyzer that will read the sources
     *
     * @return null|PhpVersion The version, or null when no source named one
     *
     * @throws ConfigException If the version is not one the chosen analyzer reads
     */
    public static function phpVersionFor(RawConfig $raw, AnalyzerKind $analyzer): ?PhpVersion
    {
        $version = $raw->optionalPhpVersion('phpVersion');
        $oldest = $analyzer->oldestPhpVersion();
        if ($version !== null && $version->id < $oldest->id) {
            throw new ConfigException(sprintf(
                'Invalid configuration "phpVersion": the %s analyzer reads sources written for PHP %s and newer, got %s.',
                $analyzer->value,
                $oldest->toString(),
                $version->toString(),
            ));
        }

        return $version;
    }
}
