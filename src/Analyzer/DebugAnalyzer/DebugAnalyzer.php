<?php

declare(strict_types=1);

namespace App\Analyzer\DebugAnalyzer;

use App\Analyzer\Analyzer;
use App\Analyzer\DebugAnalyzer\Generator\ClassLikeGraphGenerator;
use App\Analyzer\DebugAnalyzer\Generator\GraphGenerator;
use App\Analyzer\DebugAnalyzer\Generator\LeafGraphGenerator;
use App\Analyzer\DebugAnalyzer\Generator\MemberGraphGenerator;
use App\Analyzer\DebugAnalyzer\Generator\NameGenerator;
use App\Analyzer\DebugAnalyzer\Generator\NodeGenerator;
use App\Analyzer\DebugAnalyzer\Generator\NodeIdGenerator;
use App\Analyzer\DebugAnalyzer\Generator\RandomSource;
use App\Analyzer\Graph\Graph;
use Override;

/**
 * An analyzer that invents a dependency graph instead of reading one.
 *
 * It answers the question "does the rest of the pipeline handle a graph of this
 * shape" without a codebase to point at, which is what makes traversal, depth
 * bounds and tree output exercisable on demand. The path it is given is ignored:
 * nothing is parsed.
 *
 * The graph is drawn from a random source the analyzer seeds and owns, so passing
 * a seed makes the same graph come out every time, on every PHP version — which is
 * what turns a reproduction of a reporting bug into something that can be attached
 * to a report.
 */
final readonly class DebugAnalyzer implements Analyzer
{
    /**
     * @param null|int $seed  Seed making the generated graph reproducible, or null for a fresh one
     * @param int      $depth How many levels of symbols the generated graph goes down
     */
    public function __construct(
        private ?int $seed = null,
        private int $depth = 5,
    ) {
        assert($this->depth > 0, 'A generated graph depth must be a positive number of levels');
    }

    /**
     * Generates a dependency graph.
     *
     * @param string $path Ignored: this analyzer reads no sources
     *
     * @return Graph A generated dependency graph
     */
    #[Override]
    public function analyze(string $path): Graph
    {
        $random = new RandomSource($this->seed ?? random_int(0, PHP_INT_MAX));
        $ids = new NodeIdGenerator(new NameGenerator($random), $random);
        $nodes = new NodeGenerator($ids, $random);

        return (new GraphGenerator(
            classLikes: new ClassLikeGraphGenerator($nodes, $ids, $random),
            members: new MemberGraphGenerator($nodes, $ids, $random),
            leaves: new LeafGraphGenerator($nodes),
            ids: $ids,
            random: $random,
        ))->graph($this->depth);
    }
}
