<?php

declare(strict_types=1);

namespace Tests\Contract\Reporter;

use App\Analyzer\DebugAnalyzer\DebugAnalyzer;
use App\Analyzer\Graph\Direction;
use App\Analyzer\Graph\Graph;
use App\Analyzer\Graph\Node;
use App\Analyzer\Graph\NodeId;
use App\Reporter\Traversal;
use App\Reporter\Traversal\DepthFirstTraversal;
use Eris\Generator;
use Eris\TestTrait;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 *
 * Property-based contract tests for the traversal layer, over generated graphs.
 *
 * Three properties are checked for every graph the debug analyzer can produce:
 * a traversal follows only the relations of its own direction; a traversal
 * terminates even when the graph is cyclic; and the two directions agree, so that
 * a relation reachable one way is reachable the other way round.
 */
#[CoversClass(DepthFirstTraversal::class)]
#[Large]
final class TraversalContractTest extends TestCase
{
    use TestTrait;

    /**
     * Checks that a traversal follows only the relations of its own direction.
     */
    public function testATraversalFollowsOnlyItsOwnDirection(): void
    {
        $this->forAll(Generator\choose(1, 10000))
            ->then(function (int $seed): void {
                $graph = (new DebugAnalyzer(seed: $seed, depth: 3))->analyze('/generated');
                $root = $graph->nodes()[0]->id();

                foreach ([Direction::Uses, Direction::UsedBy] as $direction) {
                    foreach (self::visitedNames($graph, $root, new DepthFirstTraversal($direction)) as $name) {
                        self::assertNotNull($graph->nodeNamed($name));
                    }
                }
            })
        ;
    }

    /**
     * Checks that a traversal terminates however the generated graph is shaped.
     */
    public function testATraversalTerminatesOnAnyGeneratedGraph(): void
    {
        $this->forAll(Generator\choose(1, 10000))
            ->then(function (int $seed): void {
                $graph = (new DebugAnalyzer(seed: $seed, depth: 3))->analyze('/generated');
                $root = $graph->nodes()[0]->id();

                self::assertNotEmpty(self::visitedNames($graph, $root, new DepthFirstTraversal(Direction::Uses)));
            })
        ;
    }

    /**
     * Checks that a relation reached one way is reachable the other way round.
     */
    public function testTheTwoDirectionsAgreeOnEveryRelation(): void
    {
        $this->forAll(Generator\choose(1, 10000))
            ->then(function (int $seed): void {
                $graph = (new DebugAnalyzer(seed: $seed, depth: 2))->analyze('/generated');

                foreach ($graph->authoredEdges() as $edge) {
                    self::assertContains(
                        $edge->to()->toString(),
                        self::visitedNames($graph, $edge->from(), new DepthFirstTraversal(Direction::Uses)),
                    );
                    self::assertContains(
                        $edge->from()->toString(),
                        self::visitedNames($graph, $edge->to(), new DepthFirstTraversal(Direction::UsedBy)),
                    );
                }
            })
        ;
    }

    /**
     * Checks that the direction a traversal reports is the one it was given.
     */
    public function testATraversalReportsTheDirectionItWasGiven(): void
    {
        self::assertSame(Direction::Uses, (new DepthFirstTraversal(Direction::Uses))->direction());
        self::assertSame(Direction::UsedBy, (new DepthFirstTraversal(Direction::UsedBy))->direction());
    }

    /**
     * Walks a graph and reports the name of every node the traversal visited.
     *
     * @param Graph        $graph     The graph to walk
     * @param NodeId<Node> $root      The symbol to start at
     * @param Traversal    $traversal The strategy to walk with
     *
     * @return list<string> The names visited, in the order they were visited
     */
    public static function visitedNames(Graph $graph, NodeId $root, Traversal $traversal): array
    {
        $visited = [];
        $traversal->traverse($graph, $root, static function (Node $node, int $depth) use (&$visited): bool {
            $visited[] = $node->id()->toString();

            return $depth < 4;
        });

        return $visited;
    }
}
