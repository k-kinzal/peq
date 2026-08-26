<?php

declare(strict_types=1);

namespace Tests\Fixture\Analyzer;

use App\Analyzer\DebugAnalyzer\DebugAnalyzer;
use App\Analyzer\Graph\Graph;

/**
 * Graphs the debug analyzer draws, and the seeds a contract is checked over.
 *
 * A property of the graph model holds for every graph or for none, so checking one
 * means checking many. The seeds are written down rather than drawn at random: a
 * contract that fails has to fail again the next time it is run, and a run that
 * cannot be repeated says nothing about what to fix.
 */
final class GeneratedGraphs
{
    /**
     * Seeds spread far enough apart to draw unrelated graphs.
     *
     * @var list<int>
     */
    private const SEEDS = [1, 7, 42, 99, 137, 512, 1024, 2718, 3141, 4096, 6553, 8191, 9001, 9973];

    /**
     * Names each seed a contract is checked over.
     *
     * @return iterable<string, array{int}> The seeds, one per case
     */
    public static function seeds(): iterable
    {
        foreach (self::SEEDS as $seed) {
            yield sprintf('seed %d', $seed) => [$seed];
        }
    }

    /**
     * Names each pair of seeds a contract about two graphs is checked over.
     *
     * @return iterable<string, array{int, int}> The pairs, one per case
     */
    public static function seedPairs(): iterable
    {
        $seeds = self::SEEDS;
        foreach ($seeds as $position => $seed) {
            $other = $seeds[($position + 1) % count($seeds)];

            yield sprintf('seeds %d and %d', $seed, $other) => [$seed, $other];
        }
    }

    /**
     * Draws the graph one seed describes.
     *
     * @param int $seed  The seed to draw with
     * @param int $depth How many levels of symbols to draw
     *
     * @return Graph The generated graph
     */
    public static function ofSeed(int $seed, int $depth = 3): Graph
    {
        return (new DebugAnalyzer(seed: $seed, depth: $depth))->analyze('/generated');
    }
}
