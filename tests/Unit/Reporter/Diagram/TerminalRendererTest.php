<?php

declare(strict_types=1);

namespace Tests\Unit\Reporter\Diagram;

use App\Reporter\Diagram\Diagram;
use App\Reporter\Diagram\DiagramCanvas;
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
use App\Reporter\Diagram\TerminalRenderer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Output\BufferedOutput;

/**
 * @internal
 */
#[CoversClass(TerminalRenderer::class)]
#[UsesClass(Diagram::class)]
#[UsesClass(DiagramCanvas::class)]
#[UsesClass(DiagramEdge::class)]
#[UsesClass(DiagramNode::class)]
#[UsesClass(DiagramLayout::class)]
#[UsesClass(Lane::class)]
#[UsesClass(LaneRouting::class)]
#[UsesClass(LayeredLayout::class)]
#[UsesClass(LayerOrdering::class)]
#[UsesClass(LayoutItem::class)]
#[UsesClass(LayoutItemKind::class)]
#[UsesClass(RowPlacement::class)]
#[Small]
final class TerminalRendererTest extends TestCase
{
    public function testRenderWritesTheDrawingOneLineAtATime(): void
    {
        $diagram = new Diagram();
        $diagram->add(new DiagramNode('A'));
        $diagram->add(new DiagramNode('B'));
        $diagram->add(new DiagramNode('C'));
        $diagram->relate(new DiagramEdge('A', 'B'));
        $diagram->relate(new DiagramEdge('A', 'C'));
        $output = new BufferedOutput();

        (new TerminalRenderer())->render($diagram, $output);

        self::assertSame("    ┌──▶ B\nA ──┤\n    └──▶ C\n", $output->fetch());
    }

    public function testRenderWritesNothingForADrawingThatHoldsNothing(): void
    {
        $output = new BufferedOutput();

        (new TerminalRenderer())->render(new Diagram(), $output);

        self::assertSame('', $output->fetch());
    }

    public function testRenderWritesANameThatLooksLikeAConsoleTagAsItIs(): void
    {
        $diagram = new Diagram();
        $diagram->add(new DiagramNode('<info>A</info>'));
        $output = new BufferedOutput();

        (new TerminalRenderer())->render($diagram, $output);

        self::assertSame("<info>A</info>\n", $output->fetch());
    }

    public function testDrawJoinsASymbolToTheOneItPointsAtWithAnArrow(): void
    {
        $diagram = new Diagram();
        $diagram->add(new DiagramNode('A'));
        $diagram->add(new DiagramNode('B'));
        $diagram->relate(new DiagramEdge('A', 'B'));

        self::assertSame(['A ──▶ B'], (new TerminalRenderer(80))->draw($diagram));
    }

    public function testDrawIsNoLinesForADrawingThatHoldsNothing(): void
    {
        self::assertSame([], (new TerminalRenderer(80))->draw(new Diagram()));
    }

    public function testDrawWritesASymbolNothingIsJoinedToAsItsNameAlone(): void
    {
        $diagram = new Diagram();
        $diagram->add(new DiagramNode('A'));

        self::assertSame(['A'], (new TerminalRenderer(80))->draw($diagram));
    }

    public function testDrawWritesSymbolsNothingJoinsOneBelowAnother(): void
    {
        $diagram = new Diagram();
        $diagram->add(new DiagramNode('A'));
        $diagram->add(new DiagramNode('B'));

        self::assertSame(['A', 'B'], (new TerminalRenderer(80))->draw($diagram));
    }

    public function testDrawDrawsAChainOnOneLine(): void
    {
        $diagram = new Diagram();
        $diagram->add(new DiagramNode('A'));
        $diagram->add(new DiagramNode('B'));
        $diagram->add(new DiagramNode('C'));
        $diagram->add(new DiagramNode('D'));
        $diagram->relate(new DiagramEdge('A', 'B'));
        $diagram->relate(new DiagramEdge('B', 'C'));
        $diagram->relate(new DiagramEdge('C', 'D'));

        self::assertSame(['A ──▶ B ──▶ C ──▶ D'], (new TerminalRenderer(80))->draw($diagram));
    }

    public function testDrawFansOutFromASymbolBetweenTheSymbolsItPointsAt(): void
    {
        $diagram = new Diagram();
        $diagram->add(new DiagramNode('A'));
        $diagram->add(new DiagramNode('B'));
        $diagram->add(new DiagramNode('C'));
        $diagram->relate(new DiagramEdge('A', 'B'));
        $diagram->relate(new DiagramEdge('A', 'C'));

        self::assertSame(
            [
                '    ┌──▶ B',
                'A ──┤',
                '    └──▶ C',
            ],
            (new TerminalRenderer(80))->draw($diagram),
        );
    }

    public function testDrawFansOutStraightOnToTheSymbolOnItsOwnLine(): void
    {
        $diagram = new Diagram();
        $diagram->add(new DiagramNode('A'));
        $diagram->add(new DiagramNode('B'));
        $diagram->add(new DiagramNode('C'));
        $diagram->add(new DiagramNode('D'));
        $diagram->relate(new DiagramEdge('A', 'B'));
        $diagram->relate(new DiagramEdge('A', 'C'));
        $diagram->relate(new DiagramEdge('A', 'D'));

        self::assertSame(
            [
                '    ┌──▶ B',
                'A ──┼──▶ C',
                '    └──▶ D',
            ],
            (new TerminalRenderer(80))->draw($diagram),
        );
    }

    public function testDrawGathersTheArrowsArrivingAtOneSymbol(): void
    {
        $diagram = new Diagram();
        $diagram->add(new DiagramNode('A'));
        $diagram->add(new DiagramNode('B'));
        $diagram->add(new DiagramNode('C'));
        $diagram->relate(new DiagramEdge('A', 'C'));
        $diagram->relate(new DiagramEdge('B', 'C'));

        self::assertSame(
            [
                'A ──┐',
                '    ├──▶ C',
                'B ──┘',
            ],
            (new TerminalRenderer(80))->draw($diagram),
        );
    }

    public function testDrawFansOutAndGathersAgainAroundADiamond(): void
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
            [
                '    ┌──▶ B ──┐',
                'A ──┤        ├──▶ D',
                '    └──▶ C ──┘',
            ],
            (new TerminalRenderer(80))->draw($diagram),
        );
    }

    public function testDrawSendsAnArrowFromAFanOverToAGatheringOnALineOfItsOwn(): void
    {
        $diagram = new Diagram();
        $diagram->add(new DiagramNode('A'));
        $diagram->add(new DiagramNode('D'));
        $diagram->add(new DiagramNode('B'));
        $diagram->add(new DiagramNode('C'));
        $diagram->relate(new DiagramEdge('A', 'B'));
        $diagram->relate(new DiagramEdge('A', 'C'));
        $diagram->relate(new DiagramEdge('D', 'C'));

        self::assertSame(
            [
                'A ──┬────▶ B',
                '    └─┐',
                'D ────┴──▶ C',
            ],
            (new TerminalRenderer(80))->draw($diagram),
        );
    }

    public function testDrawCrossesTheLaneOfAnotherArrowWithoutJoiningIt(): void
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

        self::assertSame(
            [
                '    ┌───┐',
                'A ──│─┐ ├────▶ C',
                '    │ ├─┘',
                '    │ └───┐',
                'B ──┤     ├──▶ D',
                '    └─────┘',
            ],
            (new TerminalRenderer(80))->draw($diagram),
        );
    }

    public function testDrawPointsAnArrowWithinAColumnAtAReference(): void
    {
        $diagram = new Diagram();
        $diagram->add(new DiagramNode('A'));
        $diagram->add(new DiagramNode('B'));
        $diagram->add(new DiagramNode('C'));
        $diagram->relate(new DiagramEdge('A', 'B'));
        $diagram->relate(new DiagramEdge('A', 'C'));
        $diagram->relate(new DiagramEdge('B', 'C'));

        self::assertSame(
            [
                '    ┌──▶ B ──▶ C (*)',
                'A ──┤',
                '    └──▶ C',
            ],
            (new TerminalRenderer(80))->draw($diagram),
        );
    }

    public function testDrawMarksAnArrowThatClosesACycleAsRecursion(): void
    {
        $diagram = new Diagram();
        $diagram->add(new DiagramNode('A'));
        $diagram->add(new DiagramNode('B'));
        $diagram->add(new DiagramNode('C'));
        $diagram->relate(new DiagramEdge('A', 'B'));
        $diagram->relate(new DiagramEdge('B', 'C'));
        $diagram->relate(new DiagramEdge('C', 'A'));

        self::assertSame(['A ──▶ B ──▶ C ──▶ A (recursive)'], (new TerminalRenderer(80))->draw($diagram));
    }

    public function testDrawMarksASymbolPointingAtItselfAsRecursion(): void
    {
        $diagram = new Diagram();
        $diagram->add(new DiagramNode('A'));
        $diagram->add(new DiagramNode('B'));
        $diagram->relate(new DiagramEdge('A', 'A'));
        $diagram->relate(new DiagramEdge('A', 'B'));

        self::assertSame(
            [
                '    ┌──▶ B',
                'A ──┤',
                '    └──▶ A (recursive)',
            ],
            (new TerminalRenderer(80))->draw($diagram),
        );
    }

    public function testDrawDrawsTwoRelationsBetweenTheSameSymbolsAsOneArrow(): void
    {
        $diagram = new Diagram();
        $diagram->add(new DiagramNode('A'));
        $diagram->add(new DiagramNode('B'));
        $diagram->relate(new DiagramEdge('A', 'B', 'calls'));
        $diagram->relate(new DiagramEdge('A', 'B', 'names'));

        self::assertSame(['A ──▶ B'], (new TerminalRenderer(80))->draw($diagram));
    }

    public function testDrawLeavesOutARelationToASymbolTheDrawingDoesNotHold(): void
    {
        $diagram = new Diagram();
        $diagram->add(new DiagramNode('A'));
        $diagram->relate(new DiagramEdge('A', 'B'));

        self::assertSame(['A'], (new TerminalRenderer(80))->draw($diagram));
    }

    public function testDrawCutsADrawingWiderThanItsWidthIntoBandsThatCarryOnFromTheSymbolMarked(): void
    {
        $diagram = new Diagram();
        $diagram->add(new DiagramNode('A'));
        $diagram->add(new DiagramNode('B'));
        $diagram->add(new DiagramNode('C'));
        $diagram->add(new DiagramNode('D'));
        $diagram->relate(new DiagramEdge('A', 'B'));
        $diagram->relate(new DiagramEdge('A', 'C'));
        $diagram->relate(new DiagramEdge('B', 'D'));

        self::assertSame(
            [
                '    ┌──▶ B …',
                'A ──┤',
                '    └──▶ C',
                '',
                'B ──▶ D',
            ],
            (new TerminalRenderer(10))->draw($diagram),
        );
    }

    public function testDrawCarriesEverySymbolThatGoesOnIntoTheNextBandReferencesIncluded(): void
    {
        $diagram = new Diagram();
        $diagram->add(new DiagramNode('App\Alpha'));
        $diagram->add(new DiagramNode('App\Beta'));
        $diagram->add(new DiagramNode('App\Gamma'));
        $diagram->add(new DiagramNode('App\Delta'));
        $diagram->relate(new DiagramEdge('App\Alpha', 'App\Beta'));
        $diagram->relate(new DiagramEdge('App\Beta', 'App\Gamma'));
        $diagram->relate(new DiagramEdge('App\Alpha', 'App\Gamma'));
        $diagram->relate(new DiagramEdge('App\Gamma', 'App\Delta'));

        self::assertSame(
            [
                '            ┌──▶ App\Beta …',
                'App\Alpha ──┤',
                '            └──▶ App\Gamma …',
                '',
                'App\Beta ───▶ App\Gamma (*)',
                'App\Gamma ──▶ App\Delta',
            ],
            (new TerminalRenderer(30))->draw($diagram),
        );
    }

    public function testDrawDrawsTheSameDiagramInOneBandWhenItFits(): void
    {
        $diagram = new Diagram();
        $diagram->add(new DiagramNode('A'));
        $diagram->add(new DiagramNode('B'));
        $diagram->add(new DiagramNode('C'));
        $diagram->add(new DiagramNode('D'));
        $diagram->relate(new DiagramEdge('A', 'B'));
        $diagram->relate(new DiagramEdge('A', 'C'));
        $diagram->relate(new DiagramEdge('B', 'D'));

        self::assertSame(
            [
                '    ┌──▶ B ──▶ D',
                'A ──┤',
                '    └──▶ C',
            ],
            (new TerminalRenderer(16))->draw($diagram),
        );
    }

    public function testDrawKeepsTwoColumnsInABandHoweverNarrowItsWidth(): void
    {
        $diagram = new Diagram();
        $diagram->add(new DiagramNode('Alpha'));
        $diagram->add(new DiagramNode('Beta'));
        $diagram->relate(new DiagramEdge('Alpha', 'Beta'));

        self::assertSame(['Alpha ──▶ Beta'], (new TerminalRenderer(1))->draw($diagram));
    }

    public function testDrawMeasuresAWideNameByTheColumnsItTakes(): void
    {
        $diagram = new Diagram();
        $diagram->add(new DiagramNode('A'));
        $diagram->add(new DiagramNode('請求書'));
        $diagram->add(new DiagramNode('B'));
        $diagram->add(new DiagramNode('D'));
        $diagram->relate(new DiagramEdge('A', '請求書'));
        $diagram->relate(new DiagramEdge('A', 'B'));
        $diagram->relate(new DiagramEdge('請求書', 'D'));
        $diagram->relate(new DiagramEdge('B', 'D'));

        self::assertSame(
            [
                '    ┌──▶ 請求書 ──┐',
                'A ──┤             ├──▶ D',
                '    └──▶ B ───────┘',
            ],
            (new TerminalRenderer(80))->draw($diagram),
        );
    }

    public function testDrawLinesUpTheLaneAfterAWideName(): void
    {
        $diagram = new Diagram();
        $diagram->add(new DiagramNode('請求書'));
        $diagram->add(new DiagramNode('A'));
        $diagram->add(new DiagramNode('B'));
        $diagram->relate(new DiagramEdge('請求書', 'A'));
        $diagram->relate(new DiagramEdge('請求書', 'B'));

        self::assertSame(
            [
                '         ┌──▶ A',
                '請求書 ──┤',
                '         └──▶ B',
            ],
            (new TerminalRenderer(80))->draw($diagram),
        );
    }

    public function testBandsIsOneBandWhenTheDrawingFits(): void
    {
        $diagram = new Diagram();
        $diagram->add(new DiagramNode('A'));
        $diagram->add(new DiagramNode('B'));
        $diagram->relate(new DiagramEdge('A', 'B'));
        $layout = LayeredLayout::of($diagram);

        self::assertSame([[0, 1]], (new TerminalRenderer(80))->bands($layout, [LaneRouting::of($layout, 0)]));
    }

    public function testBandsOfASingleColumnIsThatColumn(): void
    {
        $diagram = new Diagram();
        $diagram->add(new DiagramNode('A'));

        self::assertSame([[0, 0]], (new TerminalRenderer(80))->bands(LayeredLayout::of($diagram), []));
    }

    public function testBandsCutsTheColumnsSoThatEachBandFitsTheLastColumnOfOneBeingTheFirstOfTheNext(): void
    {
        $diagram = new Diagram();
        $diagram->add(new DiagramNode('Alpha'));
        $diagram->add(new DiagramNode('Beta'));
        $diagram->add(new DiagramNode('Gamma'));
        $diagram->add(new DiagramNode('Delta'));
        $diagram->relate(new DiagramEdge('Alpha', 'Beta'));
        $diagram->relate(new DiagramEdge('Beta', 'Gamma'));
        $diagram->relate(new DiagramEdge('Gamma', 'Delta'));
        $layout = LayeredLayout::of($diagram);
        $routes = [LaneRouting::of($layout, 0), LaneRouting::of($layout, 1), LaneRouting::of($layout, 2)];

        self::assertSame([[0, 1], [1, 2], [2, 3]], (new TerminalRenderer(20))->bands($layout, $routes));
    }

    public function testBandsCountsTheMarkOfASymbolThatGoesOnInTheNextBand(): void
    {
        $diagram = new Diagram();
        $diagram->add(new DiagramNode('Alpha'));
        $diagram->add(new DiagramNode('Beta'));
        $diagram->add(new DiagramNode('Gamma'));
        $diagram->add(new DiagramNode('Delta'));
        $diagram->relate(new DiagramEdge('Alpha', 'Beta'));
        $diagram->relate(new DiagramEdge('Beta', 'Gamma'));
        $diagram->relate(new DiagramEdge('Gamma', 'Delta'));
        $layout = LayeredLayout::of($diagram);
        $routes = [LaneRouting::of($layout, 0), LaneRouting::of($layout, 1), LaneRouting::of($layout, 2)];

        self::assertSame([[0, 2], [2, 3]], (new TerminalRenderer(26))->bands($layout, $routes));
        self::assertSame([[0, 1], [1, 3]], (new TerminalRenderer(25))->bands($layout, $routes));
    }

    public function testBandsKeepsTwoColumnsInABandHoweverNarrowItsWidth(): void
    {
        $diagram = new Diagram();
        $diagram->add(new DiagramNode('Alpha'));
        $diagram->add(new DiagramNode('Beta'));
        $diagram->add(new DiagramNode('Gamma'));
        $diagram->relate(new DiagramEdge('Alpha', 'Beta'));
        $diagram->relate(new DiagramEdge('Beta', 'Gamma'));
        $layout = LayeredLayout::of($diagram);

        self::assertSame(
            [[0, 1], [1, 2]],
            (new TerminalRenderer(1))->bands($layout, [LaneRouting::of($layout, 0), LaneRouting::of($layout, 1)]),
        );
    }

    public function testReachIsAsWideAsTheNamesAndTheArrowBetweenThem(): void
    {
        $diagram = new Diagram();
        $diagram->add(new DiagramNode('A'));
        $diagram->add(new DiagramNode('B'));
        $diagram->relate(new DiagramEdge('A', 'B'));
        $layout = LayeredLayout::of($diagram);

        self::assertSame(7, (new TerminalRenderer())->reach($layout, [LaneRouting::of($layout, 0)], 0, 1));
    }

    public function testReachMakesRoomForTheLanesBetweenTwoColumns(): void
    {
        $diagram = new Diagram();
        $diagram->add(new DiagramNode('A'));
        $diagram->add(new DiagramNode('B'));
        $diagram->add(new DiagramNode('C'));
        $diagram->relate(new DiagramEdge('A', 'B'));
        $diagram->relate(new DiagramEdge('A', 'C'));
        $layout = LayeredLayout::of($diagram);

        self::assertSame(10, (new TerminalRenderer())->reach($layout, [LaneRouting::of($layout, 0)], 0, 1));
    }

    public function testReachCountsTheMarkOfASymbolThatGoesOnInTheNextBand(): void
    {
        $diagram = new Diagram();
        $diagram->add(new DiagramNode('Alpha'));
        $diagram->add(new DiagramNode('Beta'));
        $diagram->add(new DiagramNode('Gamma'));
        $diagram->relate(new DiagramEdge('Alpha', 'Beta'));
        $diagram->relate(new DiagramEdge('Beta', 'Gamma'));
        $layout = LayeredLayout::of($diagram);

        self::assertSame(16, (new TerminalRenderer())->reach($layout, [LaneRouting::of($layout, 0), LaneRouting::of($layout, 1)], 0, 1));
    }

    public function testReachOfABandOfOneColumnIsItsWidestName(): void
    {
        $diagram = new Diagram();
        $diagram->add(new DiagramNode('Alpha'));
        $diagram->add(new DiagramNode('Beta'));

        self::assertSame(5, (new TerminalRenderer())->reach(LayeredLayout::of($diagram), [], 0, 0));
    }

    public function testLabelsWritesASymbolAsItsName(): void
    {
        $diagram = new Diagram();
        $diagram->add(new DiagramNode('A'));

        self::assertSame(["symbol\0A" => 'A'], (new TerminalRenderer())->labels(LayeredLayout::of($diagram), 0, 0));
    }

    public function testLabelsWritesAReferenceAsTheSymbolItPointsAtMarked(): void
    {
        $diagram = new Diagram();
        $diagram->add(new DiagramNode('A'));
        $diagram->add(new DiagramNode('B'));
        $diagram->add(new DiagramNode('C'));
        $diagram->relate(new DiagramEdge('A', 'B'));
        $diagram->relate(new DiagramEdge('A', 'C'));
        $diagram->relate(new DiagramEdge('B', 'C'));

        self::assertSame(
            ["symbol\0A" => 'A', "symbol\0B" => 'B', "symbol\0C" => 'C', "reference\0B\0C" => 'C (*)'],
            (new TerminalRenderer())->labels(LayeredLayout::of($diagram), 0, 2),
        );
    }

    public function testLabelsMarksASymbolThatGoesOnInTheNextBand(): void
    {
        $diagram = new Diagram();
        $diagram->add(new DiagramNode('A'));
        $diagram->add(new DiagramNode('B'));
        $diagram->add(new DiagramNode('C'));
        $diagram->relate(new DiagramEdge('A', 'B'));
        $diagram->relate(new DiagramEdge('B', 'C'));

        self::assertSame(
            ["symbol\0A" => 'A', "symbol\0B" => 'B …'],
            (new TerminalRenderer())->labels(LayeredLayout::of($diagram), 0, 1),
        );
    }

    public function testLabelsLeavesOutAPlaceNothingLeavesInTheColumnALaterBandStartsWith(): void
    {
        $diagram = new Diagram();
        $diagram->add(new DiagramNode('A'));
        $diagram->add(new DiagramNode('B'));
        $diagram->add(new DiagramNode('C'));
        $diagram->add(new DiagramNode('D'));
        $diagram->relate(new DiagramEdge('A', 'B'));
        $diagram->relate(new DiagramEdge('A', 'C'));
        $diagram->relate(new DiagramEdge('B', 'D'));

        self::assertSame(
            ["symbol\0B" => 'B', "symbol\0D" => 'D'],
            (new TerminalRenderer())->labels(LayeredLayout::of($diagram), 1, 2),
        );
    }

    public function testLabelsKeepsEveryPlaceInTheFirstColumnOfTheDrawing(): void
    {
        $diagram = new Diagram();
        $diagram->add(new DiagramNode('A'));
        $diagram->add(new DiagramNode('B'));

        self::assertSame(
            ["symbol\0A" => 'A', "symbol\0B" => 'B'],
            (new TerminalRenderer())->labels(LayeredLayout::of($diagram), 0, 0),
        );
    }

    public function testLabelMarksASymbolPointedAtAgainAsTheTreeMarksIt(): void
    {
        $layout = new DiagramLayout([], [[], []], [], []);

        self::assertSame('A (*)', TerminalRenderer::label($layout, LayoutItem::reference('B', 'A', 1, false), 0, 1));
    }

    public function testLabelMarksASymbolAnArrowClosingACyclePointsAtAsRecursion(): void
    {
        $layout = new DiagramLayout([], [[], []], [], []);

        self::assertSame('A (recursive)', TerminalRenderer::label($layout, LayoutItem::reference('B', 'A', 1, true), 0, 1));
    }

    public function testLabelMarksASymbolThatGoesOnInTheNextBandWhereThisOneEnds(): void
    {
        $a = LayoutItem::symbol('A', 1);
        $b = LayoutItem::symbol('B', 2);
        $layout = new DiagramLayout([$a->key => $a, $b->key => $b], [[], [$a->key], [$b->key]], [], [[$a->key, $b->key]]);

        self::assertSame('A …', TerminalRenderer::label($layout, $a, 0, 1));
    }

    public function testLabelDoesNotMarkASymbolNothingLeaves(): void
    {
        $a = LayoutItem::symbol('A', 1);
        $b = LayoutItem::symbol('B', 2);
        $layout = new DiagramLayout([$a->key => $a, $b->key => $b], [[], [$a->key], [$b->key]], [], []);

        self::assertSame('A', TerminalRenderer::label($layout, $a, 0, 1));
    }

    public function testLabelDoesNotMarkASymbolInTheLastColumnOfTheDrawing(): void
    {
        $a = LayoutItem::symbol('A', 1);
        $b = LayoutItem::symbol('B', 2);
        $layout = new DiagramLayout([$a->key => $a, $b->key => $b], [[], [$a->key], [$b->key]], [], [[$a->key, $b->key]]);

        self::assertSame('B', TerminalRenderer::label($layout, $b, 1, 2));
    }

    public function testLabelDoesNotMarkASymbolBeforeTheLastColumnOfItsBand(): void
    {
        $a = LayoutItem::symbol('A', 1);
        $b = LayoutItem::symbol('B', 2);
        $layout = new DiagramLayout([$a->key => $a, $b->key => $b], [[], [$a->key], [$b->key], []], [], [[$a->key, $b->key]]);

        self::assertSame('A', TerminalRenderer::label($layout, $a, 0, 2));
    }

    public function testLabelDoesNotMarkASymbolInABandOfOneColumn(): void
    {
        $a = LayoutItem::symbol('A', 1);
        $b = LayoutItem::symbol('B', 2);
        $layout = new DiagramLayout([$a->key => $a, $b->key => $b], [[], [$a->key], [$b->key]], [], [[$a->key, $b->key]]);

        self::assertSame('A', TerminalRenderer::label($layout, $a, 1, 1));
    }

    public function testColumnsFollowsAColumnWithNoLanesAfterItWithAShortArrow(): void
    {
        $diagram = new Diagram();
        $diagram->add(new DiagramNode('A'));
        $diagram->add(new DiagramNode('B'));
        $diagram->relate(new DiagramEdge('A', 'B'));
        $layout = LayeredLayout::of($diagram);
        $renderer = new TerminalRenderer();

        self::assertSame([0 => 0, 1 => 6], $renderer->columns($layout, [LaneRouting::of($layout, 0)], $renderer->labels($layout, 0, 1), 0, 1));
    }

    public function testColumnsMakesRoomForEveryLaneBetweenTwoColumns(): void
    {
        $diagram = new Diagram();
        $diagram->add(new DiagramNode('A'));
        $diagram->add(new DiagramNode('D'));
        $diagram->add(new DiagramNode('B'));
        $diagram->add(new DiagramNode('C'));
        $diagram->relate(new DiagramEdge('A', 'B'));
        $diagram->relate(new DiagramEdge('A', 'C'));
        $diagram->relate(new DiagramEdge('D', 'C'));
        $layout = LayeredLayout::of($diagram);
        $renderer = new TerminalRenderer();

        self::assertSame([0 => 0, 1 => 11], $renderer->columns($layout, [LaneRouting::of($layout, 0)], $renderer->labels($layout, 0, 1), 0, 1));
    }

    public function testColumnsStartsALaterBandAtTheLeftEdge(): void
    {
        $diagram = new Diagram();
        $diagram->add(new DiagramNode('Alpha'));
        $diagram->add(new DiagramNode('Beta'));
        $diagram->add(new DiagramNode('Gamma'));
        $diagram->add(new DiagramNode('Delta'));
        $diagram->relate(new DiagramEdge('Alpha', 'Beta'));
        $diagram->relate(new DiagramEdge('Beta', 'Gamma'));
        $diagram->relate(new DiagramEdge('Gamma', 'Delta'));
        $layout = LayeredLayout::of($diagram);
        $routes = [LaneRouting::of($layout, 0), LaneRouting::of($layout, 1), LaneRouting::of($layout, 2)];
        $renderer = new TerminalRenderer();

        self::assertSame([1 => 0, 2 => 9, 3 => 19], $renderer->columns($layout, $routes, $renderer->labels($layout, 1, 3), 1, 3));
    }

    public function testWidthOfIsTheWidthOfTheWidestName(): void
    {
        $layout = new DiagramLayout([], [['a', 'b']], [], []);

        self::assertSame(11, TerminalRenderer::widthOf($layout, ['a' => 'App', 'b' => 'App\Invoice'], 0));
    }

    public function testWidthOfCountsAWideCharacterAsTwoColumns(): void
    {
        $layout = new DiagramLayout([], [['a', 'b']], [], []);

        self::assertSame(6, TerminalRenderer::widthOf($layout, ['a' => 'App', 'b' => '請求書'], 0));
    }

    public function testWidthOfCountsAPlaceWithNothingWrittenForItAsNothing(): void
    {
        $layout = new DiagramLayout([], [['a', 'b']], [], []);

        self::assertSame(3, TerminalRenderer::widthOf($layout, ['a' => 'App'], 0));
    }

    public function testWidthOfAColumnWithNothingInItIsNothing(): void
    {
        $layout = new DiagramLayout([], [[]], [], []);

        self::assertSame(0, TerminalRenderer::widthOf($layout, [], 0));
    }

    public function testBandDrawsASymbolFanningOutBetweenTheSymbolsItPointsAt(): void
    {
        $diagram = new Diagram();
        $diagram->add(new DiagramNode('A'));
        $diagram->add(new DiagramNode('B'));
        $diagram->add(new DiagramNode('C'));
        $diagram->relate(new DiagramEdge('A', 'B'));
        $diagram->relate(new DiagramEdge('A', 'C'));
        $layout = LayeredLayout::of($diagram);

        self::assertSame(
            [
                '    ┌──▶ B',
                'A ──┤',
                '    └──▶ C',
            ],
            (new TerminalRenderer())->band($layout, [LaneRouting::of($layout, 0)], 0, 1),
        );
    }

    public function testBandDrawsOnlyTheColumnsItIsGiven(): void
    {
        $diagram = new Diagram();
        $diagram->add(new DiagramNode('A'));
        $diagram->add(new DiagramNode('B'));
        $diagram->add(new DiagramNode('C'));
        $diagram->relate(new DiagramEdge('A', 'B'));
        $diagram->relate(new DiagramEdge('B', 'C'));
        $layout = LayeredLayout::of($diagram);

        self::assertSame(
            ['A ──▶ B …'],
            (new TerminalRenderer())->band($layout, [LaneRouting::of($layout, 0), LaneRouting::of($layout, 1)], 0, 1),
        );
    }

    public function testBandDrawsALaterBandFromTheLeftEdge(): void
    {
        $diagram = new Diagram();
        $diagram->add(new DiagramNode('A'));
        $diagram->add(new DiagramNode('B'));
        $diagram->add(new DiagramNode('C'));
        $diagram->relate(new DiagramEdge('A', 'B'));
        $diagram->relate(new DiagramEdge('B', 'C'));
        $layout = LayeredLayout::of($diagram);

        self::assertSame(
            ['B ──▶ C'],
            (new TerminalRenderer())->band($layout, [LaneRouting::of($layout, 0), LaneRouting::of($layout, 1)], 1, 2),
        );
    }

    public function testConnectDrawsAStraightArrowFromAfterTheNameToJustBeforeTheNext(): void
    {
        $diagram = new Diagram();
        $diagram->add(new DiagramNode('A'));
        $diagram->add(new DiagramNode('B'));
        $diagram->relate(new DiagramEdge('A', 'B'));
        $layout = LayeredLayout::of($diagram);
        $canvas = new DiagramCanvas();

        TerminalRenderer::connect($canvas, $layout, LaneRouting::of($layout, 0), ["symbol\0A" => 'A', "symbol\0B" => 'B'], 0, 1, 6);

        self::assertSame(['  ──▶'], $canvas->lines());
    }

    public function testConnectDrawsTheLaneArrowsFanOutAlong(): void
    {
        $diagram = new Diagram();
        $diagram->add(new DiagramNode('A'));
        $diagram->add(new DiagramNode('B'));
        $diagram->add(new DiagramNode('C'));
        $diagram->relate(new DiagramEdge('A', 'B'));
        $diagram->relate(new DiagramEdge('A', 'C'));
        $layout = LayeredLayout::of($diagram);
        $canvas = new DiagramCanvas();

        TerminalRenderer::connect($canvas, $layout, LaneRouting::of($layout, 0), ["symbol\0A" => 'A', "symbol\0B" => 'B', "symbol\0C" => 'C'], 0, 1, 9);

        self::assertSame(['    ┌──▶', '  ──┤', '    └──▶'], $canvas->lines());
    }

    public function testConnectSendsAnArrowOverFromOneLaneToAnotherOnItsOwnLine(): void
    {
        $diagram = new Diagram();
        $diagram->add(new DiagramNode('A'));
        $diagram->add(new DiagramNode('D'));
        $diagram->add(new DiagramNode('B'));
        $diagram->add(new DiagramNode('C'));
        $diagram->relate(new DiagramEdge('A', 'B'));
        $diagram->relate(new DiagramEdge('A', 'C'));
        $diagram->relate(new DiagramEdge('D', 'C'));
        $layout = LayeredLayout::of($diagram);
        $canvas = new DiagramCanvas();

        TerminalRenderer::connect(
            $canvas,
            $layout,
            LaneRouting::of($layout, 0),
            ["symbol\0A" => 'A', "symbol\0D" => 'D', "symbol\0B" => 'B', "symbol\0C" => 'C'],
            0,
            1,
            11,
        );

        self::assertSame(['  ──┬────▶', '    └─┐', '  ────┴──▶'], $canvas->lines());
    }

    public function testLeaveStartsAnArrowASpaceAfterTheName(): void
    {
        self::assertSame(8, TerminalRenderer::leave('App', 4));
    }

    public function testLeaveCountsAWideNameByTheColumnsItTakes(): void
    {
        self::assertSame(7, TerminalRenderer::leave('請求書', 0));
    }

    public function testArriveDrawsTheLastStretchOfAnArrowWithAnArrowhead(): void
    {
        $diagram = new Diagram();
        $diagram->add(new DiagramNode('A'));
        $layout = LayeredLayout::of($diagram);
        $canvas = new DiagramCanvas();

        TerminalRenderer::arrive($canvas, $layout, "symbol\0A", 0, 5, $canvas->arrow());

        self::assertSame(['───▶'], $canvas->lines());
    }

    public function testArriveLeavesASpaceBetweenTheArrowheadAndTheName(): void
    {
        $diagram = new Diagram();
        $diagram->add(new DiagramNode('A'));
        $layout = LayeredLayout::of($diagram);
        $canvas = new DiagramCanvas();
        $canvas->write(0, 5, 'A');

        TerminalRenderer::arrive($canvas, $layout, "symbol\0A", 0, 5, $canvas->arrow());

        self::assertSame(['───▶ A'], $canvas->lines());
    }

    public function testArriveDrawsOnTheLineOfThePlaceItArrivesAt(): void
    {
        $layout = new DiagramLayout([], [], ["symbol\0B" => 4], []);
        $canvas = new DiagramCanvas();
        $canvas->write(0, 0, 'A');

        TerminalRenderer::arrive($canvas, $layout, "symbol\0B", 2, 7, $canvas->arrow());

        self::assertSame(['A', '  ───▶'], $canvas->lines());
    }

    public function testDrawSendsAnArrowThatNoOrderOfLanesKeepsApartOverALineOfItsOwn(): void
    {
        $diagram = new Diagram();
        $diagram->add(new DiagramNode('Root'));
        $diagram->add(new DiagramNode('A'));
        $diagram->add(new DiagramNode('B'));
        $diagram->add(new DiagramNode('C'));
        $diagram->add(new DiagramNode('D'));
        $diagram->relate(new DiagramEdge('Root', 'A'));
        $diagram->relate(new DiagramEdge('Root', 'B'));
        $diagram->relate(new DiagramEdge('Root', 'C'));
        $diagram->relate(new DiagramEdge('B', 'A'));
        $diagram->relate(new DiagramEdge('B', 'B'));
        $diagram->relate(new DiagramEdge('C', 'A'));
        $diagram->relate(new DiagramEdge('C', 'D'));
        $diagram->relate(new DiagramEdge('D', 'C'));

        self::assertSame(
            [
                '       ┌──▶ A   ┌──────▶ A (*)',
                'Root ──┼──▶ B ──┤ ┌────▶ D ──────────────▶ C (recursive)',
                '       │        └─│─┐',
                '       └──▶ C ────┤ └──▶ B (recursive)',
                '                  └────▶ A (*)',
            ],
            (new TerminalRenderer(120))->draw($diagram),
        );
    }
}
