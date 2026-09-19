<?php

declare(strict_types=1);

namespace Tests\Unit\Gql\Datum;

use App\Gql\Datum\BooleanDatum;
use App\Gql\Datum\DateTimeDatum;
use App\Gql\Datum\Datum;
use App\Gql\Datum\DatumKind;
use App\Gql\Datum\DatumOrder;
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
#[CoversClass(DatumOrder::class)]
#[UsesClass(BooleanDatum::class)]
#[UsesClass(DateTimeDatum::class)]
#[UsesClass(DatumKind::class)]
#[UsesClass(EdgeDatum::class)]
#[UsesClass(FloatDatum::class)]
#[UsesClass(IntegerDatum::class)]
#[UsesClass(ListDatum::class)]
#[UsesClass(NodeDatum::class)]
#[UsesClass(NullDatum::class)]
#[UsesClass(PathDatum::class)]
#[UsesClass(StringDatum::class)]
#[Small]
final class DatumOrderTest extends TestCase
{
    #[DataProvider('providerValuesAndWhetherTheyAreEqual')]
    public function testEqualsReportsWhetherTwoValuesAreEqualOrThatItCannotBeDecided(Datum $left, Datum $right, ?bool $equal): void
    {
        self::assertSame($equal, DatumOrder::equals($left, $right));
    }

    /**
     * @return iterable<string, array{Datum, Datum, null|bool}>
     */
    public static function providerValuesAndWhetherTheyAreEqual(): iterable
    {
        yield 'two numbers of different kinds' => [new IntegerDatum(2), new FloatDatum(2.0), true];

        yield 'two numbers that differ' => [new IntegerDatum(2), new IntegerDatum(3), false];

        yield 'nothing against nothing' => [new NullDatum(), new NullDatum(), null];

        yield 'something against nothing' => [new IntegerDatum(2), new NullDatum(), null];

        yield 'a number and the string spelling it' => [new IntegerDatum(5), new StringDatum('5'), false];

        yield 'two equal strings' => [new StringDatum('a'), new StringDatum('a'), true];

        yield 'two truth values' => [new BooleanDatum(true), new BooleanDatum(true), true];

        yield 'two moments naming the same instant' => [
            new DateTimeDatum(new DateTimeImmutable('2024-01-15T10:30:00+00:00')),
            new DateTimeDatum(new DateTimeImmutable('2024-01-15T19:30:00+09:00')),
            true,
        ];

        yield 'two references to the same symbol' => [new NodeDatum('a', ['Class']), new NodeDatum('a'), true];

        yield 'two references to different symbols' => [new NodeDatum('a'), new NodeDatum('b'), false];

        yield 'two references to the same relation' => [
            new EdgeDatum('e', [], [], 'a', 'b'),
            new EdgeDatum('e', ['calls'], [], 'a', 'b'),
            true,
        ];

        yield 'two equal paths' => [
            PathDatum::at(new NodeDatum('a')),
            PathDatum::at(new NodeDatum('a')),
            true,
        ];

        yield 'two equal lists' => [
            new ListDatum([new IntegerDatum(1)]),
            new ListDatum([new IntegerDatum(1)]),
            true,
        ];
    }

    public function testAllEqualReportsListsOfDifferentLengthsAsUnequal(): void
    {
        self::assertFalse(DatumOrder::allEqual([new IntegerDatum(1)], []));
    }

    public function testAllEqualPrefersADifferenceFoundOverAPairItCouldNotDecide(): void
    {
        $left = [new NullDatum(), new IntegerDatum(1)];
        $right = [new NullDatum(), new IntegerDatum(2)];

        self::assertFalse(DatumOrder::allEqual($left, $right));
    }

    public function testAllEqualCannotDecideWhenOnlyOnePairIsUndecided(): void
    {
        $left = [new NullDatum(), new IntegerDatum(1)];
        $right = [new NullDatum(), new IntegerDatum(1)];

        self::assertNull(DatumOrder::allEqual($left, $right));
    }

    public function testAllEqualReportsTwoListsOfNothingAsEqual(): void
    {
        self::assertTrue(DatumOrder::allEqual([], []));
    }

    #[DataProvider('providerValuesAndHowTheyOrder')]
    public function testCompareReportsHowTwoValuesOrderOrThatItCannotBeDecided(Datum $left, Datum $right, ?int $order): void
    {
        self::assertSame($order, DatumOrder::compare($left, $right));
    }

    /**
     * @return iterable<string, array{Datum, Datum, null|int}>
     */
    public static function providerValuesAndHowTheyOrder(): iterable
    {
        yield 'two numbers' => [new IntegerDatum(2), new FloatDatum(2.5), -1];

        yield 'two equal numbers' => [new IntegerDatum(2), new IntegerDatum(2), 0];

        yield 'two strings' => [new StringDatum('a'), new StringDatum('b'), -1];

        yield 'two truth values' => [new BooleanDatum(false), new BooleanDatum(true), -1];

        yield 'two moments' => [
            new DateTimeDatum(new DateTimeImmutable('2024-01-15T10:30:00+00:00')),
            new DateTimeDatum(new DateTimeImmutable('2024-01-16T10:30:00+00:00')),
            -1,
        ];

        yield 'two lists' => [
            new ListDatum([new IntegerDatum(1), new IntegerDatum(2)]),
            new ListDatum([new IntegerDatum(1), new IntegerDatum(3)]),
            -1,
        ];

        yield 'something against nothing' => [new IntegerDatum(2), new NullDatum(), null];

        yield 'values of unrelated kinds' => [new IntegerDatum(2), new StringDatum('2'), null];

        yield 'two symbols, which have no order of their own' => [new NodeDatum('a'), new NodeDatum('b'), null];
    }

    public function testCompareListsOrdersByTheirFirstDifference(): void
    {
        $left = [new IntegerDatum(1), new IntegerDatum(2)];
        $right = [new IntegerDatum(1), new IntegerDatum(3)];

        self::assertSame(-1, DatumOrder::compareLists($left, $right));
    }

    public function testCompareListsMakesTheListThatRunsOutFirstTheSmaller(): void
    {
        self::assertSame(-1, DatumOrder::compareLists([], [new IntegerDatum(1)]));
    }

    public function testCompareListsCannotDecideWhenAPairCannotBeCompared(): void
    {
        self::assertNull(DatumOrder::compareLists([new IntegerDatum(1)], [new StringDatum('1')]));
    }

    public function testSortPutsTheAbsenceOfAValueBeforeEveryValue(): void
    {
        self::assertSame(-1, DatumOrder::sort(new NullDatum(), new IntegerDatum(-99)));
    }

    public function testSortDecidesTwoAbsencesAsEqual(): void
    {
        self::assertSame(0, DatumOrder::sort(new NullDatum(), new NullDatum()));
    }

    public function testSortOrdersValuesWithNoOrderOfTheirOwnByHowTheyAreWritten(): void
    {
        self::assertSame(-1, DatumOrder::sort(new NodeDatum('App\A'), new NodeDatum('App\B')));
    }

    public function testSortOrdersValuesOfDifferentKindsByTheirKinds(): void
    {
        self::assertLessThan(0, DatumOrder::sort(new IntegerDatum(99), new StringDatum('a')));
    }

    public function testNumberOfReadsAWholeNumberAsAnInteger(): void
    {
        self::assertSame(2, DatumOrder::numberOf(new IntegerDatum(2)));
    }

    public function testNumberOfReadsAnApproximateNumberAsAFloat(): void
    {
        self::assertSame(1.5, DatumOrder::numberOf(new FloatDatum(1.5)));
    }

    public function testNumberOfReadsAnythingElseAsZeroWhichNoComparisonReaches(): void
    {
        self::assertSame(0, DatumOrder::numberOf(new StringDatum('2')));
    }
}
