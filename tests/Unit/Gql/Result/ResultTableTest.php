<?php

declare(strict_types=1);

namespace Tests\Unit\Gql\Result;

use App\Gql\Binding\BindingRow;
use App\Gql\Binding\BindingTable;
use App\Gql\Datum\DatumKind;
use App\Gql\Datum\IntegerDatum;
use App\Gql\Datum\NullDatum;
use App\Gql\Datum\StringDatum;
use App\Gql\Result\ResultColumn;
use App\Gql\Result\ResultRow;
use App\Gql\Result\ResultTable;
use App\Gql\StatusCode;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
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
#[UsesClass(IntegerDatum::class)]
#[UsesClass(NullDatum::class)]
#[UsesClass(StringDatum::class)]
#[UsesClass(ResultColumn::class)]
#[UsesClass(ResultRow::class)]
#[UsesClass(StatusCode::class)]
#[Small]
final class ResultTableTest extends TestCase
{
    #[DataProvider('providerOneRow')]
    public function testOfTakesItsColumnsFromWhatTheQueryAskedFor(ResultTable $result): void
    {
        self::assertSame(['n'], $result->headings());
    }

    #[DataProvider('providerOneRow')]
    public function testOfReadsTheTypeOfEachColumnFromWhatItHolds(ResultTable $result): void
    {
        self::assertSame('INT64', $result->columns[0]->type);
    }

    public function testNothingIsAResultWithNoColumnsAndNoRows(): void
    {
        self::assertSame([], ResultTable::nothing()->rows);
        self::assertSame([], ResultTable::nothing()->columns);
    }

    #[DataProvider('providerOneRow')]
    public function testStatusSaysThatAQueryFoundSomething(ResultTable $result): void
    {
        self::assertSame(StatusCode::Success, $result->status());
    }

    public function testStatusSaysThatAQueryFoundNothingRatherThanThatItFailed(): void
    {
        self::assertSame(StatusCode::NoData, ResultTable::nothing()->status());
    }

    #[DataProvider('providerOneRow')]
    public function testHeadingsAreWhatTheQueryAskedItsColumnsToBeCalled(ResultTable $result): void
    {
        self::assertSame(['n'], $result->headings());
    }

    public function testTypeOfNamesTheTypeOfAColumnOfOneKind(): void
    {
        self::assertSame('INT64', ResultTable::typeOf([new ResultRow([new IntegerDatum(1)])], 0));
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
        $rows = [new ResultRow([new NullDatum()]), new ResultRow([new IntegerDatum(1)])];

        self::assertSame('INT64', ResultTable::typeOf($rows, 0));
    }

    #[DataProvider('providerOneRow')]
    public function testColumnIsEveryValueOfOneColumnInRowOrder(ResultTable $result): void
    {
        self::assertSame(['1'], array_map(static fn ($value): string => $value->toText(), $result->column(0)));
    }

    /**
     * @return iterable<string, array{ResultTable}>
     */
    public static function providerOneRow(): iterable
    {
        $row = BindingRow::unit()->with('n', new IntegerDatum(1));

        yield 'a result of one whole number' => [ResultTable::of(['n'], new BindingTable([$row]))];
    }

    public function testColumnOfAResultWithNoRowsHoldsNoValues(): void
    {
        self::assertSame([], ResultTable::nothing()->column(0));
    }
}
