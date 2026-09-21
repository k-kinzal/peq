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
use App\Gql\Datum\EdgeDatum;
use App\Gql\Datum\IntegerDatum;
use App\Gql\Datum\ListDatum;
use App\Gql\Datum\NodeDatum;
use App\Gql\Datum\PathDatum;
use App\Gql\Datum\StringDatum;
use App\Gql\Element\ElementGraph;
use App\Gql\Evaluation\AggregateDetection;
use App\Gql\Evaluation\Arithmetic;
use App\Gql\Evaluation\BinaryOperation;
use App\Gql\Evaluation\Comparison;
use App\Gql\Evaluation\ExpressionEvaluation;
use App\Gql\Evaluation\Logic;
use App\Gql\Execution\BlockExecution;
use App\Gql\Execution\MatchExecution;
use App\Gql\Execution\ReturnExecution;
use App\Gql\Execution\RowExecution;
use App\Gql\GqlException;
use App\Gql\Invocation\AggregateCatalog;
use App\Gql\Invocation\ExtremeAccumulator;
use App\Gql\Matching\EdgeMatching;
use App\Gql\Matching\EdgeTraversal;
use App\Gql\Matching\ElementMatching;
use App\Gql\Matching\LabelMatching;
use App\Gql\Matching\MatchState;
use App\Gql\Matching\PathMatching;
use App\Gql\Matching\PathModeRule;
use App\Gql\Matching\PatternMatching;
use App\Gql\Matching\PatternVariables;
use App\Gql\Result\ResultColumn;
use App\Gql\Result\ResultRow;
use App\Gql\Result\ResultTable;
use App\Gql\StatusCode;
use App\Gql\Syntax\Clause\FilterClause;
use App\Gql\Syntax\Clause\LetClause;
use App\Gql\Syntax\Clause\MatchClause;
use App\Gql\Syntax\Clause\OrderByClause;
use App\Gql\Syntax\Clause\PageClause;
use App\Gql\Syntax\Clause\Projection;
use App\Gql\Syntax\Clause\ReturnClause;
use App\Gql\Syntax\Clause\SortDirection;
use App\Gql\Syntax\Clause\SortKey;
use App\Gql\Syntax\Clause\VariableBinding;
use App\Gql\Syntax\Expression\BinaryExpression;
use App\Gql\Syntax\Expression\BinaryOperator;
use App\Gql\Syntax\Expression\CallExpression;
use App\Gql\Syntax\Expression\LiteralExpression;
use App\Gql\Syntax\Expression\PropertyExpression;
use App\Gql\Syntax\Expression\VariableExpression;
use App\Gql\Syntax\Pattern\EdgeDirection;
use App\Gql\Syntax\Pattern\EdgePattern;
use App\Gql\Syntax\Pattern\ElementFilter;
use App\Gql\Syntax\Pattern\GraphPattern;
use App\Gql\Syntax\Pattern\LabelPattern;
use App\Gql\Syntax\Pattern\NodePattern;
use App\Gql\Syntax\Pattern\PathMode;
use App\Gql\Syntax\Pattern\PathPattern;
use App\Gql\Syntax\Pattern\Quantifier;
use App\Gql\Syntax\QueryBlock;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(BlockExecution::class)]
#[UsesClass(ExactArithmetic::class)]
#[UsesClass(NumberArgument::class)]
#[UsesClass(BindingRow::class)]
#[UsesClass(BindingTable::class)]
#[UsesClass(BooleanDatum::class)]
#[UsesClass(DatumKind::class)]
#[UsesClass(DatumOrder::class)]
#[UsesClass(DecimalDatum::class)]
#[UsesClass(EdgeDatum::class)]
#[UsesClass(IntegerDatum::class)]
#[UsesClass(ListDatum::class)]
#[UsesClass(NodeDatum::class)]
#[UsesClass(PathDatum::class)]
#[UsesClass(StringDatum::class)]
#[UsesClass(ElementGraph::class)]
#[UsesClass(AggregateDetection::class)]
#[UsesClass(Arithmetic::class)]
#[UsesClass(BinaryOperation::class)]
#[UsesClass(Comparison::class)]
#[UsesClass(ExpressionEvaluation::class)]
#[UsesClass(Logic::class)]
#[UsesClass(MatchExecution::class)]
#[UsesClass(ReturnExecution::class)]
#[UsesClass(RowExecution::class)]
#[UsesClass(GqlException::class)]
#[UsesClass(AggregateCatalog::class)]
#[UsesClass(ExtremeAccumulator::class)]
#[UsesClass(EdgeMatching::class)]
#[UsesClass(EdgeTraversal::class)]
#[UsesClass(ElementMatching::class)]
#[UsesClass(LabelMatching::class)]
#[UsesClass(MatchState::class)]
#[UsesClass(PathMatching::class)]
#[UsesClass(PathModeRule::class)]
#[UsesClass(PatternMatching::class)]
#[UsesClass(PatternVariables::class)]
#[UsesClass(ResultColumn::class)]
#[UsesClass(ResultRow::class)]
#[UsesClass(ResultTable::class)]
#[UsesClass(StatusCode::class)]
#[UsesClass(FilterClause::class)]
#[UsesClass(LetClause::class)]
#[UsesClass(MatchClause::class)]
#[UsesClass(OrderByClause::class)]
#[UsesClass(PageClause::class)]
#[UsesClass(Projection::class)]
#[UsesClass(ReturnClause::class)]
#[UsesClass(SortDirection::class)]
#[UsesClass(SortKey::class)]
#[UsesClass(VariableBinding::class)]
#[UsesClass(BinaryExpression::class)]
#[UsesClass(CallExpression::class)]
#[UsesClass(LiteralExpression::class)]
#[UsesClass(PropertyExpression::class)]
#[UsesClass(VariableExpression::class)]
#[UsesClass(EdgePattern::class)]
#[UsesClass(ElementFilter::class)]
#[UsesClass(GraphPattern::class)]
#[UsesClass(LabelPattern::class)]
#[UsesClass(NodePattern::class)]
#[UsesClass(PathPattern::class)]
#[UsesClass(Quantifier::class)]
#[UsesClass(QueryBlock::class)]
#[Small]
final class BlockExecutionTest extends TestCase
{
    /**
     * @throws GqlException
     */
    public function testRunShowsWhatTheRunOfClausesWasToldToShow(): void
    {
        $block = new QueryBlock([new ReturnClause([new Projection(new LiteralExpression(new IntegerDatum(1)), 'n')])]);

        self::assertEquals(
            new ResultTable([new ResultColumn('n', 'INT64')], [new ResultRow([new IntegerDatum(1)])]),
            (new BlockExecution(new ElementGraph([], [], [])))->run($block),
        );
    }

    /**
     * @throws GqlException
     */
    public function testRunShowsEveryNameInScopeWhenTheRunAsksForAllOfThem(): void
    {
        $block = new QueryBlock([
            new LetClause([
                new VariableBinding('n', new LiteralExpression(new IntegerDatum(1))),
                new VariableBinding('m', new LiteralExpression(new StringDatum('two'))),
            ]),
            new ReturnClause(),
        ]);

        self::assertEquals(
            new ResultTable(
                [new ResultColumn('n', 'INT64'), new ResultColumn('m', 'STRING')],
                [new ResultRow([new IntegerDatum(1), new StringDatum('two')])],
            ),
            (new BlockExecution(new ElementGraph([], [], [])))->run($block),
        );
    }

    /**
     * @throws GqlException
     */
    public function testRunHandsEachClauseWhatTheOneBeforeItProduced(): void
    {
        $graph = new ElementGraph(
            [
                'show' => new NodeDatum('show', ['Method'], ['name' => new StringDatum('show'), 'line' => new IntegerDatum(20)]),
                'store' => new NodeDatum('store', ['Method'], ['name' => new StringDatum('store'), 'line' => new IntegerDatum(30)]),
                'total' => new NodeDatum('total', ['Method'], ['name' => new StringDatum('total'), 'line' => new IntegerDatum(12)]),
            ],
            [],
            [],
        );
        $block = new QueryBlock([
            new MatchClause(new GraphPattern([new PathPattern([new NodePattern('p', LabelPattern::named('Method'))], PathMode::Walk)])),
            new FilterClause(new BinaryExpression(
                BinaryOperator::Less,
                new PropertyExpression(new VariableExpression('p'), 'line'),
                new LiteralExpression(new IntegerDatum(25)),
            )),
            new OrderByClause([new SortKey(new PropertyExpression(new VariableExpression('p'), 'line'))]),
            new ReturnClause([new Projection(new PropertyExpression(new VariableExpression('p'), 'name'), 'name')]),
        ]);

        self::assertEquals(
            new ResultTable(
                [new ResultColumn('name', 'STRING')],
                [new ResultRow([new StringDatum('total')]), new ResultRow([new StringDatum('show')])],
            ),
            (new BlockExecution($graph))->run($block),
        );
    }

    /**
     * @throws GqlException
     */
    public function testRunSummarisesWhatARepetitionCrossedAlongTheRowItWasBoundFor(): void
    {
        $show = new NodeDatum('show', [], ['name' => new StringDatum('show')]);
        $total = new NodeDatum('total', [], ['name' => new StringDatum('total')]);
        $get = new NodeDatum('get', [], ['name' => new StringDatum('get')]);
        $showCallsTotal = new EdgeDatum('show>total', ['call'], ['line' => new IntegerDatum(22)], 'show', 'total');
        $totalCallsGet = new EdgeDatum('total>get', ['call'], ['line' => new IntegerDatum(14)], 'total', 'get');
        $graph = new ElementGraph(
            ['show' => $show, 'total' => $total, 'get' => $get],
            ['show' => [$showCallsTotal], 'total' => [$totalCallsGet]],
            ['total' => [$showCallsTotal], 'get' => [$totalCallsGet]],
        );
        $block = new QueryBlock([
            new MatchClause(new GraphPattern([
                new PathPattern(
                    [
                        new NodePattern('a', null, new ElementFilter(['name' => new LiteralExpression(new StringDatum('show'))])),
                        new EdgePattern(EdgeDirection::Along, 'e', null, new ElementFilter(), new Quantifier(1, 2)),
                        new NodePattern('b'),
                    ],
                    PathMode::Walk,
                ),
            ])),
            new ReturnClause([
                new Projection(new PropertyExpression(new VariableExpression('b'), 'name'), 'reached'),
                new Projection(new CallExpression('min', [new PropertyExpression(new VariableExpression('e'), 'line')]), 'earliest'),
            ]),
        ]);

        self::assertEquals(
            new ResultTable(
                [new ResultColumn('reached', 'STRING'), new ResultColumn('earliest', 'INT64')],
                [
                    new ResultRow([new StringDatum('total'), new IntegerDatum(22)]),
                    new ResultRow([new StringDatum('get'), new IntegerDatum(14)]),
                ],
            ),
            (new BlockExecution($graph))->run($block),
        );
    }

    /**
     * @throws GqlException
     */
    public function testApplyLooksForAShapeInTheGraph(): void
    {
        $show = new NodeDatum('show', ['Method']);
        $graph = new ElementGraph(['controller' => new NodeDatum('controller', ['Class']), 'show' => $show], [], []);
        $clause = new MatchClause(new GraphPattern([new PathPattern([new NodePattern('p', LabelPattern::named('Method'))], PathMode::Walk)]));

        self::assertEquals(
            new BindingTable([new BindingRow(['p' => $show])]),
            (new BlockExecution($graph))->apply($clause, BindingTable::unit()),
        );
    }

    /**
     * @throws GqlException
     */
    public function testApplyNamesAComputedValue(): void
    {
        $clause = new LetClause([
            new VariableBinding('n', new BinaryExpression(BinaryOperator::Add, new LiteralExpression(new IntegerDatum(1)), new LiteralExpression(new IntegerDatum(1)))),
        ]);

        self::assertEquals(
            new BindingTable([new BindingRow(['n' => new IntegerDatum(2)])]),
            (new BlockExecution(new ElementGraph([], [], [])))->apply($clause, BindingTable::unit()),
        );
    }

    /**
     * @throws GqlException
     */
    public function testApplyKeepsOnlySomeRows(): void
    {
        $clause = new FilterClause(new BinaryExpression(BinaryOperator::Greater, new VariableExpression('n'), new LiteralExpression(new IntegerDatum(1))));
        $table = new BindingTable([new BindingRow(['n' => new IntegerDatum(1)]), new BindingRow(['n' => new IntegerDatum(2)])]);

        self::assertEquals(
            new BindingTable([new BindingRow(['n' => new IntegerDatum(2)])]),
            (new BlockExecution(new ElementGraph([], [], [])))->apply($clause, $table),
        );
    }

    /**
     * @throws GqlException
     */
    public function testApplyPutsTheRowsInAnOrder(): void
    {
        $clause = new OrderByClause([new SortKey(new VariableExpression('n'), SortDirection::Descending)]);
        $table = new BindingTable([new BindingRow(['n' => new IntegerDatum(1)]), new BindingRow(['n' => new IntegerDatum(2)])]);

        self::assertEquals(
            new BindingTable([new BindingRow(['n' => new IntegerDatum(2)]), new BindingRow(['n' => new IntegerDatum(1)])]),
            (new BlockExecution(new ElementGraph([], [], [])))->apply($clause, $table),
        );
    }

    /**
     * @throws GqlException
     */
    public function testApplyKeepsAStretchOfTheRows(): void
    {
        $table = new BindingTable([new BindingRow(['n' => new IntegerDatum(1)]), new BindingRow(['n' => new IntegerDatum(2)])]);

        self::assertEquals(
            new BindingTable([new BindingRow(['n' => new IntegerDatum(1)])]),
            (new BlockExecution(new ElementGraph([], [], [])))->apply(new PageClause(0, 1), $table),
        );
    }

    /**
     * @throws GqlException
     */
    public function testApplyLeavesTheProjectionToTheRunItEnds(): void
    {
        $execution = new BlockExecution(new ElementGraph([], [], []));

        $this->expectException(GqlException::class);
        $this->expectExceptionMessage('[42000] error: syntax error or access rule violation: this clause is not one peq knows how to run');

        $execution->apply(new ReturnClause(), BindingTable::unit());
    }
}
