<?php

declare(strict_types=1);

namespace Tests\Unit\Gql\Execution;

use App\Gql\Datum\DatumIdentity;
use App\Gql\Datum\DatumKind;
use App\Gql\Datum\DatumOrder;
use App\Gql\Datum\IntegerDatum;
use App\Gql\Datum\StringDatum;
use App\Gql\Execution\SetOperation;
use App\Gql\GqlException;
use App\Gql\Result\ResultColumn;
use App\Gql\Result\ResultRow;
use App\Gql\Result\ResultTable;
use App\Gql\StatusCode;
use App\Gql\Syntax\SetOperator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(SetOperation::class)]
#[UsesClass(DatumIdentity::class)]
#[UsesClass(DatumKind::class)]
#[UsesClass(DatumOrder::class)]
#[UsesClass(IntegerDatum::class)]
#[UsesClass(GqlException::class)]
#[UsesClass(ResultColumn::class)]
#[UsesClass(ResultRow::class)]
#[UsesClass(ResultTable::class)]
#[UsesClass(StatusCode::class)]
#[UsesClass(StringDatum::class)]
#[Small]
final class SetOperationTest extends TestCase
{
    /**
     * @throws GqlException
     */
    #[DataProvider('providerOperatorsAndWhatTheyCombine')]
    public function testCombineCombinesWhatTwoRunsAnswered(SetOperator $operator, ResultTable $left, ResultTable $right, ResultTable $expected): void
    {
        self::assertEquals($expected, SetOperation::combine($operator, $left, $right));
    }

    /**
     * @return iterable<string, array{SetOperator, ResultTable, ResultTable, ResultTable}>
     */
    public static function providerOperatorsAndWhatTheyCombine(): iterable
    {
        yield 'keeping every row of both' => [
            SetOperator::UnionAll,
            new ResultTable([new ResultColumn('n', 'INT64')], [new ResultRow([new IntegerDatum(1)])]),
            new ResultTable([new ResultColumn('n', 'INT64')], [new ResultRow([new IntegerDatum(1)])]),
            new ResultTable([new ResultColumn('n', 'INT64')], [new ResultRow([new IntegerDatum(1)]), new ResultRow([new IntegerDatum(1)])]),
        ];

        yield 'dropping what repeats' => [
            SetOperator::Union,
            new ResultTable([new ResultColumn('n', 'INT64')], [new ResultRow([new IntegerDatum(1)]), new ResultRow([new IntegerDatum(2)])]),
            new ResultTable([new ResultColumn('n', 'INT64')], [new ResultRow([new IntegerDatum(2)]), new ResultRow([new IntegerDatum(3)])]),
            new ResultTable(
                [new ResultColumn('n', 'INT64')],
                [new ResultRow([new IntegerDatum(1)]), new ResultRow([new IntegerDatum(2)]), new ResultRow([new IntegerDatum(3)])],
            ),
        ];

        yield 'keeping what both sides have' => [
            SetOperator::Intersect,
            new ResultTable([new ResultColumn('n', 'INT64')], [new ResultRow([new IntegerDatum(1)]), new ResultRow([new IntegerDatum(2)])]),
            new ResultTable([new ResultColumn('n', 'INT64')], [new ResultRow([new IntegerDatum(2)]), new ResultRow([new IntegerDatum(3)])]),
            new ResultTable([new ResultColumn('n', 'INT64')], [new ResultRow([new IntegerDatum(2)])]),
        ];

        yield 'keeping what only the left has' => [
            SetOperator::Except,
            new ResultTable([new ResultColumn('n', 'INT64')], [new ResultRow([new IntegerDatum(1)]), new ResultRow([new IntegerDatum(2)])]),
            new ResultTable([new ResultColumn('n', 'INT64')], [new ResultRow([new IntegerDatum(2)]), new ResultRow([new IntegerDatum(3)])]),
            new ResultTable([new ResultColumn('n', 'INT64')], [new ResultRow([new IntegerDatum(1)])]),
        ];

        yield 'keeping what only the left has, once' => [
            SetOperator::Except,
            new ResultTable([new ResultColumn('n', 'INT64')], [new ResultRow([new IntegerDatum(1)]), new ResultRow([new IntegerDatum(1)])]),
            new ResultTable([new ResultColumn('n', 'INT64')], []),
            new ResultTable([new ResultColumn('n', 'INT64')], [new ResultRow([new IntegerDatum(1)])]),
        ];

        yield 'falling back only when the left found nothing' => [
            SetOperator::Otherwise,
            new ResultTable([new ResultColumn('name', 'NULL')], []),
            new ResultTable([new ResultColumn('name', 'INT64')], [new ResultRow([new IntegerDatum(1)])]),
            new ResultTable([new ResultColumn('name', 'INT64')], [new ResultRow([new IntegerDatum(1)])]),
        ];

        yield 'not falling back when the left found something' => [
            SetOperator::Otherwise,
            new ResultTable([new ResultColumn('n', 'INT64')], [new ResultRow([new IntegerDatum(1)])]),
            new ResultTable([new ResultColumn('n', 'INT64')], [new ResultRow([new IntegerDatum(2)])]),
            new ResultTable([new ResultColumn('n', 'INT64')], [new ResultRow([new IntegerDatum(1)])]),
        ];

        yield 'headed as the left side is, whatever the right side calls its columns' => [
            SetOperator::UnionAll,
            new ResultTable([new ResultColumn('reaching', 'STRING')], [new ResultRow([new StringDatum('show')])]),
            new ResultTable([new ResultColumn('extending', 'STRING')], [new ResultRow([new StringDatum('Controller')])]),
            new ResultTable(
                [new ResultColumn('reaching', 'STRING')],
                [new ResultRow([new StringDatum('show')]), new ResultRow([new StringDatum('Controller')])],
            ),
        ];
    }

    /**
     * @throws GqlException
     */
    public function testCombineRefusesTwoResultsThatCannotBeLinedUp(): void
    {
        $this->expectException(GqlException::class);
        $this->expectExceptionMessage('two results can only be combined when they have the same columns, and these have 1 and 2');

        SetOperation::combine(
            SetOperator::Union,
            new ResultTable([new ResultColumn('n', 'INT64')], []),
            new ResultTable([new ResultColumn('n', 'INT64'), new ResultColumn('m', 'INT64')], []),
        );
    }

    /**
     * @throws GqlException
     */
    public function testRequireSameShapeAcceptsTwoResultsOfTheSameShape(): void
    {
        $this->expectNotToPerformAssertions();

        SetOperation::requireSameShape(
            new ResultTable([new ResultColumn('n', 'INT64')], []),
            new ResultTable([new ResultColumn('m', 'STRING')], []),
        );
    }

    /**
     * @throws GqlException
     */
    public function testRequireSameShapeReportsTwoResultsThatCannotBeLinedUp(): void
    {
        $this->expectException(GqlException::class);
        $this->expectExceptionMessage('[42001] error: syntax error or access rule violation - invalid syntax: two results can only be combined when they have the same columns, and these have 1 and 0');

        SetOperation::requireSameShape(new ResultTable([new ResultColumn('n', 'INT64')], []), new ResultTable([], []));
    }

    public function testOnceShowsRowsHoldingTheSameValuesOnlyOnce(): void
    {
        self::assertEquals(
            [new ResultRow([new IntegerDatum(1)]), new ResultRow([new IntegerDatum(2)])],
            SetOperation::once([new ResultRow([new IntegerDatum(1)]), new ResultRow([new IntegerDatum(2)]), new ResultRow([new IntegerDatum(1)])]),
        );
    }

    public function testSharingKeepsTheRowsBothSidesHave(): void
    {
        self::assertEquals(
            [new ResultRow([new IntegerDatum(2)])],
            SetOperation::sharing(
                [new ResultRow([new IntegerDatum(1)]), new ResultRow([new IntegerDatum(2)])],
                [new ResultRow([new IntegerDatum(2)])],
                true,
            ),
        );
    }

    public function testSharingKeepsTheRowsOnlyTheLeftSideHas(): void
    {
        self::assertEquals(
            [new ResultRow([new IntegerDatum(1)])],
            SetOperation::sharing(
                [new ResultRow([new IntegerDatum(1)]), new ResultRow([new IntegerDatum(2)])],
                [new ResultRow([new IntegerDatum(2)])],
                false,
            ),
        );
    }

    public function testRetypedTypesAColumnByEveryValueItHoldsOnceCombined(): void
    {
        $rows = [new ResultRow([new IntegerDatum(1)]), new ResultRow([new StringDatum('a')])];

        self::assertEquals([new ResultColumn('n', 'ANY')], SetOperation::retyped([new ResultColumn('n', 'INT64')], $rows));
    }

    /**
     * @throws GqlException
     */
    public function testCombineTypesAUnionByWhatBothSidesHold(): void
    {
        $left = new ResultTable([new ResultColumn('n', 'INT64')], [new ResultRow([new IntegerDatum(1)])]);
        $right = new ResultTable([new ResultColumn('n', 'STRING')], [new ResultRow([new StringDatum('a')])]);

        self::assertEquals(
            new ResultTable([new ResultColumn('n', 'ANY')], [new ResultRow([new IntegerDatum(1)]), new ResultRow([new StringDatum('a')])]),
            SetOperation::combine(SetOperator::UnionAll, $left, $right),
        );
    }
}
