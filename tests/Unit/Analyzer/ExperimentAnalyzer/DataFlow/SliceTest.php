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
}
