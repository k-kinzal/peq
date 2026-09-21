<?php

declare(strict_types=1);

namespace Tests\Unit\Gql\Result;

use App\Gql\Binding\BindingRow;
use App\Gql\Binding\BindingTable;
use App\Gql\Datum\DatumKind;
use App\Gql\Datum\DecimalDatum;
use App\Gql\Datum\FloatDatum;
use App\Gql\Datum\IntegerDatum;
use App\Gql\Datum\NullDatum;
use App\Gql\Datum\StringDatum;
use App\Gql\Result\ResultColumn;
use App\Gql\Result\ResultRow;
use App\Gql\Result\ResultTable;
use App\Gql\StatusCode;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(ResultTable::class)]
#[UsesClass(BindingRow::class)]
#[UsesClass(BindingTable::class)]
#[UsesClass(DatumKind::class)]
#[UsesClass(DecimalDatum::class)]
#[UsesClass(IntegerDatum::class)]
#[UsesClass(NullDatum::class)]
#[UsesClass(ResultColumn::class)]
#[UsesClass(ResultRow::class)]
#[UsesClass(StatusCode::class)]
#[UsesClass(StringDatum::class)]
#[UsesClass(FloatDatum::class)]
#[Small]
final class ResultTableTest extends TestCase
{
    public function testOfTakesItsColumnsFromWhatTheQueryAskedForAndTheirTypesFromWhatTheyHold(): void
    {
        $table = new BindingTable([BindingRow::unit()->with('n', new IntegerDatum(1))->with('s', new StringDatum('a'))]);

        self::assertEquals(
            [new ResultColumn('s', 'STRING'), new ResultColumn('n', 'INT64')],
            ResultTable::of(['s', 'n'], $table)->columns,
        );
    }

    public function testOfReadsOneRowPerRowBoundInTheOrderItsColumnsAreShown(): void
    {
        $table = new BindingTable([
            BindingRow::unit()->with('n', new IntegerDatum(1))->with('s', new StringDatum('a')),
            BindingRow::unit()->with('n', new IntegerDatum(2))->with('s', new StringDatum('b')),
        ]);

        self::assertEquals(
            [
                new ResultRow([new StringDatum('a'), new IntegerDatum(1)]),
                new ResultRow([new StringDatum('b'), new IntegerDatum(2)]),
            ],
            ResultTable::of(['s', 'n'], $table)->rows,
        );
    }

    public function testOfReadsAColumnARowDoesNotBindAsAbsent(): void
    {
        $table = new BindingTable([BindingRow::unit()]);

        self::assertEquals([new ResultRow([new NullDatum()])], ResultTable::of(['n'], $table)->rows);
    }

    public function testNothingIsAResultWithNoColumnsAndNoRows(): void
    {
        $result = ResultTable::nothing();

        self::assertSame([], $result->columns);
        self::assertSame([], $result->rows);
    }

    public function testStatusSaysThatAQueryFoundSomething(): void
    {
        $result = new ResultTable([new ResultColumn('n', 'INT64')], [new ResultRow([new IntegerDatum(1)])]);

        self::assertSame(StatusCode::Success, $result->status());
    }

    public function testStatusSaysThatAQueryFoundNothingRatherThanThatItFailed(): void
    {
        $result = new ResultTable([new ResultColumn('n', 'NULL')], []);

        self::assertSame(StatusCode::NoData, $result->status());
    }

    public function testHeadingsAreWhatTheQueryAskedItsColumnsToBeCalled(): void
    {
        $result = new ResultTable([new ResultColumn('p', 'NODE'), new ResultColumn('n', 'INT64')], []);

        self::assertSame(['p', 'n'], $result->headings());
    }

    public function testTypeOfNamesTheTypeOfAColumnOfOneKind(): void
    {
        self::assertSame('INT64', ResultTable::typeOf([new ResultRow([new IntegerDatum(1)])], 0));
    }

    public function testTypeOfNamesAColumnOfDecimals(): void
    {
        self::assertSame('DECIMAL', ResultTable::typeOf([new ResultRow([new DecimalDatum(15, 1)])], 0));
    }

    public function testTypeOfNamesAColumnOfMoreThanOneKindAsHoldingAnyOfThem(): void
    {
        $rows = [new ResultRow([new IntegerDatum(1)]), new ResultRow([new StringDatum('a')])];

        self::assertSame('ANY', ResultTable::typeOf($rows, 0));
    }

    public function testTypeOfNamesAColumnWithNothingInItAsHoldingNothing(): void
    {
        self::assertSame('NULL', ResultTable::typeOf([], 0));
    }

    public function testTypeOfPassesOverTheRowsWhoseValueIsAbsent(): void
    {
        $rows = [new ResultRow([new NullDatum()]), new ResultRow([new IntegerDatum(1)]), new ResultRow([new NullDatum()])];

        self::assertSame('INT64', ResultTable::typeOf($rows, 0));
    }

    public function testTypeOfReadsTheColumnItIsAsked(): void
    {
        $rows = [new ResultRow([new IntegerDatum(1), new StringDatum('a')])];

        self::assertSame('STRING', ResultTable::typeOf($rows, 1));
    }

    public function testColumnIsEveryValueOfOneColumnInRowOrder(): void
    {
        $result = new ResultTable(
            [new ResultColumn('n', 'INT64'), new ResultColumn('s', 'STRING')],
            [
                new ResultRow([new IntegerDatum(1), new StringDatum('a')]),
                new ResultRow([new IntegerDatum(2), new StringDatum('b')]),
            ],
        );

        self::assertEquals([new StringDatum('a'), new StringDatum('b')], $result->column(1));
    }

    public function testColumnOfAResultWithNoRowsHoldsNoValues(): void
    {
        self::assertSame([], ResultTable::nothing()->column(0));
    }

    public function testTypeOfFindsAColumnOfWholeNumbersAndDecimalsHoldsDecimals(): void
    {
        $rows = [new ResultRow([new IntegerDatum(1)]), new ResultRow([new DecimalDatum(15, 1)])];

        self::assertSame('DECIMAL', ResultTable::typeOf($rows, 0));
    }

    public function testTypeOfFindsAColumnWithAnApproximateNumberInItHoldsApproximateNumbers(): void
    {
        $rows = [new ResultRow([new DecimalDatum(15, 1)]), new ResultRow([new FloatDatum(2.5)])];

        self::assertSame('FLOAT64', ResultTable::typeOf($rows, 0));
    }
}
