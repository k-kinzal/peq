<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Analyzer\Graph\Edge;
use App\Analyzer\Graph\Graph;
use App\Analyzer\Graph\Node;
use App\Analyzer\PhpStanAnalyzer\PhpStanAnalyzer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(PhpStanAnalyzer::class)]
#[Large]
final class SelfAnalysisTest extends TestCase
{
    #[DataProvider('providerPeqsOwnSource')]
    public function testAnalyzingItsOwnSourceProducesAPopulatedGraph(Graph $graph): void
    {
        self::assertGreaterThan(50, count($graph->nodes()));
    }

    #[DataProvider('providerPeqsOwnSource')]
    public function testTheGraphHoldsTheSymbolsPeqDeclares(Graph $graph): void
    {
        self::assertNotNull($graph->nodeNamed('App\Analyzer\Graph\Graph'));
        self::assertNotNull($graph->nodeNamed('App\Analyzer\Graph\EdgeKind'));
        self::assertNotNull($graph->nodeNamed('App\Analyzer\Graph\NodeKind'));
        self::assertNotNull($graph->nodeNamed('App\Analyzer\PhpStanAnalyzer\PhpStanAnalyzer'));
        self::assertNotNull($graph->nodeNamed('App\Command\InspectCommand'));
        self::assertNotNull($graph->nodeNamed('App\Action\Inspect\InspectAction'));
    }

    #[DataProvider('providerPeqsOwnSource')]
    public function testTheGraphKeepsEveryPromiseOfTheGraphModel(Graph $graph): void
    {
        $edges = array_merge([], ...array_map(static fn (Node $node): array => $graph->edges($node->id()), $graph->nodes()));
        $spell = static fn (Edge $edge): string => $edge->from()->toString().' -['.$edge->kind()->value.']-> '.$edge->to()->toString();
        $names = array_map(static fn (Node $node): string => $node->id()->toString(), $graph->nodes());
        $spelled = array_map($spell, $edges);

        self::assertSame([], array_values(array_map($spell, array_filter($edges, static fn (Edge $edge): bool => $graph->edge($edge->to(), $edge->from()) === null))), 'Relations with no reverse reading');
        self::assertSame([], array_values(array_map($spell, array_filter($edges, static fn (Edge $edge): bool => $graph->node($edge->from()) === null || $graph->node($edge->to()) === null))), 'Relations pointing at a symbol the graph does not hold');
        self::assertSame($names, array_values(array_unique($names)), 'Identifiers naming more than one symbol');
        self::assertSame($spelled, array_values(array_unique($spelled)), 'Relations recorded more than once in one direction');
        self::assertSame($spelled, array_map(static fn (Edge $edge): string => $spell($edge->invert()->invert()), $edges), 'Relations that change when inverted twice');
    }

    /**
     * @return iterable<string, array{Graph}>
     */
    public static function providerPeqsOwnSource(): iterable
    {
        yield 'everything under src' => [(new PhpStanAnalyzer())->analyze(dirname(__DIR__, 2).'/src')];
    }
}
