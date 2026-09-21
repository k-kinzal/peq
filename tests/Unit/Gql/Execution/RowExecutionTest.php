<?php

declare(strict_types=1);

namespace Tests\Unit\Gql\Execution;

use App\Gql\Argument\ExactArithmetic;
use App\Gql\Argument\NumberArgument;
use App\Gql\Binding\BindingRow;
use App\Gql\Binding\BindingTable;
use App\Gql\Datum\BooleanDatum;
use App\Gql\Datum\DatumKind;
use App\Gql\Datum\DatumOrder;
use App\Gql\Datum\DecimalDatum;
use App\Gql\Datum\IntegerDatum;
use App\Gql\Datum\NullDatum;
use App\Gql\Datum\StringDatum;
use App\Gql\Evaluation\Arithmetic;
use App\Gql\Evaluation\BinaryOperation;
use App\Gql\Evaluation\Comparison;
use App\Gql\Evaluation\ExpressionEvaluation;
use App\Gql\Evaluation\Logic;
use App\Gql\Execution\RowExecution;
use App\Gql\GqlException;
use App\Gql\StatusCode;
use App\Gql\Syntax\Clause\FilterClause;
use App\Gql\Syntax\Clause\LetClause;
use App\Gql\Syntax\Clause\OrderByClause;
use App\Gql\Syntax\Clause\PageClause;
use App\Gql\Syntax\Clause\SortDirection;
use App\Gql\Syntax\Clause\SortKey;
use App\Gql\Syntax\Clause\VariableBinding;
use App\Gql\Syntax\Expression\BinaryExpression;
use App\Gql\Syntax\Expression\BinaryOperator;
use App\Gql\Syntax\Expression\LiteralExpression;
use App\Gql\Syntax\Expression\VariableExpression;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(RowExecution::class)]
#[UsesClass(ExactArithmetic::class)]
#[UsesClass(NumberArgument::class)]
#[UsesClass(BindingRow::class)]
#[UsesClass(BindingTable::class)]
#[UsesClass(BooleanDatum::class)]
#[UsesClass(DatumKind::class)]
#[UsesClass(DatumOrder::class)]
#[UsesClass(DecimalDatum::class)]
#[UsesClass(IntegerDatum::class)]
#[UsesClass(StringDatum::class)]
#[UsesClass(Arithmetic::class)]
#[UsesClass(BinaryOperation::class)]
#[UsesClass(Comparison::class)]
#[UsesClass(ExpressionEvaluation::class)]
#[UsesClass(Logic::class)]
#[UsesClass(GqlException::class)]
#[UsesClass(StatusCode::class)]
#[UsesClass(FilterClause::class)]
#[UsesClass(LetClause::class)]
#[UsesClass(OrderByClause::class)]
#[UsesClass(PageClause::class)]
#[UsesClass(SortDirection::class)]
#[UsesClass(SortKey::class)]
#[UsesClass(VariableBinding::class)]
#[UsesClass(BinaryExpression::class)]
#[UsesClass(LiteralExpression::class)]
#[UsesClass(VariableExpression::class)]
#[Small]
final class RowExecutionTest extends TestCase
{
    /**
     * @throws GqlException
     */
    public function testBindAddsAColumnToEveryRowItIsGiven(): void
    {
        $clause = new LetClause([
            new VariableBinding('next', new BinaryExpression(BinaryOperator::Add, new VariableExpression('n'), new LiteralExpression(new IntegerDatum(1)))),
        ]);
        $table = new BindingTable([new BindingRow(['n' => new IntegerDatum(1)]), new BindingRow(['n' => new IntegerDatum(2)])]);

        self::assertEquals(
            new BindingTable([
                new BindingRow(['n' => new IntegerDatum(1), 'next' => new IntegerDatum(2)]),
                new BindingRow(['n' => new IntegerDatum(2), 'next' => new IntegerDatum(3)]),
            ]),
            (new RowExecution(new ExpressionEvaluation()))->bind($clause, $table),
        );
    }

    /**
     * @throws GqlException
     */
    public function testBindWorksEveryNameOutFromTheRowAsItArrived(): void
    {
        $clause = new LetClause([
            new VariableBinding('a', new LiteralExpression(new IntegerDatum(2))),
            new VariableBinding('b', new VariableExpression('a')),
        ]);
        $table = new BindingTable([new BindingRow(['a' => new IntegerDatum(1)])]);

        self::assertEquals(
            new BindingTable([new BindingRow(['a' => new IntegerDatum(2), 'b' => new IntegerDatum(1)])]),
            (new RowExecution(new ExpressionEvaluation()))->bind($clause, $table),
        );
    }

    /**
     * @throws GqlException
     */
    public function testBindReportsANameNothingBinds(): void
    {
        $clause = new LetClause([new VariableBinding('b', new VariableExpression('a'))]);

        $this->expectException(GqlException::class);
        $this->expectExceptionMessage('[42002] error: syntax error or access rule violation - invalid reference: nothing binds "a" here');

        (new RowExecution(new ExpressionEvaluation()))->bind($clause, BindingTable::unit());
    }

    /**
     * @throws GqlException
     */
    public function testKeepKeepsOnlyTheRowsThePredicateIsTrueOf(): void
    {
        $clause = new FilterClause(new BinaryExpression(BinaryOperator::Greater, new VariableExpression('line'), new LiteralExpression(new IntegerDatum(25))));
        $table = new BindingTable([new BindingRow(['line' => new IntegerDatum(20)]), new BindingRow(['line' => new IntegerDatum(30)])]);

        self::assertEquals(
            new BindingTable([new BindingRow(['line' => new IntegerDatum(30)])]),
            (new RowExecution(new ExpressionEvaluation()))->keep($clause, $table),
        );
    }

    /**
     * @throws GqlException
     */
    public function testKeepDropsARowThePredicateCannotBeDecidedFor(): void
    {
        $clause = new FilterClause(new BinaryExpression(BinaryOperator::Greater, new VariableExpression('line'), new LiteralExpression(new IntegerDatum(0))));
        $table = new BindingTable([new BindingRow(['line' => new NullDatum()]), new BindingRow(['line' => new IntegerDatum(8)])]);

        self::assertEquals(
            new BindingTable([new BindingRow(['line' => new IntegerDatum(8)])]),
            (new RowExecution(new ExpressionEvaluation()))->keep($clause, $table),
        );
    }

    /**
     * @throws GqlException
     */
    public function testKeepReportsAPredicateThatIsNotATruthValue(): void
    {
        $clause = new FilterClause(new LiteralExpression(new IntegerDatum(1)));

        $this->expectException(GqlException::class);
        $this->expectExceptionMessage('[22G03] error: data exception - invalid value type: a truth value was expected, and a INT64 was given');

        (new RowExecution(new ExpressionEvaluation()))->keep($clause, BindingTable::unit());
    }

    /**
     * @throws GqlException
     */
    public function testOrderPutsTheRowsInTheOrderItIsAskedFor(): void
    {
        $clause = new OrderByClause([new SortKey(new VariableExpression('line'), SortDirection::Descending)]);
        $table = new BindingTable([
            new BindingRow(['line' => new IntegerDatum(12)]),
            new BindingRow(['line' => new IntegerDatum(30)]),
            new BindingRow(['line' => new IntegerDatum(8)]),
        ]);

        self::assertEquals(
            new BindingTable([
                new BindingRow(['line' => new IntegerDatum(30)]),
                new BindingRow(['line' => new IntegerDatum(12)]),
                new BindingRow(['line' => new IntegerDatum(8)]),
            ]),
            (new RowExecution(new ExpressionEvaluation()))->order($clause, $table),
        );
    }

    /**
     * @throws GqlException
     */
    public function testOrderProducesNothingWhenThereIsNothingToOrder(): void
    {
        $clause = new OrderByClause([new SortKey(new VariableExpression('line'))]);

        self::assertEquals(BindingTable::nothing(), (new RowExecution(new ExpressionEvaluation()))->order($clause, BindingTable::nothing()));
    }

    /**
     * @throws GqlException
     */
    public function testSortedTriesEachKeyInTheOrderTheyAreWritten(): void
    {
        $keys = [new SortKey(new VariableExpression('owner')), new SortKey(new VariableExpression('name'), SortDirection::Descending)];
        $rows = [
            new BindingRow(['owner' => new StringDatum('App\Http\Controller'), 'name' => new StringDatum('show')]),
            new BindingRow(['owner' => new StringDatum('App\Cache\Store'), 'name' => new StringDatum('get')]),
            new BindingRow(['owner' => new StringDatum('App\Http\Controller'), 'name' => new StringDatum('store')]),
        ];

        self::assertEquals(
            [
                new BindingRow(['owner' => new StringDatum('App\Cache\Store'), 'name' => new StringDatum('get')]),
                new BindingRow(['owner' => new StringDatum('App\Http\Controller'), 'name' => new StringDatum('store')]),
                new BindingRow(['owner' => new StringDatum('App\Http\Controller'), 'name' => new StringDatum('show')]),
            ],
            (new RowExecution(new ExpressionEvaluation()))->sorted($keys, $rows),
        );
    }

    /**
     * @throws GqlException
     */
    public function testSortedPutsTheAbsenceOfAValueFirst(): void
    {
        $rows = [new BindingRow(['line' => new IntegerDatum(3)]), new BindingRow(['line' => new NullDatum()])];

        self::assertEquals(
            [new BindingRow(['line' => new NullDatum()]), new BindingRow(['line' => new IntegerDatum(3)])],
            (new RowExecution(new ExpressionEvaluation()))->sorted([new SortKey(new VariableExpression('line'))], $rows),
        );
    }

    /**
     * @throws GqlException
     */
    public function testSortedReportsAKeyHoldingValuesGqlGivesNoOrderBetween(): void
    {
        $rows = [new BindingRow(['key' => new IntegerDatum(1)]), new BindingRow(['key' => new StringDatum('a')])];

        $this->expectException(GqlException::class);
        $this->expectExceptionMessage('[22G04] error: data exception - values not comparable');

        (new RowExecution(new ExpressionEvaluation()))->sorted([new SortKey(new VariableExpression('key'))], $rows);
    }

    /**
     * @throws GqlException
     */
    public function testSortedProducesNoRowsFromNoRows(): void
    {
        self::assertSame([], (new RowExecution(new ExpressionEvaluation()))->sorted([], []));
    }

    /**
     * @throws GqlException
     */
    public function testAgainstKeepsRowsThatTieOnEveryKeyInTheOrderTheyArrivedIn(): void
    {
        $left = ['row' => BindingRow::unit(), 'values' => [], 'index' => 0];
        $right = ['row' => BindingRow::unit(), 'values' => [], 'index' => 1];

        self::assertSame(-1, RowExecution::against([], $left, $right));
    }

    /**
     * @throws GqlException
     */
    public function testAgainstTurnsTheComparisonRoundForADescendingKey(): void
    {
        $left = ['row' => BindingRow::unit(), 'values' => [new IntegerDatum(1)], 'index' => 0];
        $right = ['row' => BindingRow::unit(), 'values' => [new IntegerDatum(2)], 'index' => 1];

        self::assertSame(1, RowExecution::against([new SortKey(new VariableExpression('n'), SortDirection::Descending)], $left, $right));
    }

    public function testPageKeepsTheStretchOfRowsItIsAskedFor(): void
    {
        $table = new BindingTable([
            new BindingRow(['n' => new IntegerDatum(1)]),
            new BindingRow(['n' => new IntegerDatum(2)]),
            new BindingRow(['n' => new IntegerDatum(3)]),
        ]);

        self::assertEquals(
            new BindingTable([new BindingRow(['n' => new IntegerDatum(2)])]),
            (new RowExecution(new ExpressionEvaluation()))->page(new PageClause(1, 1), $table),
        );
    }

    public function testPageKeepsEverythingAfterTheOffsetWhenNoLimitIsWritten(): void
    {
        $table = new BindingTable([new BindingRow(['n' => new IntegerDatum(1)]), new BindingRow(['n' => new IntegerDatum(2)])]);

        self::assertEquals(
            new BindingTable([new BindingRow(['n' => new IntegerDatum(2)])]),
            (new RowExecution(new ExpressionEvaluation()))->page(new PageClause(1), $table),
        );
    }

    public function testPageKeepsNothingWhenAStretchOfNoRowsIsAskedFor(): void
    {
        self::assertEquals(BindingTable::nothing(), (new RowExecution(new ExpressionEvaluation()))->page(new PageClause(0, 0), BindingTable::unit()));
    }
}
