<?php

declare(strict_types=1);

namespace App\Reporter;

use App\Analyzer\Graph\Graph;
use App\Analyzer\Graph\Node;
use App\Analyzer\Graph\NodeId;

/**
 * Interface for graph traversal strategies.
 *
 * Implementations define how to traverse the dependency graph starting from a
 * given symbol. Different strategies can traverse in different directions
 * (e.g., dependencies vs. dependents) or orders.
 */
interface Traversal
{
    /**
     * Traverses the graph starting from the given symbol.
     *
     * @param Graph                                  $graph    The dependency graph to traverse
     * @param NodeId<Node>                           $symbol   The starting symbol identifier
     * @param callable(Node $node, int $depth): bool $callback Callback function invoked for each visited node.
     *                                                         Return true to continue traversal, false to stop.
     */
    public function traverse(Graph $graph, NodeId $symbol, callable $callback): void;
}
