<?php

declare(strict_types=1);

namespace Tests\Fixture\Analyzer;

use App\Analyzer\Graph\Graph;
use App\Analyzer\PhpStanAnalyzer\PhpStanAnalyzer;

/**
 * The graph peq builds of its own source.
 *
 * Analysing a whole source tree takes seconds, and every check that reads the result
 * would otherwise pay for it again. The graph is therefore built once per process and
 * kept here rather than in a test class, which has nowhere to keep it.
 */
final class SelfAnalysis
{
    /**
     * The graph, once it has been built.
     */
    private static ?Graph $graph = null;

    /**
     * Returns peq's own dependency graph, building it on first use.
     *
     * @return Graph The graph of everything under src/
     */
    public static function graph(): Graph
    {
        return self::$graph ??= (new PhpStanAnalyzer())->analyze(dirname(__DIR__, 3).'/src');
    }
}
