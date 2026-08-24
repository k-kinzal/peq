<?php

declare(strict_types=1);

namespace Tests\Unit\Analyzer;

use App\Analyzer\Analyzer;
use App\Analyzer\DebugAnalyzer\DebugAnalyzer;
use App\Analyzer\PhpStanAnalyzer\PhpStanAnalyzer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use Tests\Fixture\Graph\GraphInvariants;

/**
 * @internal
 */
#[CoversClass(DebugAnalyzer::class)]
#[CoversClass(PhpStanAnalyzer::class)]
#[Medium]
final class AnalyzerTest extends TestCase
{
    #[DataProvider('providerEveryAnalyzer')]
    public function testAnalyzeProducesAGraphWhoseRelationsAreReadableBothWays(Analyzer $analyzer, string $path): void
    {
        GraphInvariants::assertBidirectional($analyzer->analyze($path));
    }

    #[DataProvider('providerEveryAnalyzer')]
    public function testAnalyzeProducesAGraphWithNoDanglingRelation(Analyzer $analyzer, string $path): void
    {
        GraphInvariants::assertEndpointsExist($analyzer->analyze($path));
    }

    #[DataProvider('providerEveryAnalyzer')]
    public function testAnalyzeProducesAGraphWhereAnIdentifierNamesOneSymbol(Analyzer $analyzer, string $path): void
    {
        GraphInvariants::assertNodeUniqueness($analyzer->analyze($path));
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
