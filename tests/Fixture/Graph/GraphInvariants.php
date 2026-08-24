<?php

declare(strict_types=1);

namespace Tests\Fixture\Graph;

use App\Analyzer\Graph\Edge;
use App\Analyzer\Graph\Graph;
use App\Analyzer\Graph\Node;
use PHPUnit\Framework\Assert;

/**
 * The structural properties every graph must hold, whoever built it.
 *
 * These are contracts of the graph model rather than of any one analyzer, so they
 * are stated once here and checked against whatever a test can produce: a generated
 * graph, a merged one, or the graph of a real codebase. Keeping them out of the test
 * classes is what lets the same property be asserted from a property-based contract
 * test and from an integration test without either owning it.
 */
final class GraphInvariants
{
    /**
     * Asserts that every relation is readable from both of its endpoints.
     *
     * @param Graph $graph The graph to check
     */
    public static function assertBidirectional(Graph $graph): void
    {
        foreach (self::allEdges($graph) as $edge) {
            Assert::assertNotNull(
                $graph->edge($edge->to(), $edge->from()),
                sprintf(
                    'Edge %s -[%s]-> %s has no inverse',
                    $edge->from()->toString(),
                    $edge->kind()->value,
                    $edge->to()->toString(),
                ),
            );
        }
    }

    /**
     * Asserts that no relation points at a symbol the graph does not hold.
     *
     * @param Graph $graph The graph to check
     */
    public static function assertEndpointsExist(Graph $graph): void
    {
        foreach (self::allEdges($graph) as $edge) {
            Assert::assertNotNull($graph->node($edge->from()), sprintf('from-node %s not in graph', $edge->from()->toString()));
            Assert::assertNotNull($graph->node($edge->to()), sprintf('to-node %s not in graph', $edge->to()->toString()));
        }
    }

    /**
     * Asserts that an identifier names at most one node.
     *
     * @param Graph $graph The graph to check
     */
    public static function assertNodeUniqueness(Graph $graph): void
    {
        $ids = array_map(static fn (Node $node): string => $node->id()->toString(), $graph->nodes());
        Assert::assertSame(count($ids), count(array_unique($ids)), 'Duplicate node identifiers');
    }

    /**
     * Asserts that the same relation is recorded at most once per direction.
     *
     * @param Graph $graph The graph to check
     */
    public static function assertNoEdgeDuplicates(Graph $graph): void
    {
        foreach ($graph->nodes() as $node) {
            $seen = [];
            foreach ($graph->edges($node->id()) as $edge) {
                $key = $edge->from()->toString().' -['.$edge->kind()->value.']-> '.$edge->to()->toString();
                Assert::assertArrayNotHasKey($key, $seen, sprintf('Duplicate edge: %s', $key));
                $seen[$key] = true;
            }
        }
    }

    /**
     * Asserts that a graph holds every node another one holds.
     *
     * @param Graph $source The graph whose nodes must be present
     * @param Graph $target The graph to check
     */
    public static function assertAllNodesPreserved(Graph $source, Graph $target): void
    {
        foreach ($source->nodes() as $node) {
            Assert::assertNotNull(
                $target->node($node->id()),
                sprintf('Node %s missing from target graph', $node->id()->toString()),
            );
        }
    }

    /**
     * Asserts that inverting an edge twice yields the edge it started from.
     *
     * @param Graph $graph The graph to check
     */
    public static function assertInversionRoundTrips(Graph $graph): void
    {
        foreach (self::allEdges($graph) as $edge) {
            Assert::assertSame(
                $edge->kind(),
                $edge->invert()->invert()->kind(),
                sprintf('Inverting %s twice changed its kind', $edge->from()->toString()),
            );
            Assert::assertSame($edge->from()->toString(), $edge->invert()->invert()->from()->toString());
            Assert::assertSame($edge->to()->toString(), $edge->invert()->invert()->to()->toString());
        }
    }

    /**
     * Returns every edge recorded anywhere in the graph.
     *
     * @param Graph $graph The graph to read
     *
     * @return list<Edge> Every edge, in both directions of reading
     */
    public static function allEdges(Graph $graph): array
    {
        $edges = [];
        foreach ($graph->nodes() as $node) {
            foreach ($graph->edges($node->id()) as $edge) {
                $edges[] = $edge;
            }
        }

        return $edges;
    }
}
