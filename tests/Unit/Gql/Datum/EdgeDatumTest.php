<?php

declare(strict_types=1);

namespace Tests\Unit\Gql\Datum;

use App\Gql\Datum\DatumKind;
use App\Gql\Datum\EdgeDatum;
use App\Gql\Datum\IntegerDatum;
use App\Gql\Datum\NullDatum;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(EdgeDatum::class)]
#[UsesClass(DatumKind::class)]
#[UsesClass(IntegerDatum::class)]
#[UsesClass(NullDatum::class)]
#[Small]
final class EdgeDatumTest extends TestCase
{
    public function testARelationKnowsWhichSymbolsItJoinsAndWhichWayRound(): void
    {
        $edge = new EdgeDatum('e', ['methodCall'], [], 'App\A::run', 'App\B::go');

        self::assertSame('App\A::run', $edge->origin);
        self::assertSame('App\B::go', $edge->target);
        self::assertSame(['methodCall'], $edge->labels);
    }

    public function testKindNamesARelationOfTheGraph(): void
    {
        self::assertSame(DatumKind::Edge, (new EdgeDatum('e', [], [], 'a', 'b'))->kind());
    }

    public function testPropertyReadsAPropertyTheRelationCarries(): void
    {
        $edge = new EdgeDatum('e', [], ['line' => new IntegerDatum(12)], 'a', 'b');

        self::assertSame('12', $edge->property('line')->toText());
    }

    public function testPropertyReadsOneItDoesNotCarryAsAbsent(): void
    {
        self::assertSame(DatumKind::Null, (new EdgeDatum('e', [], [], 'a', 'b'))->property('line')->kind());
    }

    public function testLabelIsTheMostParticularOfTheLabelsItCarries(): void
    {
        self::assertSame('methodCall', (new EdgeDatum('e', ['methodCall', 'call', 'usage'], [], 'a', 'b'))->label());
    }

    public function testLabelOfARelationThatCarriesNoneIsNothing(): void
    {
        self::assertSame('', (new EdgeDatum('e', [], [], 'a', 'b'))->label());
    }

    public function testToTextWritesTheRelationTheWayAPatternWritesOne(): void
    {
        $edge = new EdgeDatum('e', ['methodCall'], [], 'App\A::run', 'App\B::go');

        self::assertSame('App\A::run -[methodCall]-> App\B::go', $edge->toText());
    }
}
