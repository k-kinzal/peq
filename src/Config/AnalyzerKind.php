<?php

declare(strict_types=1);

namespace App\Config;

/**
 * Which analyzer implementation builds the dependency graph.
 *
 * The kinds are closed, so every place that reacts to the choice — the use case
 * that constructs the analyzer above all — has to name an arm for each of them,
 * and adding a third analyzer is reported at every such place instead of falling
 * through a default.
 */
enum AnalyzerKind: string
{
    /** Builds the graph from real sources, using PHPStan to resolve types */
    case PhpStan = 'phpstan';

    /** Builds the same graph from real sources, reading them directly instead of through PHPStan */
    case Native = 'native';

    /** Builds a synthetic graph, for exercising output without parsing sources */
    case Debug = 'debug';
}
