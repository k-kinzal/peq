<?php

declare(strict_types=1);

namespace Tests\Unit\Analyzer\ExperimentAnalyzer\DataFlow;

use App\Analyzer\ExperimentAnalyzer\DataFlow\DependencyGraph;
use App\Analyzer\ExperimentAnalyzer\DataFlow\Occurrence;
use PHPUnit\Framework\Attributes\CoversNamespace;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversNamespace('App\Analyzer\ExperimentAnalyzer')]
final class SliceTest extends TestCase
{
    public function testOfTraversesTheSameCycleInBothDirectionsWithABound(): void
    {
        $graph = new DependencyGraph('f', '/f.php', '');
        $graph->nodes['a'] = new Occurrence('a', 'read', '$a', 1, 1, 1, '$a');
        $graph->nodes['b'] = new Occurrence('b', 'write', '$b', 2, 1, 2, '$b');
        $graph->nodes['c'] = new Occurrence('c', 'read', '$c', 3, 1, 3, '$c');
        $graph->connect('a', 'b');
        $graph->connect('b', 'c');
        $graph->connect('c', 'a');
        $forward = \App\Analyzer\ExperimentAnalyzer\DataFlow\Slice::of($graph, ['a'], \App\Analyzer\Graph\Direction::Uses, 1);
        $reverse = \App\Analyzer\ExperimentAnalyzer\DataFlow\Slice::of($graph, ['a'], \App\Analyzer\Graph\Direction::UsedBy, 1);
        self::assertSame(['a', 'b'], array_map(static fn (Occurrence $node): string => $node->id, $forward->nodes));
        self::assertSame(['a', 'c'], array_map(static fn (Occurrence $node): string => $node->id, $reverse->nodes));
        self::assertCount(1, $forward->edges);
        self::assertCount(1, $reverse->edges);
    }

    public function testOfPreservesCompleteCyclesAndEdgeMetadataWithinTheRequestedDepth(): void
    {
        $graph = new DependencyGraph('f', '/f.php', '');
        $graph->nodes['a'] = new Occurrence('a', 'read', '$a', 1, 1, 1, '$a');
        $graph->nodes['b'] = new Occurrence('b', 'write', '$b', 2, 1, 2, '$b');
        $graph->nodes['c'] = new Occurrence('c', 'read', '$c', 3, 1, 3, '$c');
        $graph->connect('a', 'b', 'control', 'truthy');
        $graph->connect('b', 'c');
        $graph->connect('c', 'a');
        $graph->diagnostics['boundary'] = 'Boundary.';
        $limited = \App\Analyzer\ExperimentAnalyzer\DataFlow\Slice::of($graph, ['a'], \App\Analyzer\Graph\Direction::Uses, 2);
        self::assertSame(['a', 'b', 'c'], array_map(static fn (Occurrence $node): string => $node->id, $limited->nodes));
        self::assertCount(2, $limited->edges);
        $reverse = \App\Analyzer\ExperimentAnalyzer\DataFlow\Slice::of($graph, ['b'], \App\Analyzer\Graph\Direction::UsedBy, null);
        self::assertSame([
            ['b', 'a', 'control', 'truthy'], ['a', 'c', 'data', null], ['c', 'b', 'data', null],
        ], array_map(static fn (\App\Analyzer\ExperimentAnalyzer\DataFlow\Dependency $edge): array => [$edge->from, $edge->to, $edge->kind, $edge->branch], $reverse->edges));
        self::assertSame(['Boundary.'], $reverse->diagnostics);
        self::assertSame('f', $reverse->target);
        self::assertSame('/f.php', $reverse->file);
        self::assertSame(['b'], $reverse->roots);
    }
}
