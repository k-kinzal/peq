<?php

declare(strict_types=1);

namespace Tests\Unit\Gql\Datum;

use App\Gql\Datum\BooleanDatum;
use App\Gql\Datum\DateTimeDatum;
use App\Gql\Datum\Datum;
use App\Gql\Datum\DatumKind;
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
    public function testKindNamesWhatTheValueIs(Datum $value, DatumKind $kind): void
    {
        self::assertSame($kind, $value->kind());
    }

    #[DataProvider('providerOneValueOfEveryKind')]
    public function testToTextWritesEveryValueOutAsSomething(Datum $value, DatumKind $kind): void
    {
        self::assertNotSame('', $value->toText());
        self::assertSame($kind, $value->kind());
    }

    /**
     * @return iterable<string, array{Datum, DatumKind}>
     */
    public static function providerOneValueOfEveryKind(): iterable
    {
        yield 'the absence of a value' => [new NullDatum(), DatumKind::Null];

        yield 'a truth value' => [new BooleanDatum(true), DatumKind::Boolean];

        yield 'a whole number' => [new IntegerDatum(1), DatumKind::Integer];

        yield 'an approximate number' => [new FloatDatum(1.5), DatumKind::Float];

        yield 'a character string' => [new StringDatum('a'), DatumKind::Text];

        yield 'a list' => [new ListDatum([new IntegerDatum(1)]), DatumKind::ListOf];

        yield 'a symbol' => [new NodeDatum('a'), DatumKind::Node];

        yield 'a relation' => [new EdgeDatum('e', ['calls'], [], 'a', 'b'), DatumKind::Edge];

        yield 'a path' => [PathDatum::at(new NodeDatum('a')), DatumKind::Path];

        yield 'a moment' => [new DateTimeDatum(new DateTimeImmutable('2024-01-15T10:30:00+00:00')), DatumKind::DateTime];
    }

    public function testEveryKindOfValueIsAccountedFor(): void
    {
        $kinds = array_map(
            static fn (array $case): DatumKind => $case[1],
            [...self::providerOneValueOfEveryKind()],
        );

        self::assertSame(DatumKind::cases(), array_values($kinds));
    }
}
