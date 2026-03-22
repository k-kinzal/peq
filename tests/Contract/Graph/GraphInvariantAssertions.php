<?php

declare(strict_types=1);

namespace Tests\Contract\Graph;

use App\Analyzer\Graph\Edge;
use App\Analyzer\Graph\Graph;
use App\Analyzer\Graph\Node;

/**
 * @internal
 *
 * Reusable graph invariant assertions for contract tests.
 *
 * These assertions verify the structural invariants that every Graph instance
 * must satisfy, regardless of which analyzer produced it.
 */
trait GraphInvariantAssertions
{
    private static function assertBidirectional(Graph $graph): void
    {
        foreach (self::allEdges($graph) as $edge) {
            self::assertNotNull(
                $graph->edge($edge->to(), $edge->from()),
                sprintf(
                    'Edge %s →[%s]→ %s has no inverse',
                    $edge->from()->toString(),
                    $edge->kind()->value,
                    $edge->to()->toString()
                )
            );
        }
    }

    private static function assertEndpointsExist(Graph $graph): void
    {
        foreach (self::allEdges($graph) as $edge) {
            self::assertNotNull(
                $graph->node($edge->from()),
                "from-node {$edge->from()->toString()} not in graph"
            );
            self::assertNotNull(
                $graph->node($edge->to()),
                "to-node {$edge->to()->toString()} not in graph"
            );
        }
    }

    private static function assertNodeUniqueness(Graph $graph): void
    {
        $ids = array_map(fn (Node $n) => $n->id()->toString(), $graph->nodes());
        self::assertSame(count($ids), count(array_unique($ids)), 'Duplicate node IDs');
    }

    private static function assertNoEdgeDuplicates(Graph $graph): void
    {
        foreach ($graph->nodes() as $node) {
            $seen = [];
            foreach ($graph->edges($node->id()) as $edge) {
                $key = $edge->from()->toString().'→'.$edge->kind()->value.'→'.$edge->to()->toString();
                self::assertArrayNotHasKey(
                    $key,
                    $seen,
                    sprintf('Duplicate edge: %s', $key),
                );
                $seen[$key] = true;
            }
        }
    }

    private static function assertAllNodesPreserved(Graph $source, Graph $target): void
    {
        foreach ($source->nodes() as $node) {
            self::assertNotNull(
                $target->node($node->id()),
                "Node {$node->id()->toString()} missing from target graph"
            );
        }
    }

    /** @return list<Edge> */
    private static function allEdges(Graph $graph): array
    {
        return array_values(array_merge(...array_map(
            fn (Node $node) => $graph->edges($node->id()),
            $graph->nodes()
        )));
    }
}
