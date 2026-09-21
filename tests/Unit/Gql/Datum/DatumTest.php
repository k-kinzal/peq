<?php

declare(strict_types=1);

namespace Tests\Unit\Gql\Datum;

use App\Gql\Datum\BooleanDatum;
use App\Gql\Datum\DateTimeDatum;
use App\Gql\Datum\Datum;
use App\Gql\Datum\DatumKind;
use App\Gql\Datum\DecimalDatum;
use App\Gql\Datum\EdgeDatum;
use App\Gql\Datum\FloatDatum;
use App\Gql\Datum\IntegerDatum;
use App\Gql\Datum\ListDatum;
use App\Gql\Datum\NodeDatum;
use App\Gql\Datum\NullDatum;
use App\Gql\Datum\PathDatum;
use App\Gql\Datum\StringDatum;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(BooleanDatum::class)]
#[CoversClass(DateTimeDatum::class)]
#[CoversClass(DecimalDatum::class)]
#[CoversClass(EdgeDatum::class)]
#[CoversClass(FloatDatum::class)]
#[CoversClass(IntegerDatum::class)]
#[CoversClass(ListDatum::class)]
#[CoversClass(NodeDatum::class)]
#[CoversClass(NullDatum::class)]
#[CoversClass(PathDatum::class)]
#[CoversClass(StringDatum::class)]
#[UsesClass(DatumKind::class)]
#[Small]
final class DatumTest extends TestCase
{
    #[DataProvider('providerOneValueOfEveryKind')]
    public function testKindNamesWhatTheValueIs(Datum $value, DatumKind $kind, string $written): void
    {
        self::assertSame($kind, $value->kind());
    }

    #[DataProvider('providerOneValueOfEveryKind')]
    public function testToTextWritesEveryValueOutTheWayAResultShowsIt(Datum $value, DatumKind $kind, string $written): void
    {
        self::assertSame($written, $value->toText());
    }

    /**
     * @return iterable<string, array{Datum, DatumKind, string}>
     */
    public static function providerOneValueOfEveryKind(): iterable
    {
        yield 'the absence of a value' => [new NullDatum(), DatumKind::Null, 'NULL'];

        yield 'a truth value' => [new BooleanDatum(true), DatumKind::Boolean, 'TRUE'];

        yield 'a whole number' => [new IntegerDatum(1), DatumKind::Integer, '1'];

        yield 'an exact number with digits after the point' => [new DecimalDatum(15, 1), DatumKind::Decimal, '1.5'];

        yield 'an approximate number' => [new FloatDatum(1.5), DatumKind::Float, '1.5'];

        yield 'a character string' => [new StringDatum('a'), DatumKind::Text, 'a'];

        yield 'a list' => [new ListDatum([new IntegerDatum(1)]), DatumKind::ListOf, '[1]'];

        yield 'a symbol' => [new NodeDatum('a'), DatumKind::Node, 'a'];

        yield 'a relation' => [new EdgeDatum('e', ['calls'], [], 'a', 'b'), DatumKind::Edge, 'a -[calls]-> b'];

        yield 'a path' => [new PathDatum([new NodeDatum('a')]), DatumKind::Path, 'a'];

        yield 'a moment' => [
            new DateTimeDatum(new DateTimeImmutable('2024-01-15T10:30:00+00:00')),
            DatumKind::DateTime,
            '2024-01-15T10:30:00+00:00',
        ];
    }

    public function testEveryKindOfValueIsAccountedFor(): void
    {
        self::assertSame(DatumKind::cases(), array_column([...self::providerOneValueOfEveryKind()], 1));
    }
}
