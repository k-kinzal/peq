<?php

declare(strict_types=1);

namespace Tests\Contract\Reporter;

use App\Analyzer\DebugAnalyzer\DebugAnalyzer;
use App\Analyzer\Graph\Edge;
use App\Analyzer\Graph\EdgeKind;
use App\Analyzer\Graph\Graph;
use App\Analyzer\Graph\Node;
use App\Analyzer\Graph\NodeId;
use App\Reporter\Traversal;
use App\Reporter\Traversal\DependencyTraversal;
use App\Reporter\Traversal\DependsTraversal;
use Eris\Generator;
use Eris\TestTrait;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 *
 * Property-based contract tests for the Traversal layer.
 *
 * Verifies three properties over randomly generated graphs:
 * 1. Edge partition: each traversal direction follows only its designated edge kinds
 * 2. Termination: both traversals terminate on all graphs including cyclic ones
 * 3. Symmetry: forward edge A→B implies DepTraversal(A) visits B ∧ DependsTraversal(B) visits A
 */
final class TraversalContractTest extends TestCase
{
    use TestTrait;

    #[Test]
    public function testEdgePartitionProperty(): void
    {
        $this->forAll(Generator\choose(1, 10000))
            ->then(function (int $seed): void {
                $graph = (new DebugAnalyzer(seed: $seed, depth: 3))->analyze('/fake');
                $nodes = $graph->nodes();
                if (count($nodes) === 0) {
                    return;
                }

                // Pick a deterministic subset of start nodes
                mt_srand($seed);
                $startIndices = [0];
                if (count($nodes) > 1) {
                    $startIndices[] = mt_rand(0, count($nodes) - 1);
                }

                foreach ($startIndices as $idx) {
                    $startNode = $nodes[$idx];

                    $depEdges = self::collectFollowedEdges($graph, $startNode->id(), new DependencyTraversal());
                    foreach ($depEdges as $edge) {
                        self::assertNotSame(
                            EdgeKind::UsedBy,
                            $edge->kind(),
                            sprintf(
                                'DependencyTraversal followed UsedBy edge from %s to %s (seed=%d)',
                                $edge->from()->toString(),
                                $edge->to()->toString(),
                                $seed,
                            ),
                        );
                        self::assertNotSame(
                            EdgeKind::DeclaredIn,
                            $edge->kind(),
                            sprintf(
                                'DependencyTraversal followed DeclaredIn edge from %s to %s (seed=%d)',
                                $edge->from()->toString(),
                                $edge->to()->toString(),
                                $seed,
                            ),
                        );
                    }

                    $dependsEdges = self::collectFollowedEdges($graph, $startNode->id(), new DependsTraversal());
                    foreach ($dependsEdges as $edge) {
                        self::assertContains(
                            $edge->kind(),
                            [EdgeKind::UsedBy, EdgeKind::DeclaredIn],
                            sprintf(
                                'DependsTraversal followed non-reverse edge %s from %s to %s (seed=%d)',
                                $edge->kind()->value,
                                $edge->from()->toString(),
                                $edge->to()->toString(),
                                $seed,
                            ),
                        );
                    }
                }
            })
        ;
    }

    #[Test]
    public function testTerminationProperty(): void
    {
        $this->forAll(Generator\choose(1, 10000))
            ->then(function (int $seed): void {
                $graph = (new DebugAnalyzer(seed: $seed, depth: 3))->analyze('/fake');
                $nodes = $graph->nodes();
                if (count($nodes) === 0) {
                    return;
                }

                mt_srand($seed);
                $startIndices = [0];
                if (count($nodes) > 1) {
                    $startIndices[] = mt_rand(0, count($nodes) - 1);
                }

                $nodeCount = count($nodes);
                $bound = $nodeCount * $nodeCount;

                foreach ($startIndices as $idx) {
                    $startNode = $nodes[$idx];

                    $depCount = 0;
                    (new DependencyTraversal())->traverse(
                        $graph,
                        $startNode->id(),
                        function () use (&$depCount, $bound, $seed): bool {
                            ++$depCount;
                            self::assertLessThanOrEqual(
                                $bound,
                                $depCount,
                                sprintf('DependencyTraversal exceeded n^2 visits (seed=%d)', $seed),
                            );

                            return true;
                        },
                    );

                    $dependsCount = 0;
                    (new DependsTraversal())->traverse(
                        $graph,
                        $startNode->id(),
                        function () use (&$dependsCount, $bound, $seed): bool {
                            ++$dependsCount;
                            self::assertLessThanOrEqual(
                                $bound,
                                $dependsCount,
                                sprintf('DependsTraversal exceeded n^2 visits (seed=%d)', $seed),
                            );

                            return true;
                        },
                    );
                }
            })
        ;
    }

    #[Test]
    public function testSymmetryProperty(): void
    {
        $this->forAll(Generator\choose(1, 10000))
            ->then(function (int $seed): void {
                $graph = (new DebugAnalyzer(seed: $seed, depth: 3))->analyze('/fake');

                // Collect all forward edges
                $forwardEdges = [];
                foreach ($graph->nodes() as $node) {
                    foreach ($graph->edges($node->id()) as $edge) {
                        if ($edge->kind() !== EdgeKind::UsedBy && $edge->kind() !== EdgeKind::DeclaredIn) {
                            $forwardEdges[] = $edge;
                        }
                    }
                }

                if (count($forwardEdges) === 0) {
                    return;
                }

                // Sample up to 10 edges to keep test time bounded
                mt_srand($seed);
                $indices = range(0, count($forwardEdges) - 1);

                self::fisherYatesShuffle($indices);
                $sampleIndices = array_slice($indices, 0, min(10, count($indices)));

                foreach ($sampleIndices as $i) {
                    $edge = $forwardEdges[$i];

                    // Forward: DependencyTraversal from edge->from() visits edge->to()
                    $depVisited = self::collectVisitedIds($graph, $edge->from(), new DependencyTraversal());
                    self::assertContains(
                        $edge->to()->toString(),
                        $depVisited,
                        sprintf(
                            'DependencyTraversal from %s should visit %s via %s (seed=%d)',
                            $edge->from()->toString(),
                            $edge->to()->toString(),
                            $edge->kind()->value,
                            $seed,
                        ),
                    );

                    // Reverse: DependsTraversal from edge->to() visits edge->from()
                    $dependsVisited = self::collectVisitedIds($graph, $edge->to(), new DependsTraversal());
                    self::assertContains(
                        $edge->from()->toString(),
                        $dependsVisited,
                        sprintf(
                            'DependsTraversal from %s should visit %s via inverse of %s (seed=%d)',
                            $edge->to()->toString(),
                            $edge->from()->toString(),
                            $edge->kind()->value,
                            $seed,
                        ),
                    );
                }
            })
        ;
    }

    /**
     * Fisher-Yates shuffle using mt_rand for PHP 8.1 compatibility.
     *
     * @param array<int, int> $array
     */
    private static function fisherYatesShuffle(array &$array): void
    {
        for ($i = count($array) - 1; $i > 0; --$i) {
            $j = mt_rand(0, $i);
            [$array[$i], $array[$j]] = [$array[$j], $array[$i]];
        }
    }

    /**
     * Collects edges that a traversal follows by tracking parent→child transitions.
     *
     * @param NodeId<Node> $start
     *
     * @return list<Edge>
     */
    private static function collectFollowedEdges(Graph $graph, NodeId $start, Traversal $traversal): array
    {
        /** @var array<int, NodeId<Node>> $parentStack */
        $parentStack = [];

        /** @var list<array{NodeId<Node>, NodeId<Node>}> $transitions */
        $transitions = [];

        $traversal->traverse($graph, $start, function (Node $node, int $depth) use (&$parentStack, &$transitions): bool {
            if ($depth > 0 && isset($parentStack[$depth - 1])) {
                $transitions[] = [$parentStack[$depth - 1], $node->id()];
            }
            $parentStack[$depth] = $node->id();

            return true;
        });

        $followedEdges = [];
        foreach ($transitions as [$fromId, $toId]) {
            $edge = $graph->edge($fromId, $toId);
            if ($edge !== null) {
                $followedEdges[] = $edge;
            }
        }

        return $followedEdges;
    }

    /**
     * @param NodeId<Node> $start
     *
     * @return list<string>
     */
    private static function collectVisitedIds(Graph $graph, NodeId $start, Traversal $traversal): array
    {
        $visited = [];
        $traversal->traverse($graph, $start, function (Node $node, int $depth) use (&$visited): bool {
            $visited[] = $node->id()->toString();

            return true;
        });

        return $visited;
    }
}
