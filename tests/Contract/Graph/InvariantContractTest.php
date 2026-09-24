<?php

declare(strict_types=1);

namespace Tests\Contract\Graph;

use App\Analyzer\DebugAnalyzer\DebugAnalyzer;
use App\Analyzer\Graph\Edge;
use App\Analyzer\Graph\Graph;
use App\Analyzer\Graph\Node;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(Graph::class)]
#[Large]
final class InvariantContractTest extends TestCase
{
    #[DataProvider('providerGeneratedGraphs')]
    public function testEveryRelationIsReadableFromBothOfItsEnds(Graph $graph): void
    {
        $edges = array_merge([], ...array_map(static fn (Node $node): array => $graph->edges($node->id()), $graph->nodes()));

        self::assertNotSame([], $edges);
        self::assertSame([], array_values(array_filter($edges, static fn (Edge $edge): bool => $graph->edge($edge->to(), $edge->from()) === null)));
    }

    #[DataProvider('providerGeneratedGraphs')]
    public function testNoRelationPointsAtASymbolTheGraphDoesNotHold(Graph $graph): void
    {
        $edges = array_merge([], ...array_map(static fn (Node $node): array => $graph->edges($node->id()), $graph->nodes()));

        self::assertNotSame([], $edges);
        self::assertSame([], array_values(array_filter($edges, static fn (Edge $edge): bool => $graph->node($edge->from()) === null || $graph->node($edge->to()) === null)));
    }

    #[DataProvider('providerGeneratedGraphs')]
    public function testAnIdentifierNamesAtMostOneSymbol(Graph $graph): void
    {
        $names = array_map(static fn (Node $node): string => $node->id()->toString(), $graph->nodes());

        self::assertSame($names, array_values(array_unique($names)));
    }

    #[DataProvider('providerGeneratedGraphs')]
    public function testTheSameRelationIsRecordedAtMostOncePerDirection(Graph $graph): void
    {
        $spelled = array_map(
            static fn (Edge $edge): string => $edge->from()->toString().' -['.$edge->kind()->value.']-> '.$edge->to()->toString(),
            array_merge([], ...array_map(static fn (Node $node): array => $graph->edges($node->id()), $graph->nodes())),
        );

        self::assertSame($spelled, array_values(array_unique($spelled)));
    }

    /**
     * @return iterable<string, array{Graph}>
     */
    public static function providerGeneratedGraphs(): iterable
    {
        yield 'seed 1' => [(new DebugAnalyzer(seed: 1, depth: 3))->analyze('/generated')];

        yield 'seed 7' => [(new DebugAnalyzer(seed: 7, depth: 3))->analyze('/generated')];

        yield 'seed 42' => [(new DebugAnalyzer(seed: 42, depth: 3))->analyze('/generated')];

        yield 'seed 137' => [(new DebugAnalyzer(seed: 137, depth: 3))->analyze('/generated')];

        yield 'seed 1024' => [(new DebugAnalyzer(seed: 1024, depth: 3))->analyze('/generated')];

        yield 'seed 9973' => [(new DebugAnalyzer(seed: 9973, depth: 3))->analyze('/generated')];
    }

    #[DataProvider('providerPairsOfGeneratedGraphs')]
    public function testMergeKeepsEverySymbolAndRelationOfBothGraphs(Graph $first, Graph $second): void
    {
        $merged = $first->merge($second);
        $names = static fn (Graph $graph): array => array_map(static fn (Node $node): string => $node->id()->toString(), $graph->nodes());
        $relations = static fn (Graph $graph): array => array_map(static fn (Edge $edge): string => $edge->from()->toString().' -['.$edge->kind()->value.']-> '.$edge->to()->toString(), $graph->forwardEdges());

        self::assertSame([], array_values(array_diff(array_merge($names($first), $names($second)), $names($merged))));
        self::assertSame([], array_values(array_diff(array_merge($relations($first), $relations($second)), $relations($merged))));
        self::assertSame($relations($merged), array_values(array_unique($relations($merged))));
    }

    /**
     * @return iterable<string, array{Graph, Graph}>
     */
    public static function providerPairsOfGeneratedGraphs(): iterable
    {
        yield 'seeds 1 and 7' => [(new DebugAnalyzer(seed: 1, depth: 2))->analyze('/generated'), (new DebugAnalyzer(seed: 7, depth: 2))->analyze('/generated')];

        yield 'seeds 42 and 137' => [(new DebugAnalyzer(seed: 42, depth: 2))->analyze('/generated'), (new DebugAnalyzer(seed: 137, depth: 2))->analyze('/generated')];

        yield 'seed 1024 with itself' => [(new DebugAnalyzer(seed: 1024, depth: 2))->analyze('/generated'), (new DebugAnalyzer(seed: 1024, depth: 2))->analyze('/generated')];
    }
}
