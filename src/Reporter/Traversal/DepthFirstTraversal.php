<?php

declare(strict_types=1);

namespace App\Reporter\Traversal;

use App\Analyzer\Graph\Direction;
use App\Analyzer\Graph\Graph;
use App\Analyzer\Graph\Node;
use App\Analyzer\Graph\NodeId;
use App\Reporter\Traversal;
use Closure;

/**
 * Walks the graph depth first in one direction.
 *
 * Reading the graph away from a symbol and reading it towards one are the same
 * walk over different edge kinds, because the graph records every relation in
 * both directions. The direction is therefore a constructor argument rather than
 * a subclass: `Direction::Uses` follows the relations written in source code and
 * answers "what does this symbol depend on", `Direction::UsedBy` follows their
 * opposite readings and answers "what depends on this symbol".
 */
final class DepthFirstTraversal implements Traversal
{
    /**
     * @param Direction $direction The direction whose edge kinds this traversal follows
     */
    public function __construct(
        private readonly Direction $direction,
    ) {}

    /**
     * Walks the graph from the given symbol, depth first.
     *
     * A node already on the current path is handed to the visitor and not
     * descended into again, so a cyclic dependency terminates the branch instead
     * of the process.
     *
     * @param Graph                    $graph   The dependency graph to traverse
     * @param NodeId<Node>             $symbol  The starting symbol identifier
     * @param Closure(Node, int): bool $visitor Invoked for each visited node
     */
    public function traverse(Graph $graph, NodeId $symbol, Closure $visitor): void
    {
        (new DepthFirstWalk($graph, $this->direction, $visitor))->visit($symbol, 0);
    }

    /**
     * Returns the direction in which this traversal reads the graph.
     *
     * @example The direction is part of the strategy rather than of each call
     *     $reading = \App\Analyzer\Graph\Direction::UsedBy;
     *     (new \App\Reporter\Traversal\DepthFirstTraversal($reading))->direction() // => \App\Analyzer\Graph\Direction::UsedBy
     *
     * @return Direction The direction given to this traversal
     */
    public function direction(): Direction
    {
        return $this->direction;
    }
}
