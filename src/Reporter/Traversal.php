<?php

declare(strict_types=1);

namespace App\Reporter;

use App\Analyzer\Graph\Direction;
use App\Analyzer\Graph\Graph;
use App\Analyzer\Graph\Node;
use App\Analyzer\Graph\NodeId;
use Closure;

/**
 * Interface for graph traversal strategies.
 *
 * A traversal decides which relations it follows out of a node and in what order
 * it hands the nodes it reaches to its visitor. The direction it reads the graph
 * in is part of its identity rather than a per-call argument, so a consumer that
 * needs to know which edges a traversal would follow — a renderer working out
 * whether a node is the last of its siblings, for instance — asks for it once.
 */
interface Traversal
{
    /**
     * Traverses the graph starting from the given symbol.
     *
     * @param Graph                    $graph   The dependency graph to traverse
     * @param NodeId<Node>             $symbol  The starting symbol identifier
     * @param Closure(Node, int): bool $visitor Invoked for each visited node with the node and its
     *                                          depth; returns true to descend into that node, false
     *                                          to stop at it
     */
    public function traverse(Graph $graph, NodeId $symbol, Closure $visitor): void;

    /**
     * Returns the direction in which this traversal reads the graph.
     *
     * @return Direction The direction whose edge kinds this traversal follows
     */
    public function direction(): Direction;
}
