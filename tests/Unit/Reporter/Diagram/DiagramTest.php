<?php

declare(strict_types=1);

namespace Tests\Unit\Reporter\Diagram;

use App\Reporter\Diagram\Diagram;
use App\Reporter\Diagram\DiagramEdge;
use App\Reporter\Diagram\DiagramNode;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(Diagram::class)]
#[UsesClass(DiagramEdge::class)]
#[UsesClass(DiagramNode::class)]
#[Small]
final class DiagramTest extends TestCase
{
    public function testAddKeepsTheNumberASymbolDrawnTwiceWasFirstGiven(): void
    {
        $diagram = new Diagram();
        $diagram->add(new DiagramNode('a', 'class'));
        $diagram->add(new DiagramNode('a', 'method'));

        self::assertSame('class', $diagram->nodes()[0]->kind);
    }

    public function testAddDrawsASymbolDrawnTwiceOnce(): void
    {
        $diagram = new Diagram();
        $diagram->add(new DiagramNode('a'));
        $diagram->add(new DiagramNode('a'));

        self::assertCount(1, $diagram->nodes());
    }

    public function testRelateDrawsARelationDrawnTwiceOnce(): void
    {
        $diagram = new Diagram();
        $diagram->relate(new DiagramEdge('a', 'b', 'calls'));
        $diagram->relate(new DiagramEdge('a', 'b', 'calls'));

        self::assertCount(1, $diagram->edges());
    }

    public function testRelateDrawsTwoDifferentRelationsBetweenTheSameSymbols(): void
    {
        $diagram = new Diagram();
        $diagram->relate(new DiagramEdge('a', 'b', 'calls'));
        $diagram->relate(new DiagramEdge('a', 'b', 'parameterType'));

        self::assertCount(2, $diagram->edges());
    }

    public function testNodesHoldsNothingInADrawingNothingWasAddedTo(): void
    {
        self::assertSame([], (new Diagram())->nodes());
    }

    public function testEdgesHoldsNothingInADrawingNothingWasAddedTo(): void
    {
        self::assertSame([], (new Diagram())->edges());
    }

    public function testLeavingReadsTheRelationsLeavingASymbol(): void
    {
        $diagram = new Diagram();
        $diagram->add(new DiagramNode('a'));
        $diagram->add(new DiagramNode('b'));
        $diagram->relate(new DiagramEdge('a', 'b', 'calls'));

        self::assertCount(1, $diagram->leaving('a'));
    }

    public function testLeavingLeavesOutARelationToASymbolThatWasNotDrawn(): void
    {
        $diagram = new Diagram();
        $diagram->add(new DiagramNode('a'));
        $diagram->relate(new DiagramEdge('a', 'b', 'calls'));

        self::assertSame([], $diagram->leaving('a'));
    }

    public function testNumberOfNumbersTheFirstSymbolDrawnOne(): void
    {
        $diagram = new Diagram();
        $diagram->add(new DiagramNode('a'));

        self::assertSame(1, $diagram->numberOf('a'));
    }

    public function testNumberOfNumbersSymbolsInTheOrderTheyWereDrawn(): void
    {
        $diagram = new Diagram();
        $diagram->add(new DiagramNode('a'));
        $diagram->add(new DiagramNode('b'));

        self::assertSame(2, $diagram->numberOf('b'));
    }

    public function testNumberOfFindsNoNumberForASymbolThatWasNeverDrawn(): void
    {
        self::assertNull((new Diagram())->numberOf('a'));
    }

    public function testEmptyReportsADrawingNothingWasAddedTo(): void
    {
        self::assertTrue((new Diagram())->empty());
    }

    public function testEmptyReportsADrawingWithASymbolInIt(): void
    {
        $diagram = new Diagram();
        $diagram->add(new DiagramNode('a'));

        self::assertFalse($diagram->empty());
    }
}
