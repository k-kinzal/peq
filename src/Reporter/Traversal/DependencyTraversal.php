<?php

declare(strict_types=1);

namespace App\Reporter\Traversal;

use App\Analyzer\Graph\EdgeKind;
use App\Analyzer\Graph\Graph;
use App\Analyzer\Graph\Node;
use App\Analyzer\Graph\NodeId;
use App\Reporter\Traversal;

/**
 * Traverses the graph by following dependencies (outgoing edges).
 *
 * This traversal strategy visits nodes that the starting symbol depends on ("uses").
 * It follows edges like function calls, property access, class extension, etc.
 * It detects and handles recursion to prevent infinite loops.
 */
final class DependencyTraversal implements Traversal
{
    /** @var \SplStack<NodeId<Node>> */
    private \SplStack $stack;

    public function __construct()
    {
        $this->stack = new \SplStack();
    }

    public function isTraversableEdge(EdgeKind $kind): bool
    {
        return $kind !== EdgeKind::UsedBy && $kind !== EdgeKind::DeclaredIn;
    }

    /**
     * {@inheritdoc}
     */
    public function traverse(Graph $graph, NodeId $symbol, callable $callback): void
    {
        $this->stack = new \SplStack();
        $traverseRecursive = function (NodeId $nodeId, int $depth) use ($graph, $callback, &$traverseRecursive) {
            $node = $graph->node($nodeId);
            if ($node === null) {
                return;
            }

            $isRecursive = false;

            /** @var NodeId<Node> $visitedId */
            foreach ($this->stack as $visitedId) {
                if ($visitedId->toString() === $nodeId->toString()) {
                    $isRecursive = true;

                    break;
                }
            }

            if ($isRecursive) {
                $callback($node, $depth);

                return;
            }

            $this->stack->push($nodeId);

            $shouldContinue = $callback($node, $depth);
            if ($shouldContinue) {
                $visitedChildren = [];
                $edges = $graph->edges($nodeId);
                foreach ($edges as $edge) {
                    $targetKey = $edge->to()->toString();
                    if (
                        $edge->kind() !== EdgeKind::UsedBy
                        && $edge->kind() !== EdgeKind::DeclaredIn
                        && !isset($visitedChildren[$targetKey])
                    ) {
                        $visitedChildren[$targetKey] = true;
                        $traverseRecursive($edge->to(), $depth + 1);
                    }
                }
            }

            $this->stack->pop();
        };

        $traverseRecursive($symbol, 0);
    }
}
