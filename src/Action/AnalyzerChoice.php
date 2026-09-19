<?php

declare(strict_types=1);

namespace App\Action;

use App\Analyzer\Analyzer;
use App\Analyzer\DebugAnalyzer\DebugAnalyzer;
use App\Analyzer\NativeAnalyzer\NativeAnalyzer;
use App\Analyzer\PhpStanAnalyzer\PhpStanAnalyzer;
use App\Config\AnalyzerKind;
use App\Config\Config;

/**
 * Turning the configured analyzer kind into the analyzer itself.
 *
 * Two use cases now build a graph — inspecting a symbol and querying the graph — and
 * both have to make exactly the same decision from exactly the same settings. Making
 * it twice would be two places to forget a new analyzer in, and the second of them
 * would be found by a reader wondering why one command supports something the other
 * does not.
 *
 * Each arm is handed only what its analyzer needs. An analyzer is told its parameters
 * rather than given the application's configuration to read, which is what keeps the
 * analyzers independent of how peq happens to be configured.
 */
final class AnalyzerChoice
{
    /**
     * Returns the analyzer a configuration asks for.
     *
     * @param Config $config The merged application configuration
     *
     * @example The kind a configuration names is the analyzer it gets
     *     $config = \App\Config\Config::fromArray(['basePath' => '.', 'direction' => 'uses', 'type' => 'native']);
     *     \App\Action\AnalyzerChoice::forConfig($config) instanceof \App\Analyzer\NativeAnalyzer\NativeAnalyzer // => true
     * @example So is the one that builds a graph without reading any sources
     *     $config = \App\Config\Config::fromArray(['basePath' => '.', 'direction' => 'uses', 'type' => 'debug']);
     *     \App\Action\AnalyzerChoice::forConfig($config) instanceof \App\Analyzer\DebugAnalyzer\DebugAnalyzer // => true
     *
     * @return Analyzer The analyzer
     */
    public static function forConfig(Config $config): Analyzer
    {
        return match ($config->analyzer) {
            AnalyzerKind::PhpStan => new PhpStanAnalyzer(
                includes: $config->includes,
                excludes: $config->excludes,
            ),
            AnalyzerKind::Native => new NativeAnalyzer(
                includes: $config->includes,
                excludes: $config->excludes,
            ),
            AnalyzerKind::Debug => new DebugAnalyzer(
                seed: $config->debug->seed,
                depth: $config->debug->depth,
            ),
        };
    }
}
