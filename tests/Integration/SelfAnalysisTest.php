<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Analyzer\PhpStanAnalyzer\PhpStanAnalyzer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\TestCase;
use Tests\Fixture\Analyzer\SelfAnalysis;
use Tests\Fixture\Graph\GraphInvariants;

/**
 * @internal
 */
#[CoversClass(PhpStanAnalyzer::class)]
#[Large]
final class SelfAnalysisTest extends TestCase
{
    public function testAnalyzingItsOwnSourceProducesAPopulatedGraph(): void
    {
        self::assertGreaterThan(50, count(SelfAnalysis::graph()->nodes()));
    }

    #[DataProvider('providerSymbolsPeqDeclares')]
    public function testTheGraphHoldsTheSymbolsPeqDeclares(string $name): void
    {
        self::assertNotNull(SelfAnalysis::graph()->nodeNamed($name));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function providerSymbolsPeqDeclares(): iterable
    {
        yield 'the graph model' => ['App\Analyzer\Graph\Graph'];

        yield 'a closed set of relation kinds' => ['App\Analyzer\Graph\EdgeKind'];

        yield 'a closed set of symbol kinds' => ['App\Analyzer\Graph\NodeKind'];

        yield 'the analyzer that reads real sources' => ['App\Analyzer\PhpStanAnalyzer\PhpStanAnalyzer'];

        yield 'the command line boundary' => ['App\Command\InspectCommand'];

        yield 'the use case' => ['App\Action\Inspect\InspectAction'];
    }

    public function testEveryRelationIsReadableFromBothOfItsEnds(): void
    {
        GraphInvariants::assertBidirectional(SelfAnalysis::graph());
    }

    public function testNoRelationPointsAtASymbolTheGraphDoesNotHold(): void
    {
        GraphInvariants::assertEndpointsExist(SelfAnalysis::graph());
    }

    public function testAnIdentifierNamesAtMostOneSymbol(): void
    {
        GraphInvariants::assertNodeUniqueness(SelfAnalysis::graph());
    }

    public function testTheSameRelationIsRecordedAtMostOncePerDirection(): void
    {
        GraphInvariants::assertNoEdgeDuplicates(SelfAnalysis::graph());
    }

    public function testInvertingAnyRelationTwiceYieldsTheRelationItStartedFrom(): void
    {
        GraphInvariants::assertInversionRoundTrips(SelfAnalysis::graph());
    }
}
