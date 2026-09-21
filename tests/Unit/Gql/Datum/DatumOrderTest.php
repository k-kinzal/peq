<?php

declare(strict_types=1);

namespace Tests\Unit\Gql\Datum;

use App\Gql\Datum\BooleanDatum;
use App\Gql\Datum\DateTimeDatum;
use App\Gql\Datum\Datum;
use App\Gql\Datum\DatumKind;
use App\Gql\Datum\DatumOrder;
use App\Gql\Datum\DecimalDatum;
use App\Gql\Datum\EdgeDatum;
use App\Gql\Datum\FloatDatum;
use App\Gql\Datum\IntegerDatum;
use App\Gql\Datum\ListDatum;
use App\Gql\Datum\NodeDatum;
use App\Gql\Datum\NullDatum;
use App\Gql\Datum\PathDatum;
use App\Gql\Datum\StringDatum;
use App\Gql\GqlException;
use App\Gql\StatusCode;
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
#[UsesClass(DecimalDatum::class)]
#[UsesClass(EdgeDatum::class)]
#[UsesClass(FloatDatum::class)]
#[UsesClass(GqlException::class)]
#[UsesClass(IntegerDatum::class)]
#[UsesClass(ListDatum::class)]
#[UsesClass(NodeDatum::class)]
#[UsesClass(NullDatum::class)]
#[UsesClass(PathDatum::class)]
#[UsesClass(StatusCode::class)]
#[UsesClass(StringDatum::class)]
#[Small]
final class DatumOrderTest extends TestCase
{
    /**
     * @throws GqlException
     */
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
        yield 'a whole number and an approximate one of the same value' => [new IntegerDatum(2), new FloatDatum(2.0), true];

        yield 'a whole number and a decimal of the same value' => [new IntegerDatum(2), new DecimalDatum(20, 1), true];

        yield 'two decimals written to different scales' => [new DecimalDatum(15, 1), new DecimalDatum(150, 2), true];

        yield 'two numbers that differ' => [new IntegerDatum(2), new IntegerDatum(3), false];

        yield 'nothing against nothing' => [new NullDatum(), new NullDatum(), null];

        yield 'something against nothing' => [new IntegerDatum(2), new NullDatum(), null];

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

        yield 'two equal paths' => [new PathDatum([new NodeDatum('a')]), new PathDatum([new NodeDatum('a')]), true];

        yield 'two equal lists' => [new ListDatum([new IntegerDatum(1)]), new ListDatum([new IntegerDatum(1)]), true];
    }

    /**
     * @throws GqlException
     */
    #[DataProvider('providerValuesOfKindsWithNoComparisonBetweenThem')]
    public function testEqualsReportsTwoValuesOfUnrelatedKindsAsNotComparable(Datum $left, Datum $right): void
    {
        $this->expectException(GqlException::class);
        $this->expectExceptionMessage('[22G04] error: data exception - values not comparable');

        DatumOrder::equals($left, $right);
    }

    /**
     * @return iterable<string, array{Datum, Datum}>
     */
    public static function providerValuesOfKindsWithNoComparisonBetweenThem(): iterable
    {
        yield 'a number and the string spelling it' => [new IntegerDatum(5), new StringDatum('5')];

        yield 'a truth value and a number' => [new BooleanDatum(true), new IntegerDatum(1)];

        yield 'a symbol and a relation' => [new NodeDatum('e'), new EdgeDatum('e', [], [], 'a', 'b')];

        yield 'a list and the value it holds' => [new ListDatum([new IntegerDatum(1)]), new IntegerDatum(1)];
    }

    /**
     * @throws GqlException
     */
    public function testAllEqualReportsListsOfDifferentLengthsAsUnequal(): void
    {
        self::assertFalse(DatumOrder::allEqual([new IntegerDatum(1)], []));
    }

    /**
     * @throws GqlException
     */
    public function testAllEqualPrefersADifferenceFoundOverAPairItCouldNotDecide(): void
    {
        self::assertFalse(DatumOrder::allEqual(
            [new NullDatum(), new IntegerDatum(1)],
            [new NullDatum(), new IntegerDatum(2)],
        ));
    }

    /**
     * @throws GqlException
     */
    public function testAllEqualCannotDecideWhenOnlyOnePairIsUndecided(): void
    {
        self::assertNull(DatumOrder::allEqual(
            [new NullDatum(), new IntegerDatum(1)],
            [new NullDatum(), new IntegerDatum(1)],
        ));
    }

    /**
     * @throws GqlException
     */
    public function testAllEqualReportsTwoListsOfNothingAsEqual(): void
    {
        self::assertTrue(DatumOrder::allEqual([], []));
    }

    /**
     * @throws GqlException
     */
    public function testAllEqualReportsAPairOfUnrelatedKindsAsNotComparable(): void
    {
        $this->expectException(GqlException::class);
        $this->expectExceptionMessage('[22G04] error: data exception - values not comparable');

        DatumOrder::allEqual([new IntegerDatum(1)], [new StringDatum('1')]);
    }

    /**
     * @throws GqlException
     */
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
        yield 'a whole number and an approximate one' => [new IntegerDatum(2), new FloatDatum(2.5), -1];

        yield 'a decimal and a whole number' => [new DecimalDatum(15, 1), new IntegerDatum(2), -1];

        yield 'two equal whole numbers' => [new IntegerDatum(2), new IntegerDatum(2), 0];

        yield 'two strings' => [new StringDatum('b'), new StringDatum('a'), 1];

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

        yield 'nothing against something' => [new NullDatum(), new StringDatum('a'), null];
    }

    /**
     * @throws GqlException
     */
    #[DataProvider('providerValuesWithNoOrderBetweenThem')]
    public function testCompareReportsTwoValuesWithNoOrderBetweenThemAsNotComparable(Datum $left, Datum $right): void
    {
        $this->expectException(GqlException::class);
        $this->expectExceptionMessage('[22G04] error: data exception - values not comparable');

        DatumOrder::compare($left, $right);
    }

    /**
     * @return iterable<string, array{Datum, Datum}>
     */
    public static function providerValuesWithNoOrderBetweenThem(): iterable
    {
        yield 'a number and the string spelling it' => [new IntegerDatum(2), new StringDatum('2')];

        yield 'two symbols' => [new NodeDatum('a'), new NodeDatum('b')];

        yield 'two relations' => [new EdgeDatum('e', [], [], 'a', 'b'), new EdgeDatum('f', [], [], 'a', 'b')];

        yield 'two paths' => [new PathDatum([new NodeDatum('a')]), new PathDatum([new NodeDatum('b')])];
    }

    public function testCompareNumbersComparesTwoExactNumbersExactly(): void
    {
        self::assertSame(0, DatumOrder::compareNumbers(new DecimalDatum(10, 1), new IntegerDatum(1)));
    }

    public function testCompareNumbersTellsApartTwoExactNumbersNoFloatCanTellApart(): void
    {
        self::assertSame(1, DatumOrder::compareNumbers(new IntegerDatum(9007199254740993), new DecimalDatum(90071992547409920, 1)));
    }

    public function testCompareNumbersComparesAnApproximateNumberWithAnExactOneByValue(): void
    {
        self::assertSame(0, DatumOrder::compareNumbers(new FloatDatum(1.5), new DecimalDatum(15, 1)));
    }

    public function testCompareNumbersOrdersTwoApproximateNumbers(): void
    {
        self::assertSame(-1, DatumOrder::compareNumbers(new FloatDatum(1.5), new FloatDatum(2.5)));
    }

    public function testCompareExactReadsAWholeNumberAndADecimalOfTheSameValueAsTheSame(): void
    {
        self::assertSame(0, DatumOrder::compareExact(new DecimalDatum(20, 1), new IntegerDatum(2)));
    }

    public function testCompareExactOrdersTwoDecimalsByValueWhateverTheirScales(): void
    {
        self::assertSame(1, DatumOrder::compareExact(new DecimalDatum(15, 1), new DecimalDatum(149, 2)));
    }

    public function testCompareExactOrdersNegativeNumbersBelowLessNegativeOnes(): void
    {
        self::assertSame(-1, DatumOrder::compareExact(new DecimalDatum(-15, 1), new IntegerDatum(-1)));
    }

    /**
     * @throws GqlException
     */
    public function testCompareListsOrdersByTheirFirstDifference(): void
    {
        self::assertSame(-1, DatumOrder::compareLists(
            [new IntegerDatum(1), new IntegerDatum(2)],
            [new IntegerDatum(1), new IntegerDatum(3)],
        ));
    }

    /**
     * @throws GqlException
     */
    public function testCompareListsMakesTheListThatRunsOutFirstTheSmaller(): void
    {
        self::assertSame(-1, DatumOrder::compareLists([new IntegerDatum(1)], [new IntegerDatum(1), new IntegerDatum(2)]));
    }

    /**
     * @throws GqlException
     */
    public function testCompareListsReportsTwoListsOfEqualValuesAsEqual(): void
    {
        self::assertSame(0, DatumOrder::compareLists([new IntegerDatum(1)], [new DecimalDatum(10, 1)]));
    }

    /**
     * @throws GqlException
     */
    public function testCompareListsCannotDecideWhenAnElementIsAbsent(): void
    {
        self::assertNull(DatumOrder::compareLists([new NullDatum()], [new IntegerDatum(1)]));
    }

    /**
     * @throws GqlException
     */
    public function testCompareListsReportsAPairOfUnrelatedKindsAsNotComparable(): void
    {
        $this->expectException(GqlException::class);
        $this->expectExceptionMessage('[22G04] error: data exception - values not comparable');

        DatumOrder::compareLists([new IntegerDatum(1)], [new StringDatum('1')]);
    }

    /**
     * @throws GqlException
     */
    public function testSortPutsTheAbsenceOfAValueBeforeEveryValue(): void
    {
        self::assertSame(-1, DatumOrder::sort(new NullDatum(), new IntegerDatum(-99)));
    }

    /**
     * @throws GqlException
     */
    public function testSortPutsEveryValueAfterTheAbsenceOfOne(): void
    {
        self::assertSame(1, DatumOrder::sort(new StringDatum('a'), new NullDatum()));
    }

    /**
     * @throws GqlException
     */
    public function testSortDecidesTwoAbsencesAsEqual(): void
    {
        self::assertSame(0, DatumOrder::sort(new NullDatum(), new NullDatum()));
    }

    /**
     * @throws GqlException
     */
    public function testSortOrdersNumbersOfDifferentKindsByValue(): void
    {
        self::assertSame(1, DatumOrder::sort(new DecimalDatum(15, 1), new IntegerDatum(1)));
    }

    /**
     * @throws GqlException
     */
    public function testSortReportsValuesWithNoOrderOfTheirOwnAsNotComparable(): void
    {
        $this->expectException(GqlException::class);
        $this->expectExceptionMessage('[22G04] error: data exception - values not comparable: NODE and NODE cannot be compared');

        DatumOrder::sort(new NodeDatum('App\A'), new NodeDatum('App\B'));
    }

    /**
     * @throws GqlException
     */
    public function testSortReportsValuesOfDifferentKindsAsNotComparable(): void
    {
        $this->expectException(GqlException::class);
        $this->expectExceptionMessage('[22G04] error: data exception - values not comparable: INT64 and STRING cannot be compared');

        DatumOrder::sort(new IntegerDatum(99), new StringDatum('a'));
    }

    public function testNumberOfReadsAWholeNumberAsAnInteger(): void
    {
        self::assertSame(2, DatumOrder::numberOf(new IntegerDatum(2)));
    }

    public function testNumberOfReadsADecimalAsTheFloatNearestToIt(): void
    {
        self::assertSame(1.5, DatumOrder::numberOf(new DecimalDatum(15, 1)));
    }

    public function testNumberOfReadsAnApproximateNumberAsAFloat(): void
    {
        self::assertSame(1.5, DatumOrder::numberOf(new FloatDatum(1.5)));
    }

    public function testNumberOfReadsAnythingElseAsZeroWhichNoComparisonReaches(): void
    {
        self::assertSame(0, DatumOrder::numberOf(new StringDatum('2')));
    }

    public function testNotComparableNamesBothKindsUnderTheStatusGqlGivesIt(): void
    {
        $refused = DatumOrder::notComparable(new IntegerDatum(1), new StringDatum('a'));

        self::assertSame(StatusCode::ValuesNotComparable, $refused->status);
        self::assertSame('INT64 and STRING cannot be compared', $refused->reason);
    }

    public function testCompareWrittenComparesNumbersTooLargeToBringToOneScale(): void
    {
        self::assertSame(1, DatumOrder::compareWritten('922337203685477581', '922337203685477580.7'));
    }

    public function testCompareWrittenFindsTrailingZerosChangeNothing(): void
    {
        self::assertSame(0, DatumOrder::compareWritten('1.50', '1.5'));
    }

    public function testCompareWrittenFindsANegativeNumberSmallerTheLargerItsDigits(): void
    {
        self::assertSame(-1, DatumOrder::compareWritten('-2', '-1.5'));
    }

    public function testCompareWrittenFindsNegativeZeroEqualToZero(): void
    {
        self::assertSame(0, DatumOrder::compareWritten('-0.00', '0'));
    }

    public function testCompareExactComparesNumbersTooLargeToBringToOneScale(): void
    {
        self::assertSame(1, DatumOrder::compareExact(new IntegerDatum(922337203685477581), new DecimalDatum(PHP_INT_MAX, 1)));
    }
}
