<?php

declare(strict_types=1);

namespace Tests\Fixture\Analyzer;

use App\Analyzer\DebugAnalyzer\DebugAnalyzer;

/**
 * The name of the symbol a seeded generated graph is rooted at.
 *
 * A generated graph is reproducible but its names are invented, so a test that wants
 * to look one up has to ask the generator what it produced. Doing that here keeps the
 * seed and the lookup in one place instead of pinning an invented name into a test
 * that would then break whenever the generator changes.
 */
final class GeneratedSymbol
{
    /**
     * Returns the root symbol name of the standard three-level generated graph.
     *
     * @return string The name of the symbol that graph is rooted at
     */
    public static function rootName(): string
    {
        return self::rootNameAtDepth(3);
    }

    /**
     * Returns the root symbol name of a generated graph of the given depth.
     *
     * @param int $depth How many levels the generated graph goes down
     *
     * @return string The name of the symbol that graph is rooted at
     */
    public static function rootNameAtDepth(int $depth): string
    {
        return (new DebugAnalyzer(seed: 42, depth: $depth))->analyze('/generated')->nodes()[0]->id()->toString();
    }
}
