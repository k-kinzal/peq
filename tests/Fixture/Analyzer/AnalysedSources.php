<?php

declare(strict_types=1);

namespace Tests\Fixture\Analyzer;

use App\Analyzer\Graph\Graph;

/**
 * Every source the tests can have peq read, in one graph.
 *
 * A check that must meet every kind of relation cannot be pointed at a single tree.
 * peq's own source holds whichever relations its design happens to call for and stops
 * holding one the moment a class is refactored, while the sample sources are written
 * to exercise constructs peq's own code has no reason to contain. Joining the two
 * leaves the check depending on neither: it asks the oracle about the relations that
 * exist anywhere the tests can see, rather than about the ones a chosen tree has today.
 */
final class AnalysedSources
{
    /**
     * The joined graph, once it has been built.
     */
    private static ?Graph $graph = null;

    /**
     * Returns the graph of peq's own source together with the sample sources.
     *
     * @return Graph The graph of everything under src/ and tests/Fixture/Source
     */
    public static function everything(): Graph
    {
        return self::$graph ??= SelfAnalysis::graph()->merge(AnalysedFixture::sourceTree());
    }
}
