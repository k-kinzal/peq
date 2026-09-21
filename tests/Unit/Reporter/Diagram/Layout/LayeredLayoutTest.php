<?php

declare(strict_types=1);

namespace Tests\Unit\Reporter\Diagram\Layout;

use App\Reporter\Diagram\Diagram;
use App\Reporter\Diagram\DiagramEdge;
use App\Reporter\Diagram\DiagramNode;
use App\Reporter\Diagram\Layout\DiagramLayout;
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
#[CoversClass(LayeredLayout::class)]
#[UsesClass(Diagram::class)]
#[UsesClass(DiagramEdge::class)]
#[UsesClass(DiagramNode::class)]
#[UsesClass(DiagramLayout::class)]
#[UsesClass(LayerOrdering::class)]
#[UsesClass(LayoutItem::class)]
#[UsesClass(LayoutItemKind::class)]
#[UsesClass(RowPlacement::class)]
#[Small]
final class LayeredLayoutTest extends TestCase
{
    public function testOfLaysOutASymbolPointingAtAnotherInNeighbouringColumnsOnOneLine(): void
    {
        $diagram = new Diagram();
        $diagram->add(new DiagramNode('a'));
        $diagram->add(new DiagramNode('b'));
        $diagram->relate(new DiagramEdge('a', 'b'));

        self::assertEquals(
            new DiagramLayout(
                [
                    "symbol\0a" => new LayoutItem("symbol\0a", LayoutItemKind::Symbol, 'a', 0),
                    "symbol\0b" => new LayoutItem("symbol\0b", LayoutItemKind::Symbol, 'b', 1),
                ],
                [["symbol\0a"], ["symbol\0b"]],
                ["symbol\0a" => 0, "symbol\0b" => 0],
                [["symbol\0a", "symbol\0b"]],
            ),
            LayeredLayout::of($diagram),
        );
    }

    public function testOfLaysOutADrawingThatHoldsNothingAsNothing(): void
    {
        self::assertEquals(new DiagramLayout([], [], [], []), LayeredLayout::of(new Diagram()));
    }

    public function testOfPutsEveryPlaceOnAnEvenLineASymbolBetweenTheTwoItPointsAt(): void
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

        self::assertSame(
            ["symbol\0A" => 2, "symbol\0B" => 0, "symbol\0C" => 4, "symbol\0D" => 2],
            LayeredLayout::of($diagram)->rows,
        );
    }

    public function testOfPointsAnArrowWithinAColumnAtAReferenceInTheNextColumn(): void
    {
        $diagram = new Diagram();
        $diagram->add(new DiagramNode('A'));
        $diagram->add(new DiagramNode('B'));
        $diagram->add(new DiagramNode('C'));
        $diagram->relate(new DiagramEdge('A', 'B'));
        $diagram->relate(new DiagramEdge('A', 'C'));
        $diagram->relate(new DiagramEdge('B', 'C'));

        self::assertEquals(
            new DiagramLayout(
                [
                    "symbol\0A" => new LayoutItem("symbol\0A", LayoutItemKind::Symbol, 'A', 0),
                    "symbol\0B" => new LayoutItem("symbol\0B", LayoutItemKind::Symbol, 'B', 1),
                    "symbol\0C" => new LayoutItem("symbol\0C", LayoutItemKind::Symbol, 'C', 1),
                    "reference\0B\0C" => new LayoutItem("reference\0B\0C", LayoutItemKind::Reference, 'C', 2),
                ],
                [["symbol\0A"], ["symbol\0B", "symbol\0C"], ["reference\0B\0C"]],
                ["symbol\0A" => 2, "symbol\0B" => 0, "symbol\0C" => 4, "reference\0B\0C" => 0],
                [["symbol\0A", "symbol\0B"], ["symbol\0A", "symbol\0C"], ["symbol\0B", "reference\0B\0C"]],
            ),
            LayeredLayout::of($diagram),
        );
    }

    public function testOfPointsAnArrowThatClosesACycleAtARecursion(): void
    {
        $diagram = new Diagram();
        $diagram->add(new DiagramNode('A'));
        $diagram->add(new DiagramNode('B'));
        $diagram->add(new DiagramNode('C'));
        $diagram->relate(new DiagramEdge('A', 'B'));
        $diagram->relate(new DiagramEdge('B', 'C'));
        $diagram->relate(new DiagramEdge('C', 'A'));

        self::assertEquals(
            new DiagramLayout(
                [
                    "symbol\0A" => new LayoutItem("symbol\0A", LayoutItemKind::Symbol, 'A', 0),
                    "symbol\0B" => new LayoutItem("symbol\0B", LayoutItemKind::Symbol, 'B', 1),
                    "symbol\0C" => new LayoutItem("symbol\0C", LayoutItemKind::Symbol, 'C', 2),
                    "reference\0C\0A" => new LayoutItem("reference\0C\0A", LayoutItemKind::Recursion, 'A', 3),
                ],
                [["symbol\0A"], ["symbol\0B"], ["symbol\0C"], ["reference\0C\0A"]],
                ["symbol\0A" => 0, "symbol\0B" => 0, "symbol\0C" => 0, "reference\0C\0A" => 0],
                [["symbol\0A", "symbol\0B"], ["symbol\0B", "symbol\0C"], ["symbol\0C", "reference\0C\0A"]],
            ),
            LayeredLayout::of($diagram),
        );
    }

    public function testOfPointsASymbolPointingAtItselfAtARecursion(): void
    {
        $diagram = new Diagram();
        $diagram->add(new DiagramNode('A'));
        $diagram->relate(new DiagramEdge('A', 'A'));

        self::assertEquals(
            new DiagramLayout(
                [
                    "symbol\0A" => new LayoutItem("symbol\0A", LayoutItemKind::Symbol, 'A', 0),
                    "reference\0A\0A" => new LayoutItem("reference\0A\0A", LayoutItemKind::Recursion, 'A', 1),
                ],
                [["symbol\0A"], ["reference\0A\0A"]],
                ["symbol\0A" => 0, "reference\0A\0A" => 0],
                [["symbol\0A", "reference\0A\0A"]],
            ),
            LayeredLayout::of($diagram),
        );
    }

    public function testOfPointsAnArrowFurtherRightThanTheNextColumnAtAReference(): void
    {
        $diagram = new Diagram();
        $diagram->add(new DiagramNode('A'));
        $diagram->add(new DiagramNode('B'));
        $diagram->add(new DiagramNode('C'));
        $diagram->add(new DiagramNode('D'));
        $diagram->add(new DiagramNode('E'));
        $diagram->relate(new DiagramEdge('A', 'B'));
        $diagram->relate(new DiagramEdge('B', 'C'));
        $diagram->relate(new DiagramEdge('D', 'C'));
        $diagram->relate(new DiagramEdge('D', 'E'));
        $diagram->relate(new DiagramEdge('E', 'D'));
        $layout = LayeredLayout::of($diagram);

        self::assertEquals(
            [
                "symbol\0A" => new LayoutItem("symbol\0A", LayoutItemKind::Symbol, 'A', 0),
                "symbol\0B" => new LayoutItem("symbol\0B", LayoutItemKind::Symbol, 'B', 1),
                "symbol\0C" => new LayoutItem("symbol\0C", LayoutItemKind::Symbol, 'C', 2),
                "symbol\0D" => new LayoutItem("symbol\0D", LayoutItemKind::Symbol, 'D', 0),
                "symbol\0E" => new LayoutItem("symbol\0E", LayoutItemKind::Symbol, 'E', 1),
                "reference\0D\0C" => new LayoutItem("reference\0D\0C", LayoutItemKind::Reference, 'C', 1),
                "reference\0E\0D" => new LayoutItem("reference\0E\0D", LayoutItemKind::Recursion, 'D', 2),
            ],
            $layout->items,
        );
        self::assertSame(
            [
                ["symbol\0A", "symbol\0B"],
                ["symbol\0B", "symbol\0C"],
                ["symbol\0D", "reference\0D\0C"],
                ["symbol\0D", "symbol\0E"],
                ["symbol\0E", "reference\0E\0D"],
            ],
            $layout->links,
        );
    }

    public function testOfDrawsTwoRelationsBetweenTheSameSymbolsAsOneArrow(): void
    {
        $diagram = new Diagram();
        $diagram->add(new DiagramNode('a'));
        $diagram->add(new DiagramNode('b'));
        $diagram->relate(new DiagramEdge('a', 'b', 'calls'));
        $diagram->relate(new DiagramEdge('a', 'b', 'names'));

        self::assertSame([["symbol\0a", "symbol\0b"]], LayeredLayout::of($diagram)->links);
    }

    public function testLayersPutsASymbolAsFarRightAsTheFewestArrowsItTakesToReachIt(): void
    {
        $diagram = new Diagram();
        $diagram->add(new DiagramNode('a'));
        $diagram->add(new DiagramNode('b'));
        $diagram->add(new DiagramNode('c'));
        $diagram->relate(new DiagramEdge('a', 'b'));
        $diagram->relate(new DiagramEdge('b', 'c'));
        $diagram->relate(new DiagramEdge('a', 'c'));

        self::assertSame([['a', 'b', 'c'], ['a' => 0, 'b' => 1, 'c' => 1]], LayeredLayout::layers($diagram));
    }

    public function testLayersStartsFromEverySymbolNothingPointsAtAtOnce(): void
    {
        $diagram = new Diagram();
        $diagram->add(new DiagramNode('a'));
        $diagram->add(new DiagramNode('b'));
        $diagram->add(new DiagramNode('c'));
        $diagram->add(new DiagramNode('d'));
        $diagram->relate(new DiagramEdge('a', 'c'));
        $diagram->relate(new DiagramEdge('c', 'd'));
        $diagram->relate(new DiagramEdge('b', 'c'));

        self::assertSame([['a', 'b', 'c', 'd'], ['a' => 0, 'b' => 0, 'c' => 1, 'd' => 2]], LayeredLayout::layers($diagram));
    }

    public function testLayersStartsFromTheFirstSymbolDrawnWhenEverySymbolIsPointedAt(): void
    {
        $diagram = new Diagram();
        $diagram->add(new DiagramNode('b'));
        $diagram->add(new DiagramNode('a'));
        $diagram->relate(new DiagramEdge('a', 'b'));
        $diagram->relate(new DiagramEdge('b', 'a'));

        self::assertSame([['b', 'a'], ['b' => 0, 'a' => 1]], LayeredLayout::layers($diagram));
    }

    public function testLayersStartsAgainFromASymbolNoStartReaches(): void
    {
        $diagram = new Diagram();
        $diagram->add(new DiagramNode('A'));
        $diagram->add(new DiagramNode('B'));
        $diagram->add(new DiagramNode('C'));
        $diagram->add(new DiagramNode('D'));
        $diagram->add(new DiagramNode('E'));
        $diagram->relate(new DiagramEdge('A', 'B'));
        $diagram->relate(new DiagramEdge('B', 'C'));
        $diagram->relate(new DiagramEdge('D', 'C'));
        $diagram->relate(new DiagramEdge('D', 'E'));
        $diagram->relate(new DiagramEdge('E', 'D'));

        self::assertSame(
            [['A', 'B', 'C', 'D', 'E'], ['A' => 0, 'B' => 1, 'C' => 2, 'D' => 0, 'E' => 1]],
            LayeredLayout::layers($diagram),
        );
    }

    public function testLayersPutsASymbolNothingIsJoinedToInTheFirstColumn(): void
    {
        $diagram = new Diagram();
        $diagram->add(new DiagramNode('a'));
        $diagram->add(new DiagramNode('b'));

        self::assertSame([['a', 'b'], ['a' => 0, 'b' => 0]], LayeredLayout::layers($diagram));
    }

    public function testSpreadPutsEverythingReachedAsFarRightAsTheFewestArrowsItTakes(): void
    {
        $diagram = new Diagram();
        $diagram->add(new DiagramNode('a'));
        $diagram->add(new DiagramNode('b'));
        $diagram->add(new DiagramNode('c'));
        $diagram->relate(new DiagramEdge('a', 'b'));
        $diagram->relate(new DiagramEdge('b', 'c'));
        $diagram->relate(new DiagramEdge('c', 'a'));

        self::assertSame([['b', 'c', 'a'], ['b' => 0, 'c' => 1, 'a' => 2]], LayeredLayout::spread($diagram, ['b'], [], []));
    }

    public function testSpreadStartsFromSeveralSymbolsAtOnce(): void
    {
        $diagram = new Diagram();
        $diagram->add(new DiagramNode('a'));
        $diagram->add(new DiagramNode('b'));
        $diagram->add(new DiagramNode('c'));
        $diagram->relate(new DiagramEdge('a', 'c'));
        $diagram->relate(new DiagramEdge('b', 'c'));

        self::assertSame([['a', 'b', 'c'], ['a' => 0, 'b' => 0, 'c' => 1]], LayeredLayout::spread($diagram, ['a', 'b'], [], []));
    }

    public function testSpreadLeavesTheSymbolsAlreadyPlacedWhereTheyAre(): void
    {
        $diagram = new Diagram();
        $diagram->add(new DiagramNode('a'));
        $diagram->add(new DiagramNode('b'));
        $diagram->add(new DiagramNode('c'));
        $diagram->relate(new DiagramEdge('a', 'b'));
        $diagram->relate(new DiagramEdge('b', 'c'));

        self::assertSame(
            [['c', 'a', 'b'], ['c' => 5, 'a' => 0, 'b' => 1]],
            LayeredLayout::spread($diagram, ['a'], ['c'], ['c' => 5]),
        );
    }

    public function testSpreadFromNothingPlacesNothing(): void
    {
        $diagram = new Diagram();
        $diagram->add(new DiagramNode('a'));

        self::assertSame([[], []], LayeredLayout::spread($diagram, [], [], []));
    }

    public function testTargetsDrawsTwoRelationsToOneSymbolAsOneArrow(): void
    {
        $diagram = new Diagram();
        $diagram->add(new DiagramNode('a'));
        $diagram->add(new DiagramNode('b'));
        $diagram->relate(new DiagramEdge('a', 'b', 'calls'));
        $diagram->relate(new DiagramEdge('a', 'b', 'names'));

        self::assertSame(['b'], LayeredLayout::targets($diagram, 'a'));
    }

    public function testTargetsAreInTheOrderTheRelationsWereDrawn(): void
    {
        $diagram = new Diagram();
        $diagram->add(new DiagramNode('a'));
        $diagram->add(new DiagramNode('b'));
        $diagram->add(new DiagramNode('c'));
        $diagram->relate(new DiagramEdge('a', 'c'));
        $diagram->relate(new DiagramEdge('a', 'b'));

        self::assertSame(['c', 'b'], LayeredLayout::targets($diagram, 'a'));
    }

    public function testTargetsLeaveOutASymbolTheDrawingDoesNotHold(): void
    {
        $diagram = new Diagram();
        $diagram->add(new DiagramNode('a'));
        $diagram->relate(new DiagramEdge('a', 'z'));

        self::assertSame([], LayeredLayout::targets($diagram, 'a'));
    }

    public function testTargetsOfASymbolPointingAtItselfHoldIt(): void
    {
        $diagram = new Diagram();
        $diagram->add(new DiagramNode('a'));
        $diagram->relate(new DiagramEdge('a', 'a'));

        self::assertSame(['a'], LayeredLayout::targets($diagram, 'a'));
    }

    public function testReachesFollowsArrowsThroughOtherSymbols(): void
    {
        $diagram = new Diagram();
        $diagram->add(new DiagramNode('a'));
        $diagram->add(new DiagramNode('b'));
        $diagram->add(new DiagramNode('c'));
        $diagram->relate(new DiagramEdge('a', 'b'));
        $diagram->relate(new DiagramEdge('b', 'c'));

        self::assertTrue(LayeredLayout::reaches($diagram, 'a', 'c'));
    }

    public function testReachesDoesNotFollowAnArrowBackwards(): void
    {
        $diagram = new Diagram();
        $diagram->add(new DiagramNode('a'));
        $diagram->add(new DiagramNode('b'));
        $diagram->relate(new DiagramEdge('a', 'b'));

        self::assertFalse(LayeredLayout::reaches($diagram, 'b', 'a'));
    }

    public function testReachesTheSymbolItStartsFrom(): void
    {
        $diagram = new Diagram();
        $diagram->add(new DiagramNode('a'));

        self::assertTrue(LayeredLayout::reaches($diagram, 'a', 'a'));
    }

    public function testReachesRoundACycle(): void
    {
        $diagram = new Diagram();
        $diagram->add(new DiagramNode('a'));
        $diagram->add(new DiagramNode('b'));
        $diagram->add(new DiagramNode('c'));
        $diagram->relate(new DiagramEdge('a', 'b'));
        $diagram->relate(new DiagramEdge('b', 'c'));
        $diagram->relate(new DiagramEdge('c', 'a'));

        self::assertTrue(LayeredLayout::reaches($diagram, 'c', 'b'));
    }

    public function testReachesEndsWhenACycleComesRoundWithoutReachingTheSymbol(): void
    {
        $diagram = new Diagram();
        $diagram->add(new DiagramNode('a'));
        $diagram->add(new DiagramNode('b'));
        $diagram->add(new DiagramNode('c'));
        $diagram->relate(new DiagramEdge('a', 'b'));
        $diagram->relate(new DiagramEdge('b', 'a'));

        self::assertFalse(LayeredLayout::reaches($diagram, 'a', 'c'));
    }

    public function testColumnsGathersThePlacesByColumn(): void
    {
        $a = LayoutItem::symbol('a', 0);
        $b = LayoutItem::symbol('b', 1);
        $c = LayoutItem::symbol('c', 0);

        self::assertSame([["symbol\0a", "symbol\0c"], ["symbol\0b"]], LayeredLayout::columns([$a->key => $a, $b->key => $b, $c->key => $c]));
    }

    public function testColumnsLeavesAColumnNothingIsInEmpty(): void
    {
        $a = LayoutItem::symbol('a', 0);
        $c = LayoutItem::symbol('c', 2);

        self::assertSame([["symbol\0a"], [], ["symbol\0c"]], LayeredLayout::columns([$a->key => $a, $c->key => $c]));
    }

    public function testColumnsOfNoPlacesIsNoColumns(): void
    {
        self::assertSame([], LayeredLayout::columns([]));
    }
}
