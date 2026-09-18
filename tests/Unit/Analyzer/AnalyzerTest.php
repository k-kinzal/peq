<?php

declare(strict_types=1);

namespace Tests\Unit\Analyzer;

use App\Analyzer\Analyzer;
use App\Analyzer\DebugAnalyzer\DebugAnalyzer;
use App\Analyzer\Graph\Edge;
use App\Analyzer\Graph\Node;
use App\Analyzer\PhpStanAnalyzer\PhpStanAnalyzer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(DebugAnalyzer::class)]
#[CoversClass(PhpStanAnalyzer::class)]
#[UsesClass(\App\Analyzer\DebugAnalyzer\Generator\RandomSource::class)]
#[Medium]
final class AnalyzerTest extends TestCase
{
    #[DataProvider('providerEveryAnalyzer')]
    public function testAnalyzeProducesAGraphWhoseRelationsAreReadableBothWays(Analyzer $analyzer, string $path): void
    {
        $graph = $analyzer->analyze($path);
        $edges = array_merge([], ...array_map(static fn (Node $node): array => $graph->edges($node->id()), $graph->nodes()));

        self::assertNotSame([], $edges);
        self::assertSame([], array_values(array_filter($edges, static fn (Edge $edge): bool => $graph->edge($edge->to(), $edge->from()) === null)));
    }

    #[DataProvider('providerEveryAnalyzer')]
    public function testAnalyzeProducesAGraphWithNoDanglingRelation(Analyzer $analyzer, string $path): void
    {
        $graph = $analyzer->analyze($path);
        $edges = array_merge([], ...array_map(static fn (Node $node): array => $graph->edges($node->id()), $graph->nodes()));

        self::assertNotSame([], $edges);
        self::assertSame([], array_values(array_filter($edges, static fn (Edge $edge): bool => $graph->node($edge->from()) === null || $graph->node($edge->to()) === null)));
    }

    #[DataProvider('providerEveryAnalyzer')]
    public function testAnalyzeProducesAGraphWhereAnIdentifierNamesOneSymbol(Analyzer $analyzer, string $path): void
    {
        $names = array_map(static fn (Node $node): string => $node->id()->toString(), $analyzer->analyze($path)->nodes());

        self::assertNotSame([], $names);
        self::assertSame($names, array_values(array_unique($names)));
    }

    /**
     * @return iterable<string, array{Analyzer, string}>
     */
    public static function providerEveryAnalyzer(): iterable
    {
        yield 'the generated graph' => [new DebugAnalyzer(seed: 42, depth: 3), '/generated'];

        yield 'a real source tree' => [
            new PhpStanAnalyzer(),
            dirname(__DIR__, 2).'/Fixture/Sample',
        ];
    }

    public function testAnalyzingAPathWithNoSourcesProducesAnEmptyGraphRatherThanFailing(): void
    {
        $analyzer = new PhpStanAnalyzer();

        self::assertSame([], $analyzer->analyze(__DIR__.'/nonexistent')->nodes());
    }
}
