<?php

declare(strict_types=1);

namespace Tests\Contract\Graph;

use App\Analyzer\Graph\Graph;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProviderExternal;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\TestCase;
use Tests\Fixture\Analyzer\GeneratedGraphs;
use Tests\Fixture\Graph\GraphInvariants;

/**
 * @internal
 */
#[CoversClass(Graph::class)]
#[Large]
final class InvariantContractTest extends TestCase
{
    #[DataProviderExternal(GeneratedGraphs::class, 'seeds')]
    public function testEveryRelationIsReadableFromBothOfItsEnds(int $seed): void
    {
        GraphInvariants::assertBidirectional(GeneratedGraphs::ofSeed($seed));
    }

    #[DataProviderExternal(GeneratedGraphs::class, 'seeds')]
    public function testNoRelationPointsAtASymbolTheGraphDoesNotHold(int $seed): void
    {
        GraphInvariants::assertEndpointsExist(GeneratedGraphs::ofSeed($seed));
    }

    #[DataProviderExternal(GeneratedGraphs::class, 'seeds')]
    public function testAnIdentifierNamesAtMostOneSymbol(int $seed): void
    {
        GraphInvariants::assertNodeUniqueness(GeneratedGraphs::ofSeed($seed));
    }

    #[DataProviderExternal(GeneratedGraphs::class, 'seeds')]
    public function testTheSameRelationIsRecordedAtMostOncePerDirection(int $seed): void
    {
        GraphInvariants::assertNoEdgeDuplicates(GeneratedGraphs::ofSeed($seed));
    }

    #[DataProviderExternal(GeneratedGraphs::class, 'seedPairs')]
    public function testMergeKeepsEverySymbolOfBothGraphs(int $seed, int $other): void
    {
        $graph = GeneratedGraphs::ofSeed($seed, 2);
        $merged = $graph->merge(GeneratedGraphs::ofSeed($other, 2));

        GraphInvariants::assertAllNodesPreserved($graph, $merged);
    }

    #[DataProviderExternal(GeneratedGraphs::class, 'seedPairs')]
    public function testMergeKeepsTheInvariantsOfTheGraphsItJoins(int $seed, int $other): void
    {
        $merged = GeneratedGraphs::ofSeed($seed, 2)->merge(GeneratedGraphs::ofSeed($other, 2));

        GraphInvariants::assertBidirectional($merged);
        GraphInvariants::assertNoEdgeDuplicates($merged);
    }
}
