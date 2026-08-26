<?php

declare(strict_types=1);

namespace Tests\Fixture\Reporter;

use App\Analyzer\Graph\Direction;
use App\Analyzer\Graph\Graph;
use App\Analyzer\Graph\Node;
use App\Analyzer\Graph\NodeId;
use App\Reporter\Traversal;
use App\Reporter\Traversal\DepthFirstTraversal;
use PHPUnit\Framework\Assert;

/**
 * The properties every traversal of every graph has to have.
 *
 * A traversal is checked against whole graphs rather than against one arranged
 * case, so the checks are written over what a graph holds rather than over what a
 * particular graph was built to hold. Keeping them here is what lets a test state
 * the property in one call instead of walking the graph itself.
 */
final class TraversalInvariants
{
    /**
     * Asserts that a traversal only ever hands over symbols the graph holds.
     *
     * @param Graph        $graph     The graph to walk
     * @param NodeId<Node> $root      The symbol to start at
     * @param Traversal    $traversal The strategy to walk with
     */
    public static function assertVisitsOnlyKnownSymbols(Graph $graph, NodeId $root, Traversal $traversal): void
    {
        foreach (self::visitedNames($graph, $root, $traversal) as $name) {
            Assert::assertNotNull($graph->nodeNamed($name), sprintf('The traversal visited "%s", which the graph does not hold.', $name));
        }
    }

    /**
     * Asserts that every relation is reachable from both of the symbols it joins.
     *
     * @param Graph $graph The graph to walk
     */
    public static function assertEveryRelationIsReachableBothWays(Graph $graph): void
    {
        foreach ($graph->authoredEdges() as $edge) {
            Assert::assertContains(
                $edge->to()->toString(),
                self::visitedNames($graph, $edge->from(), new DepthFirstTraversal(Direction::Uses)),
            );
            Assert::assertContains(
                $edge->from()->toString(),
                self::visitedNames($graph, $edge->to(), new DepthFirstTraversal(Direction::UsedBy)),
            );
        }
    }

    /**
     * Walks a graph and reports the name of every symbol the traversal visited.
     *
     * @param Graph        $graph     The graph to walk
     * @param NodeId<Node> $root      The symbol to start at
     * @param Traversal    $traversal The strategy to walk with
     *
     * @return list<string> The names visited, in the order they were visited
     */
    public static function visitedNames(Graph $graph, NodeId $root, Traversal $traversal): array
    {
        $log = new VisitLog();
        $traversal->traverse($graph, $root, $log->recorderStoppingBelow(4));

        return $log->names();
    }
}
