<?php

declare(strict_types=1);

namespace Tests\Unit\Gql\Matching;

use App\Gql\Datum\EdgeDatum;
use App\Gql\Element\ElementGraph;
use App\Gql\Matching\EdgeTraversal;
use App\Gql\Syntax\Pattern\EdgeDirection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(EdgeTraversal::class)]
#[UsesClass(EdgeDatum::class)]
#[UsesClass(ElementGraph::class)]
#[UsesClass(EdgeDirection::class)]
#[Small]
final class EdgeTraversalTest extends TestCase
{
    public function testFromCrossesTheRelationsThatLeaveWhenThePatternIsDrawnForwards(): void
    {
        $edge = new EdgeDatum('a>b', ['call'], [], 'a', 'b');
        $graph = new ElementGraph([], ['a' => [$edge]], ['b' => [$edge]]);

        self::assertEquals([new EdgeTraversal($edge, 'b')], EdgeTraversal::from($graph, 'a', EdgeDirection::Along));
    }

    public function testFromCrossesTheRelationsThatArriveWhenThePatternIsDrawnBackwards(): void
    {
        $edge = new EdgeDatum('a>b', ['call'], [], 'a', 'b');
        $graph = new ElementGraph([], ['a' => [$edge]], ['b' => [$edge]]);

        self::assertEquals([new EdgeTraversal($edge, 'a')], EdgeTraversal::from($graph, 'b', EdgeDirection::Against));
    }

    public function testFromCrossesNothingThatLeavesWhenThePatternIsDrawnBackwards(): void
    {
        $edge = new EdgeDatum('a>b', ['call'], [], 'a', 'b');
        $graph = new ElementGraph([], ['a' => [$edge]], ['b' => [$edge]]);

        self::assertSame([], EdgeTraversal::from($graph, 'a', EdgeDirection::Against));
    }

    public function testFromCrossesBothWaysWhenThePatternIsDrawnWithoutADirection(): void
    {
        $leaving = new EdgeDatum('b>c', ['call'], [], 'b', 'c');
        $arriving = new EdgeDatum('a>b', ['call'], [], 'a', 'b');
        $graph = new ElementGraph([], ['a' => [$arriving], 'b' => [$leaving]], ['b' => [$arriving], 'c' => [$leaving]]);

        self::assertEquals(
            [new EdgeTraversal($leaving, 'c'), new EdgeTraversal($arriving, 'a')],
            EdgeTraversal::from($graph, 'b', EdgeDirection::Either),
        );
    }

    public function testFromCrossesARelationToItselfOnceEachWayWhenThePatternIsDrawnWithoutADirection(): void
    {
        $edge = new EdgeDatum('a>a', ['call'], [], 'a', 'a');
        $graph = new ElementGraph([], ['a' => [$edge]], ['a' => [$edge]]);

        self::assertEquals(
            [new EdgeTraversal($edge, 'a'), new EdgeTraversal($edge, 'a')],
            EdgeTraversal::from($graph, 'a', EdgeDirection::Either),
        );
    }

    public function testFromCrossesNothingWhenThePatternAsksForARelationThatPointsNeitherWay(): void
    {
        $edge = new EdgeDatum('a>b', ['call'], [], 'a', 'b');
        $graph = new ElementGraph([], ['a' => [$edge]], ['b' => [$edge]]);

        self::assertSame([], EdgeTraversal::from($graph, 'a', EdgeDirection::Undirected));
    }

    public function testFromCrossesNothingArrivingWhenThePatternAsksForARelationThatPointsNeitherWay(): void
    {
        $edge = new EdgeDatum('a>b', ['call'], [], 'a', 'b');
        $graph = new ElementGraph([], ['a' => [$edge]], ['b' => [$edge]]);

        self::assertSame([], EdgeTraversal::from($graph, 'b', EdgeDirection::Undirected));
    }

    public function testFromCrossesNothingFromASymbolTheGraphDoesNotHold(): void
    {
        self::assertSame([], EdgeTraversal::from(new ElementGraph([], [], []), 'a', EdgeDirection::Either));
    }
}
