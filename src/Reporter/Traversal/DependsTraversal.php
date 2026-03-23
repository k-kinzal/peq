<?php

declare(strict_types=1);

namespace App\Reporter\Traversal;

use App\Analyzer\Graph\EdgeKind;
use App\Analyzer\Graph\Graph;
use App\Analyzer\Graph\NodeId;
use App\Reporter\Traversal;

/**
 * Traverses the graph by following dependents (incoming edges).
 *
 * This traversal strategy visits nodes that depend on the starting symbol ("used-by").
 * It follows reverse edges (UsedBy, DeclaredIn) to find usages of the symbol.
 * It detects and handles recursion to prevent infinite loops.
 */
final class DependsTraversal implements Traversal
{
    public function isTraversableEdge(EdgeKind $kind): bool
    {
        return $kind === EdgeKind::UsedBy || $kind === EdgeKind::DeclaredIn;
    }

    /**
     * {@inheritdoc}
     */
    public function traverse(Graph $graph, NodeId $symbol, callable $callback): void
    {
        /** @var array<string, true> */
        $pathSet = [];

        $traverseRecursive = function (NodeId $nodeId, int $depth) use ($graph, $callback, &$traverseRecursive, &$pathSet) {
            $node = $graph->node($nodeId);
            if ($node === null) {
                return;
            }

            $key = $nodeId->toString();
            $isRecursive = isset($pathSet[$key]);

            if ($isRecursive) {
                $callback($node, $depth);

                return;
            }

            $pathSet[$key] = true;

            $shouldContinue = $callback($node, $depth);
            if ($shouldContinue) {
                $visitedChildren = [];
                $edges = $graph->edges($nodeId);
                foreach ($edges as $edge) {
                    $targetKey = $edge->to()->toString();
                    if (
                        ($edge->kind() === EdgeKind::UsedBy
                        || $edge->kind() === EdgeKind::DeclaredIn)
                        && !isset($visitedChildren[$targetKey])
                    ) {
                        $visitedChildren[$targetKey] = true;
                        $traverseRecursive($edge->to(), $depth + 1);
                    }
                }
            }

            unset($pathSet[$key]);
        };

        $traverseRecursive($symbol, 0);
    }
}
