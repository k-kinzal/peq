<?php

declare(strict_types=1);

namespace Tests\Unit\Gql\Datum;

use App\Gql\Datum\DatumKind;
use App\Gql\Datum\EdgeDatum;
use App\Gql\Datum\NodeDatum;
use App\Gql\Datum\PathDatum;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(PathDatum::class)]
#[UsesClass(DatumKind::class)]
#[UsesClass(EdgeDatum::class)]
#[UsesClass(NodeDatum::class)]
#[Small]
final class PathDatumTest extends TestCase
{
    public function testAtIsAPathThatCrossesNothing(): void
    {
        self::assertSame(0, PathDatum::at(new NodeDatum('App\Invoice'))->length());
    }

    public function testContinuedByMakesThePathOneRelationLonger(): void
    {
        $path = PathDatum::at(new NodeDatum('a'))
            ->continuedBy(new EdgeDatum('e', ['calls'], [], 'a', 'b'), new NodeDatum('b'))
        ;

        self::assertSame(1, $path->length());
    }

    public function testContinuedByLeavesThePathItContinuedAlone(): void
    {
        $path = PathDatum::at(new NodeDatum('a'));
        $path->continuedBy(new EdgeDatum('e', [], [], 'a', 'b'), new NodeDatum('b'));

        self::assertSame(0, $path->length());
    }

    public function testNodesAreTheSymbolsThePathPassesThrough(): void
    {
        $path = PathDatum::at(new NodeDatum('a'))
            ->continuedBy(new EdgeDatum('e', [], [], 'a', 'b'), new NodeDatum('b'))
        ;

        self::assertSame(['a', 'b'], array_map(static fn (NodeDatum $node): string => $node->id, $path->nodes()));
    }

    public function testEdgesAreTheRelationsThePathCrosses(): void
    {
        $path = PathDatum::at(new NodeDatum('a'))
            ->continuedBy(new EdgeDatum('e', [], [], 'a', 'b'), new NodeDatum('b'))
        ;

        self::assertSame(['e'], array_map(static fn (EdgeDatum $edge): string => $edge->id, $path->edges()));
    }

    public function testEdgesOfAPathThatCrossesNothingAreNone(): void
    {
        self::assertSame([], PathDatum::at(new NodeDatum('a'))->edges());
    }

    public function testLastIsTheSymbolThePathEndsAt(): void
    {
        $path = PathDatum::at(new NodeDatum('a'))
            ->continuedBy(new EdgeDatum('e', [], [], 'a', 'b'), new NodeDatum('b'))
        ;

        self::assertSame('b', $path->last()->id);
    }

    public function testLengthCountsRelationsRatherThanSymbols(): void
    {
        $path = PathDatum::at(new NodeDatum('a'))
            ->continuedBy(new EdgeDatum('e', [], [], 'a', 'b'), new NodeDatum('b'))
            ->continuedBy(new EdgeDatum('f', [], [], 'b', 'c'), new NodeDatum('c'))
        ;

        self::assertSame(2, $path->length());
    }

    public function testKindNamesAPathThroughTheGraph(): void
    {
        self::assertSame(DatumKind::Path, PathDatum::at(new NodeDatum('a'))->kind());
    }

    public function testToTextWritesThePathAsTheChainItIs(): void
    {
        $path = PathDatum::at(new NodeDatum('a'))
            ->continuedBy(new EdgeDatum('e', ['calls'], [], 'a', 'b'), new NodeDatum('b'))
        ;

        self::assertSame('a -[calls]-> b', $path->toText());
    }

    public function testToTextDrawsARelationCrossedTheOtherWayPointingBack(): void
    {
        $path = PathDatum::at(new NodeDatum('b'))
            ->continuedBy(new EdgeDatum('e', ['calls'], [], 'a', 'b'), new NodeDatum('a'))
        ;

        self::assertSame('b <-[calls]- a', $path->toText());
    }

    public function testArrowDrawsARelationCrossedTheWayItPoints(): void
    {
        self::assertSame(' -[calls]-> ', PathDatum::arrow(new EdgeDatum('e', ['calls'], [], 'a', 'b'), true));
    }

    public function testArrowDrawsOneCrossedAgainstItPointingBack(): void
    {
        self::assertSame(' <-[calls]- ', PathDatum::arrow(new EdgeDatum('e', ['calls'], [], 'a', 'b'), false));
    }
}
