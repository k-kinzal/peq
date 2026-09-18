<?php

declare(strict_types=1);

namespace Tests\Contract\Reporter;

use App\Analyzer\DebugAnalyzer\DebugAnalyzer;
use App\Analyzer\Graph\Direction;
use App\Analyzer\Graph\Edge;
use App\Analyzer\Graph\Graph;
use App\Analyzer\Graph\Node;
use App\Reporter\Traversal\DepthFirstTraversal;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(DepthFirstTraversal::class)]
#[Large]
final class TraversalContractTest extends TestCase
{
    #[DataProvider('providerGeneratedGraphs')]
    public function testTraverseVisitsOnlySymbolsTheGraphHoldsWhenReadingForwards(Graph $graph): void
    {
        $visited = [];
        (new DepthFirstTraversal(Direction::Uses))->traverse($graph, $graph->nodes()[0]->id(), static function (Node $node, int $depth) use (&$visited): bool {
            $visited[] = $node->id()->toString();

            return $depth < 4;
        });

        self::assertNotSame([], $visited);
        self::assertSame([], array_values(array_filter($visited, static fn (string $name): bool => $graph->nodeNamed($name) === null)));
    }

    #[DataProvider('providerGeneratedGraphs')]
    public function testTraverseVisitsOnlySymbolsTheGraphHoldsWhenReadingBackwards(Graph $graph): void
    {
        $visited = [];
        (new DepthFirstTraversal(Direction::UsedBy))->traverse($graph, $graph->nodes()[0]->id(), static function (Node $node, int $depth) use (&$visited): bool {
            $visited[] = $node->id()->toString();

            return $depth < 4;
        });

        self::assertNotSame([], $visited);
        self::assertSame([], array_values(array_filter($visited, static fn (string $name): bool => $graph->nodeNamed($name) === null)));
    }

    #[DataProvider('providerGeneratedGraphs')]
    public function testTraverseReachesEveryRelationFromTheSymbolItStartsAt(Graph $graph): void
    {
        $unreached = array_filter($graph->authoredEdges(), static function (Edge $edge) use ($graph): bool {
            $visited = [];
            (new DepthFirstTraversal(Direction::Uses))->traverse($graph, $edge->from(), static function (Node $node, int $depth) use (&$visited): bool {
                $visited[] = $node->id()->toString();

                return $depth < 1;
            });

            return !in_array($edge->to()->toString(), $visited, true);
        });

        self::assertNotSame([], $graph->authoredEdges());
        self::assertSame([], array_values($unreached));
    }

    #[DataProvider('providerGeneratedGraphs')]
    public function testTraverseReachesEveryRelationFromTheSymbolItPointsAt(Graph $graph): void
    {
        $unreached = array_filter($graph->authoredEdges(), static function (Edge $edge) use ($graph): bool {
            $visited = [];
            (new DepthFirstTraversal(Direction::UsedBy))->traverse($graph, $edge->to(), static function (Node $node, int $depth) use (&$visited): bool {
                $visited[] = $node->id()->toString();

                return $depth < 1;
            });

            return !in_array($edge->from()->toString(), $visited, true);
        });

        self::assertNotSame([], $graph->authoredEdges());
        self::assertSame([], array_values($unreached));
    }

    /**
     * @return iterable<string, array{Graph}>
     */
    public static function providerGeneratedGraphs(): iterable
    {
        yield 'seed 1' => [(new DebugAnalyzer(seed: 1, depth: 2))->analyze('/generated')];

        yield 'seed 7' => [(new DebugAnalyzer(seed: 7, depth: 2))->analyze('/generated')];

        yield 'seed 42' => [(new DebugAnalyzer(seed: 42, depth: 2))->analyze('/generated')];

        yield 'seed 1024' => [(new DebugAnalyzer(seed: 1024, depth: 2))->analyze('/generated')];
    }
}
