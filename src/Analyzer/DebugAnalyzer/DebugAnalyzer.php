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
use App\Analyzer\Graph\Graph;
use Faker\Factory;

/**
 * An analyzer that invents a dependency graph instead of reading one.
 *
 * It answers the question "does the rest of the pipeline handle a graph of this
 * shape" without a codebase to point at, which is what makes traversal, depth
 * bounds and tree output exercisable on demand. The path it is given is ignored:
 * nothing is parsed.
 *
 * The graph is drawn from a seedable random source, so passing a seed makes the
 * same graph come out every time — which is what turns a reproduction of a
 * reporting bug into something that can be attached to a report.
 */
final class DebugAnalyzer implements Analyzer
{
    /**
     * @param null|int $seed  Seed making the generated graph reproducible, or null for a fresh one
     * @param int      $depth How many levels of symbols the generated graph goes down
     */
    public function __construct(
        private readonly ?int $seed = null,
        private readonly int $depth = 5,
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
    public function analyze(string $path): Graph
    {
        $faker = Factory::create();
        if ($this->seed !== null) {
            $faker->seed($this->seed);
        }

        $ids = new NodeIdGenerator(new NameGenerator($faker), $faker);
        $nodes = new NodeGenerator($ids, $faker);

        return (new GraphGenerator(
            classLikes: new ClassLikeGraphGenerator($nodes, $ids, $faker),
            members: new MemberGraphGenerator($nodes, $ids, $faker),
            leaves: new LeafGraphGenerator($nodes),
            ids: $ids,
            faker: $faker,
        ))->graph($this->depth);
    }
}
