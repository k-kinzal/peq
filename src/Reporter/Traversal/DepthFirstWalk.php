<?php

declare(strict_types=1);

namespace App\Reporter\Traversal;

use App\Analyzer\Graph\Direction;
use App\Analyzer\Graph\Graph;
use App\Analyzer\Graph\Node;
use App\Analyzer\Graph\NodeId;
use Closure;

/**
 * One depth-first walk in progress.
 *
 * A walk owns the state that only exists while it runs — the chain of nodes
 * currently open above the node being visited — so the traversal strategy itself
 * stays stateless and reusable. Recursion is an ordinary method call rather than
 * a self-referencing closure, which is what makes a single step of the walk
 * observable from a test.
 *
 * @visibility namespace
 */
final class DepthFirstWalk
{
    /**
     * @var array<string, true> The identifiers currently open on the path above the visited node
     */
    private array $openPath = [];

    /**
     * @param Graph                    $graph     The graph being walked
     * @param Direction                $direction The direction whose edge kinds this walk follows
     * @param Closure(Node, int): bool $visitor   Invoked for each visited node; returns true to descend
     */
    public function __construct(
        private readonly Graph $graph,
        private readonly Direction $direction,
        private readonly Closure $visitor,
    ) {}

    /**
     * Visits one node and, unless told otherwise, the nodes it relates to.
     *
     * A node that is already open on the current path is a cycle: it is handed to
     * the visitor so the output can mark it, and not descended into. A node that
     * relates to the same target through several edges is descended into once.
     *
     * @param NodeId<Node> $nodeId The identifier of the node to visit
     * @param int          $depth  How far below the starting symbol this node sits
     */
    public function visit(NodeId $nodeId, int $depth): void
    {
        $node = $this->graph->node($nodeId);
        if ($node === null) {
            return;
        }

        $key = $nodeId->toString();
        if (isset($this->openPath[$key])) {
            ($this->visitor)($node, $depth);

            return;
        }

        $this->openPath[$key] = true;

        if (($this->visitor)($node, $depth)) {
            $descended = [];
            foreach ($this->graph->edges($nodeId) as $edge) {
                $targetKey = $edge->to()->toString();
                if ($edge->kind()->direction() === $this->direction && !isset($descended[$targetKey])) {
                    $descended[$targetKey] = true;
                    $this->visit($edge->to(), $depth + 1);
                }
            }
        }

        unset($this->openPath[$key]);
    }
}
