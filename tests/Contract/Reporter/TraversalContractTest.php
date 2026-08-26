<?php

declare(strict_types=1);

namespace Tests\Contract\Reporter;

use App\Analyzer\Graph\Direction;
use App\Reporter\Traversal\DepthFirstTraversal;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProviderExternal;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\TestCase;
use Tests\Fixture\Analyzer\GeneratedGraphs;
use Tests\Fixture\Reporter\TraversalInvariants;

/**
 * @internal
 */
#[CoversClass(DepthFirstTraversal::class)]
#[Large]
final class TraversalContractTest extends TestCase
{
    #[DataProviderExternal(GeneratedGraphs::class, 'seeds')]
    public function testTraverseVisitsOnlySymbolsTheGraphHoldsWhenReadingForwards(int $seed): void
    {
        $graph = GeneratedGraphs::ofSeed($seed);

        TraversalInvariants::assertVisitsOnlyKnownSymbols(
            $graph,
            $graph->nodes()[0]->id(),
            new DepthFirstTraversal(Direction::Uses),
        );
    }

    #[DataProviderExternal(GeneratedGraphs::class, 'seeds')]
    public function testTraverseVisitsOnlySymbolsTheGraphHoldsWhenReadingBackwards(int $seed): void
    {
        $graph = GeneratedGraphs::ofSeed($seed);

        TraversalInvariants::assertVisitsOnlyKnownSymbols(
            $graph,
            $graph->nodes()[0]->id(),
            new DepthFirstTraversal(Direction::UsedBy),
        );
    }

    #[DataProviderExternal(GeneratedGraphs::class, 'seeds')]
    public function testTraverseTerminatesOnAnyGeneratedGraph(int $seed): void
    {
        $graph = GeneratedGraphs::ofSeed($seed);

        self::assertNotEmpty(TraversalInvariants::visitedNames(
            $graph,
            $graph->nodes()[0]->id(),
            new DepthFirstTraversal(Direction::Uses),
        ));
    }

    #[DataProviderExternal(GeneratedGraphs::class, 'seeds')]
    public function testTraverseReachesEveryRelationFromBothOfItsEnds(int $seed): void
    {
        TraversalInvariants::assertEveryRelationIsReachableBothWays(GeneratedGraphs::ofSeed($seed, 2));
    }

    public function testDirectionIsTheOneTheTraversalWasGiven(): void
    {
        self::assertSame(Direction::Uses, (new DepthFirstTraversal(Direction::Uses))->direction());
        self::assertSame(Direction::UsedBy, (new DepthFirstTraversal(Direction::UsedBy))->direction());
    }
}
