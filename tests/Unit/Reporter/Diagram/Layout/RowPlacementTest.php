<?php

declare(strict_types=1);

namespace Tests\Unit\Reporter\Diagram\Layout;

use App\Reporter\Diagram\Layout\LayerOrdering;
use App\Reporter\Diagram\Layout\RowPlacement;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(RowPlacement::class)]
#[UsesClass(LayerOrdering::class)]
#[Small]
final class RowPlacementTest extends TestCase
{
    public function testRowsPutsASymbolBetweenTheTwoItPointsAt(): void
    {
        self::assertSame(['a' => 1, 'b' => 0, 'c' => 2], RowPlacement::rows([['a'], ['b', 'c']], [['a', 'b'], ['a', 'c']]));
    }

    public function testRowsPutsASymbolTwoArrowsArriveAtBetweenTheTwoTheyLeave(): void
    {
        self::assertSame(['a' => 0, 'b' => 2, 'c' => 1], RowPlacement::rows([['a', 'b'], ['c']], [['a', 'c'], ['b', 'c']]));
    }

    public function testRowsPutsAChainOnOneLine(): void
    {
        self::assertSame(['a' => 0, 'b' => 0, 'c' => 0], RowPlacement::rows([['a'], ['b'], ['c']], [['a', 'b'], ['b', 'c']]));
    }

    public function testRowsPutsADiamondAroundItsMiddleLine(): void
    {
        self::assertSame(
            ['a' => 1, 'b' => 0, 'c' => 2, 'd' => 1],
            RowPlacement::rows([['a'], ['b', 'c'], ['d']], [['a', 'b'], ['a', 'c'], ['b', 'd'], ['c', 'd']]),
        );
    }

    public function testRowsKeepsPlacesNothingJoinsTwoLinesApart(): void
    {
        self::assertSame(['a' => 0, 'b' => 2], RowPlacement::rows([['a', 'b']], []));
    }

    public function testRowsStartsTheTopmostPlaceOnTheFirstLine(): void
    {
        self::assertSame(
            ['a' => 0, 'd' => 2, 'b' => 0, 'c' => 2],
            RowPlacement::rows([['a', 'd'], ['b', 'c']], [['a', 'b'], ['a', 'c'], ['d', 'c']]),
        );
    }

    public function testRowsOfNoColumnsIsNoLines(): void
    {
        self::assertSame([], RowPlacement::rows([], []));
    }

    public function testPlaceMovesTwoPlacesThatWantTheSameLineApartAroundIt(): void
    {
        self::assertSame(
            ['b' => 3, 'c' => 5],
            RowPlacement::place(['b', 'c'], ['b' => ['a'], 'c' => ['a']], ['a' => 4, 'b' => 0, 'c' => 2]),
        );
    }

    public function testPlaceMovesAPlaceToTheMedianOfWhatItIsJoinedTo(): void
    {
        self::assertSame(['d' => 2], RowPlacement::place(['d'], ['d' => ['a', 'b', 'c']], ['a' => 0, 'b' => 2, 'c' => 10, 'd' => 0]));
    }

    public function testPlaceKeepsAPlaceJoinedToNothingOnItsLine(): void
    {
        self::assertSame(['b' => 4], RowPlacement::place(['b'], [], ['b' => 4]));
    }

    public function testFitSharesTheDifferenceBetweenPlacesThatWantTheSameLine(): void
    {
        self::assertSame([0, 2], RowPlacement::fit([1.0, 1.0], 2));
    }

    public function testFitGivesPlacesAlreadyFarEnoughApartWhatTheyWant(): void
    {
        self::assertSame([0, 5], RowPlacement::fit([0.0, 5.0], 2));
    }

    public function testFitMovesARunOfPlacesThatWantTheSameLineAsOneBlock(): void
    {
        self::assertSame([0, 2, 4], RowPlacement::fit([2.0, 2.0, 2.0], 2));
    }

    public function testFitKeepsPlacesInTheirOrderWhenTheyWantTheOpposite(): void
    {
        self::assertSame([1, 3], RowPlacement::fit([4.0, 0.0], 2));
    }

    public function testFitRoundsAHalfLineAwayFromZero(): void
    {
        self::assertSame([-1, 1], RowPlacement::fit([0.0, 1.0], 2));
    }

    public function testFitMovesOnlyThePlacesThatAreTooClose(): void
    {
        self::assertSame([-1, 0, 5], RowPlacement::fit([0.0, 0.0, 5.0], 1));
    }

    public function testFitOfNothingIsNothing(): void
    {
        self::assertSame([], RowPlacement::fit([], 2));
    }

    public function testMedianOfTwoLinesIsHalfwayBetweenThem(): void
    {
        self::assertSame(1.0, RowPlacement::median([0, 2]));
    }

    public function testMedianOfThreeLinesIsTheMiddleOne(): void
    {
        self::assertSame(2.0, RowPlacement::median([9, 0, 2]));
    }

    public function testMedianOfAnEvenNumberOfLinesIsHalfwayBetweenTheMiddleTwo(): void
    {
        self::assertSame(2.5, RowPlacement::median([10, 3, 1, 2]));
    }

    public function testMedianOfOneLineIsThatLine(): void
    {
        self::assertSame(-3.0, RowPlacement::median([-3]));
    }
}
