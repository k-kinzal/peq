<?php

declare(strict_types=1);

namespace Tests\Unit\Gql\Matching;

use App\Gql\Datum\BooleanDatum;
use App\Gql\Datum\DatumKind;
use App\Gql\Datum\DatumOrder;
use App\Gql\Datum\EdgeDatum;
use App\Gql\Datum\IntegerDatum;
use App\Gql\Datum\ListDatum;
use App\Gql\Datum\NodeDatum;
use App\Gql\Datum\NullDatum;
use App\Gql\Datum\PathDatum;
use App\Gql\Datum\StringDatum;
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
#[UsesClass(BooleanDatum::class)]
#[UsesClass(DatumKind::class)]
#[UsesClass(DatumOrder::class)]
#[UsesClass(EdgeDatum::class)]
#[UsesClass(IntegerDatum::class)]
#[UsesClass(ListDatum::class)]
#[UsesClass(NodeDatum::class)]
#[UsesClass(NullDatum::class)]
#[UsesClass(PathDatum::class)]
#[UsesClass(StringDatum::class)]
#[UsesClass(ElementGraph::class)]
#[UsesClass(EdgeDirection::class)]
#[Small]
final class EdgeTraversalTest extends TestCase
{
    public function testFromCrossesTheRelationsThatLeaveWhenThePatternIsDrawnForwards(): void
    {
        $edge = new EdgeDatum('e', [], [], 'a', 'b');
        $graph = new ElementGraph([], ['a' => [$edge]], ['b' => [$edge]]);

        self::assertSame('b', EdgeTraversal::from($graph, 'a', EdgeDirection::Along)[0]->other);
    }

    public function testFromCrossesTheRelationsThatArriveWhenThePatternIsDrawnBackwards(): void
    {
        $edge = new EdgeDatum('e', [], [], 'a', 'b');
        $graph = new ElementGraph([], ['a' => [$edge]], ['b' => [$edge]]);

        self::assertSame('a', EdgeTraversal::from($graph, 'b', EdgeDirection::Against)[0]->other);
    }

    public function testFromCrossesNothingThatLeavesWhenThePatternIsDrawnBackwards(): void
    {
        $edge = new EdgeDatum('e', [], [], 'a', 'b');
        $graph = new ElementGraph([], ['a' => [$edge]], ['b' => [$edge]]);

        self::assertSame([], EdgeTraversal::from($graph, 'a', EdgeDirection::Against));
    }

    public function testFromCrossesBothWaysWhenThePatternIsDrawnWithoutADirection(): void
    {
        $edge = new EdgeDatum('e', [], [], 'a', 'a');
        $graph = new ElementGraph([], ['a' => [$edge]], ['a' => [$edge]]);

        self::assertCount(2, EdgeTraversal::from($graph, 'a', EdgeDirection::Either));
    }

    public function testFromCrossesNothingFromASymbolTheGraphDoesNotHold(): void
    {
        self::assertSame([], EdgeTraversal::from(new ElementGraph([], [], []), 'a', EdgeDirection::Either));
    }

    public function testFromRemembersWhichRelationEachCrossingIs(): void
    {
        $edge = new EdgeDatum('e', ['calls'], [], 'a', 'b');
        $graph = new ElementGraph([], ['a' => [$edge]], ['b' => [$edge]]);

        self::assertSame('e', EdgeTraversal::from($graph, 'a', EdgeDirection::Along)[0]->edge->id);
    }
}
