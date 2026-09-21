<?php

declare(strict_types=1);

namespace Tests\Unit\Reporter\Diagram\Layout;

use App\Reporter\Diagram\Layout\LayerOrdering;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(LayerOrdering::class)]
#[Small]
final class LayerOrderingTest extends TestCase
{
    public function testOrderUncrossesTwoArrowsThatWouldCross(): void
    {
        self::assertSame(
            [['a', 'b'], ['d', 'c']],
            LayerOrdering::order([['a', 'b'], ['c', 'd']], [['a', 'd'], ['b', 'c']]),
        );
    }

    public function testOrderKeepsAnOrderInWhichNothingCrosses(): void
    {
        self::assertSame(
            [['a', 'b'], ['c', 'd']],
            LayerOrdering::order([['a', 'b'], ['c', 'd']], [['a', 'c'], ['b', 'd']]),
        );
    }

    public function testOrderKeepsTheOrderItWasGivenWhenNoOrderCrossesFewer(): void
    {
        self::assertSame(
            [['a', 'b'], ['c', 'd']],
            LayerOrdering::order([['a', 'b'], ['c', 'd']], [['a', 'c'], ['a', 'd'], ['b', 'c'], ['b', 'd']]),
        );
    }

    public function testOrderUncrossesArrowsFurtherAlong(): void
    {
        self::assertSame(
            [['a'], ['b', 'c'], ['e', 'd']],
            LayerOrdering::order([['a'], ['b', 'c'], ['d', 'e']], [['a', 'b'], ['a', 'c'], ['b', 'e'], ['c', 'd']]),
        );
    }

    public function testOrderOfNoColumnsIsNoColumns(): void
    {
        self::assertSame([], LayerOrdering::order([], []));
    }

    public function testNeighboursJoinsTheTwoEndsOfAnArrowEachWay(): void
    {
        self::assertSame([['b' => ['a']], ['a' => ['b']]], LayerOrdering::neighbours([['a', 'b']]));
    }

    public function testNeighboursGathersEverythingOnePlaceIsJoinedTo(): void
    {
        self::assertSame(
            [['c' => ['a', 'b'], 'd' => ['a']], ['a' => ['c', 'd'], 'b' => ['c']]],
            LayerOrdering::neighbours([['a', 'c'], ['b', 'c'], ['a', 'd']]),
        );
    }

    public function testNeighboursOfNoArrowsIsNothing(): void
    {
        self::assertSame([[], []], LayerOrdering::neighbours([]));
    }

    public function testReorderPutsPlacesInTheOrderOfWhatTheyAreJoinedTo(): void
    {
        self::assertSame(['d', 'c'], LayerOrdering::reorder(['c', 'd'], ['a', 'b'], ['c' => ['b'], 'd' => ['a']]));
    }

    public function testReorderWeighsAPlaceByTheAveragePositionOfWhatItIsJoinedTo(): void
    {
        self::assertSame(
            ['d', 'c'],
            LayerOrdering::reorder(['c', 'd'], ['a', 'b', 'e'], ['c' => ['b', 'e'], 'd' => ['a', 'e']]),
        );
    }

    public function testReorderKeepsTheOrderOfPlacesThatWeighTheSame(): void
    {
        self::assertSame(['c', 'd'], LayerOrdering::reorder(['c', 'd'], ['a'], ['c' => ['a'], 'd' => ['a']]));
    }

    public function testReorderWeighsAPlaceJoinedToNothingByItsOwnPosition(): void
    {
        self::assertSame(
            ['x', 'd', 'c'],
            LayerOrdering::reorder(['x', 'c', 'd'], ['a', 'b'], ['c' => ['b'], 'd' => ['a']]),
        );
    }

    public function testReorderWeighsOnlyWhatAPlaceIsJoinedToInTheNeighbouringColumn(): void
    {
        self::assertSame(['d', 'c'], LayerOrdering::reorder(['c', 'd'], ['a', 'b'], ['c' => ['z', 'b'], 'd' => ['a']]));
    }

    public function testCrossingsCountsTwoArrowsToSwappedPlacesOnce(): void
    {
        self::assertSame(1, LayerOrdering::crossings([['a', 'b'], ['c', 'd']], [['a', 'd'], ['b', 'c']]));
    }

    public function testCrossingsCountsEveryPairOfArrowsThatCross(): void
    {
        self::assertSame(
            3,
            LayerOrdering::crossings([['a', 'b', 'c'], ['d', 'e', 'f']], [['a', 'f'], ['b', 'e'], ['c', 'd']]),
        );
    }

    public function testCrossingsCountsNothingBetweenArrowsSharingAnEnd(): void
    {
        self::assertSame(0, LayerOrdering::crossings([['a', 'b'], ['c', 'd']], [['a', 'c'], ['a', 'd'], ['b', 'd']]));
    }

    public function testCrossingsCountsOnlyArrowsBetweenTheSameTwoColumns(): void
    {
        self::assertSame(0, LayerOrdering::crossings([['a', 'b'], ['c', 'd'], ['e']], [['a', 'd'], ['d', 'e']]));
    }
}
