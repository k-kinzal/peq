<?php

declare(strict_types=1);

namespace Tests\Fixture\Config;

use App\Analyzer\Graph\Direction;
use App\Config\AnalyzerKind;
use App\Config\Config;
use App\Config\DebugAnalyzerConfig;

/**
 * Configurations a test can hand to the parts that read one.
 *
 * A configuration has seven settings and most tests care about one of them. Naming
 * the arrangements here keeps each test to the setting it is actually about.
 */
final class SampleConfig
{
    /**
     * A configuration asking for a reproducible generated graph.
     *
     * @param int $depth How many levels the generated graph goes down
     *
     * @return Config The configuration
     */
    public static function generated(int $depth = 3): Config
    {
        return new Config(
            basePath: '.',
            direction: Direction::Uses,
            analyzer: AnalyzerKind::Debug,
            debug: new DebugAnalyzerConfig(depth: $depth, seed: 42),
        );
    }

    /**
     * A configuration asking for real sources under a given path.
     *
     * @param string $basePath The path to analyse
     *
     * @return Config The configuration
     */
    public static function analysing(string $basePath): Config
    {
        return new Config(basePath: $basePath, direction: Direction::Uses, analyzer: AnalyzerKind::PhpStan);
    }
}
