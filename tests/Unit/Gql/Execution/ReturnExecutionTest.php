<?php

declare(strict_types=1);

namespace Tests\Unit\Gql\Execution;

use App\Gql\Argument\ExactArithmetic;
use App\Gql\Argument\NumberArgument;
use App\Gql\Binding\BindingRow;
use App\Gql\Binding\BindingTable;
use App\Gql\Datum\DatumIdentity;
use App\Gql\Datum\DatumKind;
use App\Gql\Datum\DatumOrder;
use App\Gql\Datum\DecimalDatum;
use App\Gql\Datum\EdgeDatum;
use App\Gql\Datum\IntegerDatum;
use App\Gql\Datum\ListDatum;
use App\Gql\Datum\StringDatum;
use App\Gql\Evaluation\AggregateDetection;
use App\Gql\Evaluation\Arithmetic;
use App\Gql\Evaluation\BinaryOperation;
use App\Gql\Evaluation\ExpressionEvaluation;
use App\Gql\Execution\ReturnExecution;
use App\Gql\Execution\RowExecution;
use App\Gql\GqlException;
use App\Gql\Invocation\AggregateCatalog;
use App\Gql\Invocation\CountAccumulator;
use App\Gql\Invocation\ExtremeAccumulator;
use App\Gql\Syntax\Clause\PageClause;
use App\Gql\Syntax\Clause\Projection;
use App\Gql\Syntax\Clause\ReturnClause;
use App\Gql\Syntax\Clause\SortDirection;
use App\Gql\Syntax\Clause\SortKey;
use App\Gql\Syntax\Expression\BinaryExpression;
use App\Gql\Syntax\Expression\BinaryOperator;
use App\Gql\Syntax\Expression\CallExpression;
use App\Gql\Syntax\Expression\LiteralExpression;
use App\Gql\Syntax\Expression\PropertyExpression;
use App\Gql\Syntax\Expression\VariableExpression;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(ReturnExecution::class)]
#[UsesClass(ExactArithmetic::class)]
#[UsesClass(NumberArgument::class)]
#[UsesClass(BindingRow::class)]
#[UsesClass(BindingTable::class)]
#[UsesClass(DatumIdentity::class)]
#[UsesClass(DatumKind::class)]
#[UsesClass(DatumOrder::class)]
#[UsesClass(DecimalDatum::class)]
#[UsesClass(EdgeDatum::class)]
#[UsesClass(IntegerDatum::class)]
#[UsesClass(ListDatum::class)]
#[UsesClass(StringDatum::class)]
#[UsesClass(AggregateDetection::class)]
#[UsesClass(Arithmetic::class)]
#[UsesClass(BinaryOperation::class)]
#[UsesClass(ExpressionEvaluation::class)]
#[UsesClass(RowExecution::class)]
#[UsesClass(AggregateCatalog::class)]
#[UsesClass(CountAccumulator::class)]
#[UsesClass(ExtremeAccumulator::class)]
#[UsesClass(PageClause::class)]
#[UsesClass(Projection::class)]
#[UsesClass(ReturnClause::class)]
#[UsesClass(SortDirection::class)]
#[UsesClass(SortKey::class)]
#[UsesClass(BinaryExpression::class)]
#[UsesClass(CallExpression::class)]
#[UsesClass(LiteralExpression::class)]
#[UsesClass(PropertyExpression::class)]
#[UsesClass(VariableExpression::class)]
#[Small]
final class ReturnExecutionTest extends TestCase
{
    public function testHeadingsNamesTheColumnsTheProjectionAsksFor(): void
    {
        $clause = new ReturnClause([
            new Projection(new PropertyExpression(new VariableExpression('p'), 'name'), 'name', 'p.name'),
            new Projection(new PropertyExpression(new VariableExpression('p'), 'line'), 'line', 'p.line'),
        ]);
        $execution = new ReturnExecution(new RowExecution(new ExpressionEvaluation()));

        self::assertSame(['name', 'line'], $execution->headings($clause, BindingTable::unit()));
    }

    public function testHeadingsNamesAColumnGivenNoNameAfterWhatProducedIt(): void
    {
        $clause = new ReturnClause([new Projection(new PropertyExpression(new VariableExpression('p'), 'name'), null, 'p.name')]);
        $execution = new ReturnExecution(new RowExecution(new ExpressionEvaluation()));

        self::assertSame(['p.name'], $execution->headings($clause, BindingTable::unit()));
    }

    public function testHeadingsShowsWhatIsBoundWhenTheProjectionAsksForEverything(): void
    {
        $table = new BindingTable([new BindingRow(['n' => new IntegerDatum(1), 'm' => new IntegerDatum(2)])]);
        $execution = new ReturnExecution(new RowExecution(new ExpressionEvaluation()));

        self::assertSame(['n', 'm'], $execution->headings(new ReturnClause(), $table));
    }

    /**
     * @throws GqlException
     */
    public function testRunProducesOneRowPerRowWhenNothingIsSummarised(): void
    {
        $clause = new ReturnClause([
            new Projection(new BinaryExpression(BinaryOperator::Add, new VariableExpression('n'), new LiteralExpression(new IntegerDatum(1))), 'next'),
        ]);
        $table = new BindingTable([new BindingRow(['n' => new IntegerDatum(1)]), new BindingRow(['n' => new IntegerDatum(2)])]);
        $execution = new ReturnExecution(new RowExecution(new ExpressionEvaluation()));

        self::assertEquals(
            new BindingTable([
                new BindingRow(['n' => new IntegerDatum(1), 'next' => new IntegerDatum(2)]),
                new BindingRow(['n' => new IntegerDatum(2), 'next' => new IntegerDatum(3)]),
            ]),
            $execution->run($clause, $table),
        );
    }

    /**
     * @throws GqlException
     */
    public function testRunShowsRowsThatRepeatWhatIsShownOnceWhenAskedForWhatDiffers(): void
    {
        $clause = new ReturnClause([new Projection(new VariableExpression('kind'), 'kind')], true);
        $table = new BindingTable([
            new BindingRow(['id' => new StringDatum('App\Http\Controller'), 'kind' => new StringDatum('class')]),
            new BindingRow(['id' => new StringDatum('App\Http\Controller::show'), 'kind' => new StringDatum('method')]),
            new BindingRow(['id' => new StringDatum('App\Domain\Invoice'), 'kind' => new StringDatum('class')]),
        ]);
        $execution = new ReturnExecution(new RowExecution(new ExpressionEvaluation()));

        self::assertEquals(
            new BindingTable([
                new BindingRow(['id' => new StringDatum('App\Http\Controller'), 'kind' => new StringDatum('class')]),
                new BindingRow(['id' => new StringDatum('App\Http\Controller::show'), 'kind' => new StringDatum('method')]),
            ]),
            $execution->run($clause, $table),
        );
    }

    /**
     * @throws GqlException
     */
    public function testRunKeepsTheStretchOfRowsTheProjectionAsksFor(): void
    {
        $clause = new ReturnClause([new Projection(new VariableExpression('n'), 'n')], false, [], [], new PageClause(1, 1));
        $table = new BindingTable([
            new BindingRow(['n' => new IntegerDatum(1)]),
            new BindingRow(['n' => new IntegerDatum(2)]),
            new BindingRow(['n' => new IntegerDatum(3)]),
        ]);
        $execution = new ReturnExecution(new RowExecution(new ExpressionEvaluation()));

        self::assertEquals(new BindingTable([new BindingRow(['n' => new IntegerDatum(2)])]), $execution->run($clause, $table));
    }

    /**
     * @throws GqlException
     */
    public function testRunSortsBySomethingTheProjectionDoesNotShow(): void
    {
        $clause = new ReturnClause([new Projection(new VariableExpression('name'), 'shown')], false, [], [new SortKey(new VariableExpression('line'))]);
        $table = new BindingTable([
            new BindingRow(['name' => new StringDatum('store'), 'line' => new IntegerDatum(30)]),
            new BindingRow(['name' => new StringDatum('show'), 'line' => new IntegerDatum(20)]),
        ]);
        $execution = new ReturnExecution(new RowExecution(new ExpressionEvaluation()));

        self::assertEquals(
            new BindingTable([
                new BindingRow(['name' => new StringDatum('show'), 'line' => new IntegerDatum(20), 'shown' => new StringDatum('show')]),
                new BindingRow(['name' => new StringDatum('store'), 'line' => new IntegerDatum(30), 'shown' => new StringDatum('store')]),
            ]),
            $execution->run($clause, $table),
        );
    }

    /**
     * @throws GqlException
     */
    public function testRunAnswersOnceForASummaryOverNoRowsAtAll(): void
    {
        $clause = new ReturnClause([new Projection(new CallExpression('count', [], false, true), 'n', 'count(*)')]);
        $execution = new ReturnExecution(new RowExecution(new ExpressionEvaluation()));

        self::assertEquals(new BindingTable([new BindingRow(['n' => new IntegerDatum(0)])]), $execution->run($clause, BindingTable::nothing()));
    }

    /**
     * @throws GqlException
     */
    public function testRunSummarisesAGroupListAlongTheRowRatherThanDownTheTable(): void
    {
        $first = new EdgeDatum('show>total', ['call'], ['line' => new IntegerDatum(22)], 'show', 'total');
        $second = new EdgeDatum('total>get', ['call'], ['line' => new IntegerDatum(14)], 'total', 'get');
        $clause = new ReturnClause([new Projection(new CallExpression('min', [new PropertyExpression(new VariableExpression('e'), 'line')]), 'earliest')]);
        $table = new BindingTable([
            new BindingRow(['e' => new ListDatum([$first])]),
            new BindingRow(['e' => new ListDatum([$first, $second])]),
        ]);
        $execution = new ReturnExecution(new RowExecution(new ExpressionEvaluation()));

        self::assertEquals(
            new BindingTable([
                new BindingRow(['e' => new ListDatum([$first]), 'earliest' => new IntegerDatum(22)]),
                new BindingRow(['e' => new ListDatum([$first, $second]), 'earliest' => new IntegerDatum(14)]),
            ]),
            $execution->run($clause, $table, ['e']),
        );
    }

    #[DataProvider('providerProjectionsAndWhetherTheySummarise')]
    public function testSummarisesDecidesFromTheShapeOfWhatIsWritten(ReturnClause $clause, bool $summarises): void
    {
        $execution = new ReturnExecution(new RowExecution(new ExpressionEvaluation()));

        self::assertSame($summarises, $execution->summarises($clause, ['e']));
    }

    /**
     * @return iterable<string, array{ReturnClause, bool}>
     */
    public static function providerProjectionsAndWhetherTheySummarise(): iterable
    {
        yield 'a projection that groups' => [
            new ReturnClause([new Projection(new VariableExpression('owner'), 'owner')], false, [new VariableExpression('owner')]),
            true,
        ];

        yield 'a projection holding a summary, without being told to group' => [
            new ReturnClause([new Projection(new CallExpression('count', [], false, true), 'n')]),
            true,
        ];

        yield 'a projection holding a summary buried in an expression' => [
            new ReturnClause([
                new Projection(new BinaryExpression(BinaryOperator::Add, new CallExpression('count', [], false, true), new LiteralExpression(new IntegerDatum(1))), 'n'),
            ]),
            true,
        ];

        yield 'a projection that shows properties' => [
            new ReturnClause([new Projection(new PropertyExpression(new VariableExpression('p'), 'name'), 'name')]),
            false,
        ];

        yield 'a projection that summarises only what one path crossed' => [
            new ReturnClause([new Projection(new CallExpression('min', [new PropertyExpression(new VariableExpression('e'), 'line')]), 'earliest')]),
            false,
        ];

        yield 'a projection that asks for everything' => [new ReturnClause(), false];
    }

    /**
     * @throws GqlException
     */
    public function testReportedAddsTheProjectedColumnsToTheRowTheyCameFrom(): void
    {
        $clause = new ReturnClause([new Projection(new VariableExpression('p'), 'shown')]);
        $execution = new ReturnExecution(new RowExecution(new ExpressionEvaluation()));

        self::assertEquals(
            [new BindingRow(['p' => new IntegerDatum(1), 'shown' => new IntegerDatum(1)])],
            $execution->reported($clause, new BindingTable([new BindingRow(['p' => new IntegerDatum(1)])])),
        );
    }

    /**
     * @throws GqlException
     */
    public function testReportedLeavesItsRowsAloneWhenTheProjectionAsksForEverything(): void
    {
        $execution = new ReturnExecution(new RowExecution(new ExpressionEvaluation()));

        self::assertEquals(
            [new BindingRow(['p' => new IntegerDatum(1)])],
            $execution->reported(new ReturnClause(), new BindingTable([new BindingRow(['p' => new IntegerDatum(1)])])),
        );
    }

    /**
     * @throws GqlException
     */
    public function testSummarisedWorksTheSummariesOutOverEachGroup(): void
    {
        $clause = new ReturnClause(
            [new Projection(new VariableExpression('owner'), 'owner'), new Projection(new CallExpression('count', [], false, true), 'n')],
            false,
            [new VariableExpression('owner')],
        );
        $table = new BindingTable([
            new BindingRow(['owner' => new StringDatum('App\Http\Controller'), 'name' => new StringDatum('show')]),
            new BindingRow(['owner' => new StringDatum('App\Http\Controller'), 'name' => new StringDatum('store')]),
            new BindingRow(['owner' => new StringDatum('App\Cache\Store'), 'name' => new StringDatum('get')]),
        ]);
        $execution = new ReturnExecution(new RowExecution(new ExpressionEvaluation()));

        self::assertEquals(
            [
                new BindingRow(['owner' => new StringDatum('App\Http\Controller'), 'name' => new StringDatum('show'), 'n' => new IntegerDatum(2)]),
                new BindingRow(['owner' => new StringDatum('App\Cache\Store'), 'name' => new StringDatum('get'), 'n' => new IntegerDatum(1)]),
            ],
            $execution->summarised($clause, $table),
        );
    }

    /**
     * @throws GqlException
     */
    public function testSummarisedAnswersOnceWhenThereIsNothingToSummarise(): void
    {
        $clause = new ReturnClause([new Projection(new CallExpression('count', [], false, true), 'n', 'count(*)')]);
        $execution = new ReturnExecution(new RowExecution(new ExpressionEvaluation()));

        self::assertEquals([new BindingRow(['n' => new IntegerDatum(0)])], $execution->summarised($clause, BindingTable::nothing()));
    }

    public function testGroupKeysReadsAGroupingKeyThatNamesAColumnOfTheProjectionAsThatColumn(): void
    {
        $shown = new PropertyExpression(new VariableExpression('p'), 'kind');
        $clause = new ReturnClause([new Projection($shown, 'kind', 'p.kind')], false, [new VariableExpression('kind')]);

        self::assertSame([$shown], ReturnExecution::groupKeys($clause));
    }

    public function testGroupKeysLeavesAKeyThatNamesNoColumnAsItWasWritten(): void
    {
        $key = new VariableExpression('kind');

        self::assertSame([$key], ReturnExecution::groupKeys(new ReturnClause([], false, [$key])));
    }

    /**
     * @throws GqlException
     */
    public function testGroupsMakesOneGroupOfEverythingWhenNothingIsGroupedBy(): void
    {
        self::assertSame([[]], ReturnExecution::groups([], []));
    }

    /**
     * @throws GqlException
     */
    public function testGroupsGathersTheRowsThatAgreeOnEveryKey(): void
    {
        $rows = [
            new BindingRow(['owner' => new StringDatum('App\Http\Controller'), 'name' => new StringDatum('show')]),
            new BindingRow(['owner' => new StringDatum('App\Cache\Store'), 'name' => new StringDatum('get')]),
            new BindingRow(['owner' => new StringDatum('App\Http\Controller'), 'name' => new StringDatum('store')]),
        ];

        self::assertEquals(
            [
                [
                    new BindingRow(['owner' => new StringDatum('App\Http\Controller'), 'name' => new StringDatum('show')]),
                    new BindingRow(['owner' => new StringDatum('App\Http\Controller'), 'name' => new StringDatum('store')]),
                ],
                [new BindingRow(['owner' => new StringDatum('App\Cache\Store'), 'name' => new StringDatum('get')])],
            ],
            ReturnExecution::groups([new VariableExpression('owner')], $rows),
        );
    }

    public function testOnceShowsRowsThatAgreeOnWhatIsShownOnlyOnce(): void
    {
        $rows = [
            new BindingRow(['n' => new IntegerDatum(1), 'id' => new StringDatum('a')]),
            new BindingRow(['n' => new IntegerDatum(1), 'id' => new StringDatum('b')]),
        ];

        self::assertEquals([new BindingRow(['n' => new IntegerDatum(1), 'id' => new StringDatum('a')])], ReturnExecution::once(['n'], $rows));
    }

    public function testOnceKeepsRowsThatDifferInWhatIsShown(): void
    {
        $rows = [new BindingRow(['n' => new IntegerDatum(1)]), new BindingRow(['n' => new IntegerDatum(2)])];

        self::assertEquals(
            [new BindingRow(['n' => new IntegerDatum(1)]), new BindingRow(['n' => new IntegerDatum(2)])],
            ReturnExecution::once(['n'], $rows),
        );
    }
}
