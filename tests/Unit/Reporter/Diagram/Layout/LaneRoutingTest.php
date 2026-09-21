<?php

declare(strict_types=1);

namespace Tests\Unit\Reporter\Diagram\Layout;

use App\Reporter\Diagram\Diagram;
use App\Reporter\Diagram\DiagramEdge;
use App\Reporter\Diagram\DiagramNode;
use App\Reporter\Diagram\Layout\DiagramLayout;
use App\Reporter\Diagram\Layout\Lane;
use App\Reporter\Diagram\Layout\LaneRouting;
use App\Reporter\Diagram\Layout\LayeredLayout;
use App\Reporter\Diagram\Layout\LayerOrdering;
use App\Reporter\Diagram\Layout\LayoutItem;
use App\Reporter\Diagram\Layout\LayoutItemKind;
use App\Reporter\Diagram\Layout\RowPlacement;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(LaneRouting::class)]
#[UsesClass(Diagram::class)]
#[UsesClass(DiagramEdge::class)]
#[UsesClass(DiagramNode::class)]
#[UsesClass(DiagramLayout::class)]
#[UsesClass(Lane::class)]
#[UsesClass(LayeredLayout::class)]
#[UsesClass(LayerOrdering::class)]
#[UsesClass(LayoutItem::class)]
#[UsesClass(LayoutItemKind::class)]
#[UsesClass(RowPlacement::class)]
#[Small]
final class LaneRoutingTest extends TestCase
{
    public function testOfDrawsAnArrowBetweenTwoPlacesOnOneLineStraightAcross(): void
    {
        $diagram = new Diagram();
        $diagram->add(new DiagramNode('A'));
        $diagram->add(new DiagramNode('B'));
        $diagram->relate(new DiagramEdge('A', 'B'));

        self::assertEquals(
            new LaneRouting([], [["symbol\0A", "symbol\0B"]]),
            LaneRouting::of(LayeredLayout::of($diagram), 0),
        );
    }

    public function testOfSharesOneLaneBetweenTheArrowsFanningOutFromOnePlace(): void
    {
        $diagram = new Diagram();
        $diagram->add(new DiagramNode('A'));
        $diagram->add(new DiagramNode('B'));
        $diagram->add(new DiagramNode('C'));
        $diagram->relate(new DiagramEdge('A', 'B'));
        $diagram->relate(new DiagramEdge('A', 'C'));

        self::assertEquals(
            new LaneRouting([new Lane(["symbol\0A"], ["symbol\0B", "symbol\0C"])], []),
            LaneRouting::of(LayeredLayout::of($diagram), 0),
        );
    }

    public function testOfSendsAnArrowFromAFanningPlaceAlongItsLaneEvenToAPlaceOnItsOwnLine(): void
    {
        $diagram = new Diagram();
        $diagram->add(new DiagramNode('A'));
        $diagram->add(new DiagramNode('B'));
        $diagram->add(new DiagramNode('C'));
        $diagram->add(new DiagramNode('D'));
        $diagram->relate(new DiagramEdge('A', 'B'));
        $diagram->relate(new DiagramEdge('A', 'C'));
        $diagram->relate(new DiagramEdge('A', 'D'));

        self::assertEquals(
            new LaneRouting([new Lane(["symbol\0A"], ["symbol\0B", "symbol\0C", "symbol\0D"])], []),
            LaneRouting::of(LayeredLayout::of($diagram), 0),
        );
    }

    public function testOfSharesOneLaneBetweenTheArrowsGatheringAtOnePlace(): void
    {
        $diagram = new Diagram();
        $diagram->add(new DiagramNode('A'));
        $diagram->add(new DiagramNode('B'));
        $diagram->add(new DiagramNode('C'));
        $diagram->relate(new DiagramEdge('A', 'C'));
        $diagram->relate(new DiagramEdge('B', 'C'));

        self::assertEquals(
            new LaneRouting([new Lane(["symbol\0A", "symbol\0B"], ["symbol\0C"])], []),
            LaneRouting::of(LayeredLayout::of($diagram), 0),
        );
    }

    public function testOfSendsAnArrowFromAFanOverToAGatheringOnAnOddLine(): void
    {
        $diagram = new Diagram();
        $diagram->add(new DiagramNode('A'));
        $diagram->add(new DiagramNode('D'));
        $diagram->add(new DiagramNode('B'));
        $diagram->add(new DiagramNode('C'));
        $diagram->relate(new DiagramEdge('A', 'B'));
        $diagram->relate(new DiagramEdge('A', 'C'));
        $diagram->relate(new DiagramEdge('D', 'C'));

        self::assertEquals(
            new LaneRouting(
                [
                    new Lane(["symbol\0A"], ["symbol\0B"], [], [3]),
                    new Lane(["symbol\0D"], ["symbol\0C"], [3], []),
                ],
                [],
                [[0, 1, 3]],
            ),
            LaneRouting::of(LayeredLayout::of($diagram), 0),
        );
    }

    public function testOfGivesEveryArrowThatGoesOverALineOfItsOwn(): void
    {
        $diagram = new Diagram();
        $diagram->add(new DiagramNode('A'));
        $diagram->add(new DiagramNode('B'));
        $diagram->add(new DiagramNode('C'));
        $diagram->add(new DiagramNode('D'));
        $diagram->relate(new DiagramEdge('A', 'C'));
        $diagram->relate(new DiagramEdge('A', 'D'));
        $diagram->relate(new DiagramEdge('B', 'C'));
        $diagram->relate(new DiagramEdge('B', 'D'));

        self::assertEquals(
            new LaneRouting(
                [
                    new Lane(["symbol\0B"], [], [], [-1, 5]),
                    new Lane(["symbol\0A"], [], [], [1, 3]),
                    new Lane([], ["symbol\0C"], [1, -1]),
                    new Lane([], ["symbol\0D"], [3, 5]),
                ],
                [],
                [[1, 2, 1], [1, 3, 3], [0, 2, -1], [0, 3, 5]],
            ),
            LaneRouting::of(LayeredLayout::of($diagram), 0),
        );
    }

    public function testOfSendsAGatheringArrowOverOnALineOfItsOwnWhenAnotherPlaceSitsOnItsLine(): void
    {
        $layout = new DiagramLayout(
            [
                'p' => new LayoutItem('p', LayoutItemKind::Symbol, 'P', 0),
                'q' => new LayoutItem('q', LayoutItemKind::Symbol, 'Q', 0),
                'r' => new LayoutItem('r', LayoutItemKind::Symbol, 'R', 0),
                'u' => new LayoutItem('u', LayoutItemKind::Symbol, 'U', 1),
                't' => new LayoutItem('t', LayoutItemKind::Symbol, 'T', 1),
            ],
            [['p', 'q', 'r'], ['u', 't']],
            ['p' => 0, 'q' => 4, 'r' => 8, 'u' => 0, 't' => 4],
            [['p', 'u'], ['q', 'u'], ['r', 't']],
        );

        self::assertEquals(
            new LaneRouting(
                [
                    new Lane(['q'], [], [], [1]),
                    new Lane(['r'], ['t']),
                    new Lane(['p'], ['u'], [1]),
                ],
                [],
                [[0, 2, 1]],
            ),
            LaneRouting::of($layout, 0),
        );
    }

    public function testOfRoutesOnlyTheArrowsLeavingTheColumnItIsAskedAbout(): void
    {
        $diagram = new Diagram();
        $diagram->add(new DiagramNode('A'));
        $diagram->add(new DiagramNode('B'));
        $diagram->add(new DiagramNode('C'));
        $diagram->add(new DiagramNode('D'));
        $diagram->relate(new DiagramEdge('A', 'B'));
        $diagram->relate(new DiagramEdge('A', 'C'));
        $diagram->relate(new DiagramEdge('B', 'D'));
        $diagram->relate(new DiagramEdge('C', 'D'));

        self::assertEquals(
            new LaneRouting([new Lane(["symbol\0B", "symbol\0C"], ["symbol\0D"])], []),
            LaneRouting::of(LayeredLayout::of($diagram), 1),
        );
    }

    public function testOfRoutesNothingForAColumnNothingLeaves(): void
    {
        $diagram = new Diagram();
        $diagram->add(new DiagramNode('A'));
        $diagram->add(new DiagramNode('B'));
        $diagram->relate(new DiagramEdge('A', 'B'));

        self::assertEquals(new LaneRouting([], []), LaneRouting::of(LayeredLayout::of($diagram), 1));
    }

    public function testBesideReadsThePlaceOnEachLineOfAColumn(): void
    {
        $layout = new DiagramLayout([], [[], ['b', 'c']], ['b' => 0, 'c' => 2], []);

        self::assertSame([0 => 'b', 2 => 'c'], LaneRouting::beside($layout, 1));
    }

    public function testBesideOfAColumnNothingIsInIsNothing(): void
    {
        $layout = new DiagramLayout([], [['a'], []], ['a' => 0], []);

        self::assertSame([], LaneRouting::beside($layout, 1));
    }

    public function testBesideOfAColumnPastTheLastIsNothing(): void
    {
        $layout = new DiagramLayout([], [['a']], ['a' => 0], []);

        self::assertSame([], LaneRouting::beside($layout, 1));
    }

    public function testSpareIsTheLineJustAboveThePlaceArrivedAtWhenComingFromAbove(): void
    {
        self::assertSame(3, LaneRouting::spare(0, 4, []));
    }

    public function testSpareIsTheLineJustBelowThePlaceArrivedAtWhenComingFromBelow(): void
    {
        self::assertSame(1, LaneRouting::spare(4, 0, []));
    }

    public function testSpareIsTheLineJustBelowThePlaceArrivedAtWhenComingFromItsOwnLine(): void
    {
        self::assertSame(5, LaneRouting::spare(4, 4, []));
    }

    public function testSpareIsTheLineOnTheOtherSideWhenTheNearestIsTaken(): void
    {
        self::assertSame(5, LaneRouting::spare(0, 4, [['a', 'b', 3]]));
    }

    public function testSpareMovesFurtherAwayWhenBothNearestLinesAreTaken(): void
    {
        self::assertSame(1, LaneRouting::spare(0, 4, [['a', 'b', 3], ['a', 'c', 5]]));
    }

    public function testAssembleJoinsAPlaceThatFansOutToOneThatGathersByACrossing(): void
    {
        self::assertEquals(
            new LaneRouting(
                [new Lane(['a'], [], [], [3]), new Lane([], ['b'], [3])],
                [],
                [[0, 1, 3]],
            ),
            LaneRouting::assemble(
                ['a' => 0, 'b' => 4],
                ['a' => ['targets' => [], 'crossings' => [3]]],
                ['b' => ['origins' => [], 'crossings' => [3]]],
                [['a', 'b', 3]],
                [],
            ),
        );
    }

    public function testAssembleMakesNoLaneForAPlaceThatSendsNothingAlongOne(): void
    {
        self::assertEquals(
            new LaneRouting([], [['a', 'b']]),
            LaneRouting::assemble(
                ['a' => 0, 'b' => 0],
                ['a' => ['targets' => [], 'crossings' => []]],
                ['b' => ['origins' => [], 'crossings' => []]],
                [],
                [['a', 'b']],
            ),
        );
    }

    public function testAssemblePutsTheLanesWhereArrowsFanOutLeftOfTheLanesWhereTheyGather(): void
    {
        self::assertEquals(
            new LaneRouting([new Lane(['a'], ['b', 'c']), new Lane(['d', 'e'], ['f'])], []),
            LaneRouting::assemble(
                ['a' => 10, 'b' => 8, 'c' => 12, 'd' => 0, 'e' => 4, 'f' => 2],
                ['a' => ['targets' => ['b', 'c'], 'crossings' => []]],
                ['f' => ['origins' => ['d', 'e'], 'crossings' => []]],
                [],
                [],
            ),
        );
    }

    public function testArrangeSwapsTwoLanesThatWouldCrossEachOtherTwice(): void
    {
        $first = new Lane(['a'], ['c']);
        $second = new Lane(['b'], ['d']);

        self::assertSame([$second, $first], LaneRouting::arrange([$first, $second], ['a' => 0, 'b' => 2, 'c' => 4, 'd' => 6]));
    }

    public function testArrangePutsLanesThatNeverMeetInTheOrderOfTheLinesTheyRunBetween(): void
    {
        $upper = new Lane(['a'], ['c']);
        $lower = new Lane(['b'], ['d']);

        self::assertSame([$upper, $lower], LaneRouting::arrange([$lower, $upper], ['a' => 0, 'c' => 2, 'b' => 4, 'd' => 6]));
    }

    public function testArrangeAvoidsSharingAStretchOfLineAboveAnyCrossing(): void
    {
        $left = new Lane(['a'], ['c']);
        $right = new Lane(['b'], ['d']);

        self::assertSame([$right, $left], LaneRouting::arrange([$left, $right], ['a' => 0, 'b' => 2, 'c' => 2, 'd' => 4]));
    }

    public function testArrangeOfNoLanesIsNoLanes(): void
    {
        self::assertSame([], LaneRouting::arrange([], []));
    }

    public function testCostOfAnArrowLeavingOnTheLineAnotherEntersOnIsAStretchOfSharedLine(): void
    {
        $left = new Lane(['a'], ['c']);
        $right = new Lane(['b'], ['d']);

        self::assertSame(1000, LaneRouting::cost($left, $right, ['a' => 0, 'b' => 2, 'c' => 2, 'd' => 4]));
    }

    public function testCostOfLanesThatNeverMeetIsNothing(): void
    {
        $left = new Lane(['a'], ['c']);
        $right = new Lane(['b'], ['d']);

        self::assertSame(0, LaneRouting::cost($left, $right, ['a' => 0, 'b' => 4, 'c' => 2, 'd' => 6]));
    }

    public function testCostCountsACrossingEachWayBetweenInterleavedLanes(): void
    {
        $left = new Lane(['a'], ['c']);
        $right = new Lane(['b'], ['d']);

        self::assertSame(2, LaneRouting::cost($left, $right, ['a' => 0, 'b' => 2, 'c' => 4, 'd' => 6]));
    }

    public function testCostDoesNotCountAnArrowLeavingOnTheLineTheRightLaneArrivesOn(): void
    {
        $left = new Lane(['a'], ['c']);
        $right = new Lane(['b'], ['d']);

        self::assertSame(1, LaneRouting::cost($left, $right, ['a' => 0, 'b' => 2, 'c' => 4, 'd' => 4]));
    }

    public function testUntangleSendsAnArrowThatWouldShareALineOverOnALineOfItsOwn(): void
    {
        $left = new Lane(['a'], ['c']);
        $right = new Lane(['b'], ['d']);

        self::assertEquals(
            [[new Lane(['a'], [], [], [1]), new Lane(['b'], ['d'])], [new Lane([], ['c'], [1])], [['a', 'c', 1]]],
            LaneRouting::untangle([$left, $right], ['a' => 0, 'b' => 2, 'c' => 2, 'd' => 4], []),
        );
    }

    public function testUntangleLeavesAnArrowAloneWhenTheLaneEnteredOnItsLineIsFurtherLeft(): void
    {
        $left = new Lane(['b'], ['d']);
        $right = new Lane(['a'], ['c']);

        self::assertEquals(
            [[$left, $right], [], []],
            LaneRouting::untangle([$left, $right], ['a' => 0, 'b' => 2, 'c' => 2, 'd' => 4], []),
        );
    }

    public function testLaneWithFindsTheLaneAnArrowGoesOverFrom(): void
    {
        $lanes = [new Lane(['a'], [], [], [3]), new Lane([], ['b'], [3])];

        self::assertSame(0, LaneRouting::laneWith($lanes, 3, true));
    }

    public function testLaneWithFindsTheLaneAnArrowGoesOverTo(): void
    {
        $lanes = [new Lane(['a'], [], [], [3]), new Lane([], ['b'], [3])];

        self::assertSame(1, LaneRouting::laneWith($lanes, 3, false));
    }
}
