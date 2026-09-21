<?php

declare(strict_types=1);

namespace Tests\Unit\Gql\Execution;

use App\Gql\Binding\BindingRow;
use App\Gql\Binding\BindingTable;
use App\Gql\Datum\BooleanDatum;
use App\Gql\Datum\DatumKind;
use App\Gql\Datum\DatumOrder;
use App\Gql\Datum\EdgeDatum;
use App\Gql\Datum\IntegerDatum;
use App\Gql\Datum\NodeDatum;
use App\Gql\Datum\NullDatum;
use App\Gql\Datum\PathDatum;
use App\Gql\Datum\StringDatum;
use App\Gql\Element\ElementGraph;
use App\Gql\Evaluation\BinaryOperation;
use App\Gql\Evaluation\Comparison;
use App\Gql\Evaluation\ExpressionEvaluation;
use App\Gql\Evaluation\Logic;
use App\Gql\Execution\MatchExecution;
use App\Gql\GqlException;
use App\Gql\Matching\EdgeMatching;
use App\Gql\Matching\EdgeTraversal;
use App\Gql\Matching\ElementMatching;
use App\Gql\Matching\LabelMatching;
use App\Gql\Matching\MatchState;
use App\Gql\Matching\PathMatching;
use App\Gql\Matching\PathModeRule;
use App\Gql\Matching\PatternMatching;
use App\Gql\Matching\PatternVariables;
use App\Gql\Syntax\Clause\MatchClause;
use App\Gql\Syntax\Expression\BinaryExpression;
use App\Gql\Syntax\Expression\BinaryOperator;
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
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(MatchExecution::class)]
#[UsesClass(BindingRow::class)]
#[UsesClass(BindingTable::class)]
#[UsesClass(BooleanDatum::class)]
#[UsesClass(DatumKind::class)]
#[UsesClass(DatumOrder::class)]
#[UsesClass(EdgeDatum::class)]
#[UsesClass(IntegerDatum::class)]
#[UsesClass(NodeDatum::class)]
#[UsesClass(PathDatum::class)]
#[UsesClass(StringDatum::class)]
#[UsesClass(ElementGraph::class)]
#[UsesClass(BinaryOperation::class)]
#[UsesClass(Comparison::class)]
#[UsesClass(ExpressionEvaluation::class)]
#[UsesClass(Logic::class)]
#[UsesClass(EdgeMatching::class)]
#[UsesClass(EdgeTraversal::class)]
#[UsesClass(ElementMatching::class)]
#[UsesClass(LabelMatching::class)]
#[UsesClass(MatchState::class)]
#[UsesClass(PathMatching::class)]
#[UsesClass(PathModeRule::class)]
#[UsesClass(PatternMatching::class)]
#[UsesClass(PatternVariables::class)]
#[UsesClass(MatchClause::class)]
#[UsesClass(BinaryExpression::class)]
#[UsesClass(LiteralExpression::class)]
#[UsesClass(PropertyExpression::class)]
#[UsesClass(VariableExpression::class)]
#[UsesClass(EdgePattern::class)]
#[UsesClass(ElementFilter::class)]
#[UsesClass(GraphPattern::class)]
#[UsesClass(LabelPattern::class)]
#[UsesClass(NodePattern::class)]
#[UsesClass(PathPattern::class)]
#[Small]
final class MatchExecutionTest extends TestCase
{
    /**
     * @throws GqlException
     */
    public function testRunProducesOneRowPerWayThePatternMatched(): void
    {
        $show = new NodeDatum('show');
        $store = new NodeDatum('store');
        $total = new NodeDatum('total');
        $showCallsTotal = new EdgeDatum('show>total', ['call'], [], 'show', 'total');
        $storeCallsTotal = new EdgeDatum('store>total', ['call'], [], 'store', 'total');
        $graph = new ElementGraph(
            ['show' => $show, 'store' => $store, 'total' => $total],
            ['show' => [$showCallsTotal], 'store' => [$storeCallsTotal]],
            ['total' => [$showCallsTotal, $storeCallsTotal]],
        );
        $clause = new MatchClause(new GraphPattern([
            new PathPattern([new NodePattern('a'), new EdgePattern(EdgeDirection::Along), new NodePattern('b')], PathMode::Walk),
        ]));

        self::assertEquals(
            new BindingTable([new BindingRow(['a' => $show, 'b' => $total]), new BindingRow(['a' => $store, 'b' => $total])]),
            (new MatchExecution($graph, new ExpressionEvaluation()))->run($clause, BindingTable::unit()),
        );
    }

    /**
     * @throws GqlException
     */
    public function testRunStartsTheSearchFromTheNamesAnEarlierMatchBound(): void
    {
        $show = new NodeDatum('show');
        $store = new NodeDatum('store');
        $total = new NodeDatum('total');
        $showCallsTotal = new EdgeDatum('show>total', ['call'], [], 'show', 'total');
        $storeCallsTotal = new EdgeDatum('store>total', ['call'], [], 'store', 'total');
        $graph = new ElementGraph(
            ['show' => $show, 'store' => $store, 'total' => $total],
            ['show' => [$showCallsTotal], 'store' => [$storeCallsTotal]],
            ['total' => [$showCallsTotal, $storeCallsTotal]],
        );
        $clause = new MatchClause(new GraphPattern([
            new PathPattern([new NodePattern('a'), new EdgePattern(EdgeDirection::Along), new NodePattern('b')], PathMode::Walk),
        ]));

        self::assertEquals(
            new BindingTable([new BindingRow(['a' => $store, 'b' => $total])]),
            (new MatchExecution($graph, new ExpressionEvaluation()))->run($clause, new BindingTable([new BindingRow(['a' => $store])])),
        );
    }

    /**
     * @throws GqlException
     */
    public function testRunMatchesEveryRowItIsGivenSeparately(): void
    {
        $show = new NodeDatum('show');
        $store = new NodeDatum('store');
        $total = new NodeDatum('total');
        $showCallsTotal = new EdgeDatum('show>total', ['call'], [], 'show', 'total');
        $storeCallsTotal = new EdgeDatum('store>total', ['call'], [], 'store', 'total');
        $graph = new ElementGraph(
            ['show' => $show, 'store' => $store, 'total' => $total],
            ['show' => [$showCallsTotal], 'store' => [$storeCallsTotal]],
            ['total' => [$showCallsTotal, $storeCallsTotal]],
        );
        $clause = new MatchClause(new GraphPattern([
            new PathPattern([new NodePattern('a'), new EdgePattern(EdgeDirection::Along), new NodePattern('b')], PathMode::Walk),
        ]));
        $table = new BindingTable([new BindingRow(['a' => $show]), new BindingRow(['a' => $total]), new BindingRow(['a' => $store])]);

        self::assertEquals(
            new BindingTable([new BindingRow(['a' => $show, 'b' => $total]), new BindingRow(['a' => $store, 'b' => $total])]),
            (new MatchExecution($graph, new ExpressionEvaluation()))->run($clause, $table),
        );
    }

    /**
     * @throws GqlException
     */
    public function testRunProducesNoRowsWhenThePatternMatchesNothing(): void
    {
        $graph = new ElementGraph(['controller' => new NodeDatum('controller', ['Class'])], [], []);
        $clause = new MatchClause(new GraphPattern([new PathPattern([new NodePattern('p', LabelPattern::named('Interface'))], PathMode::Walk)]));

        self::assertEquals(BindingTable::nothing(), (new MatchExecution($graph, new ExpressionEvaluation()))->run($clause, BindingTable::unit()));
    }

    /**
     * @throws GqlException
     */
    public function testRunKeepsARowAnOptionalPatternMatchedNothingFor(): void
    {
        $controller = new NodeDatum('controller', ['Class']);
        $kernel = new NodeDatum('kernel', ['Class']);
        $invoice = new NodeDatum('invoice', ['Class']);
        $extends = new EdgeDatum('controller>kernel', ['extends'], [], 'controller', 'kernel');
        $graph = new ElementGraph(
            ['controller' => $controller, 'kernel' => $kernel, 'invoice' => $invoice],
            ['controller' => [$extends]],
            ['kernel' => [$extends]],
        );
        $clause = new MatchClause(
            new GraphPattern([
                new PathPattern([new NodePattern('c'), new EdgePattern(EdgeDirection::Along, 'e', LabelPattern::named('extends')), new NodePattern('parent')], PathMode::Walk),
            ]),
            null,
            true,
        );
        $table = new BindingTable([new BindingRow(['c' => $controller]), new BindingRow(['c' => $invoice])]);

        self::assertEquals(
            new BindingTable([
                new BindingRow(['c' => $controller, 'e' => $extends, 'parent' => $kernel]),
                new BindingRow(['c' => $invoice, 'e' => new NullDatum(), 'parent' => new NullDatum()]),
            ]),
            (new MatchExecution($graph, new ExpressionEvaluation()))->run($clause, $table),
        );
    }

    /**
     * @throws GqlException
     */
    public function testRunDropsARowAPatternThatIsNotOptionalMatchedNothingFor(): void
    {
        $controller = new NodeDatum('controller', ['Class']);
        $kernel = new NodeDatum('kernel', ['Class']);
        $invoice = new NodeDatum('invoice', ['Class']);
        $extends = new EdgeDatum('controller>kernel', ['extends'], [], 'controller', 'kernel');
        $graph = new ElementGraph(
            ['controller' => $controller, 'kernel' => $kernel, 'invoice' => $invoice],
            ['controller' => [$extends]],
            ['kernel' => [$extends]],
        );
        $clause = new MatchClause(new GraphPattern([
            new PathPattern([new NodePattern('c'), new EdgePattern(EdgeDirection::Along, null, LabelPattern::named('extends')), new NodePattern('parent')], PathMode::Walk),
        ]));
        $table = new BindingTable([new BindingRow(['c' => $controller]), new BindingRow(['c' => $invoice])]);

        self::assertEquals(
            new BindingTable([new BindingRow(['c' => $controller, 'parent' => $kernel])]),
            (new MatchExecution($graph, new ExpressionEvaluation()))->run($clause, $table),
        );
    }

    public function testAbsentLeavesNothingBoundForAPatternThatBindsNothing(): void
    {
        $clause = new MatchClause(new GraphPattern([new PathPattern([new NodePattern()], PathMode::Walk)]), null, true);

        self::assertSame([], MatchExecution::absent($clause, BindingRow::unit()));
    }

    public function testAbsentLeavesANameTheRowAlreadyCarriesAsItWas(): void
    {
        $clause = new MatchClause(new GraphPattern([new PathPattern([new NodePattern('c')], PathMode::Walk)]), null, true);

        self::assertSame([], MatchExecution::absent($clause, new BindingRow(['c' => new IntegerDatum(1)])));
    }

    public function testAbsentBindsTheNamesThePatternWouldHaveBoundToNothing(): void
    {
        $clause = new MatchClause(
            new GraphPattern([new PathPattern([new NodePattern('c'), new EdgePattern(EdgeDirection::Along, 'e'), new NodePattern('parent')], PathMode::Walk, 'p')]),
            null,
            true,
        );

        self::assertEquals(
            ['p' => new NullDatum(), 'e' => new NullDatum(), 'parent' => new NullDatum()],
            MatchExecution::absent($clause, new BindingRow(['c' => new IntegerDatum(1)])),
        );
    }

    /**
     * @throws GqlException
     */
    public function testKeptNarrowsTheWholeMatchRatherThanTheSearch(): void
    {
        $show = new NodeDatum('show', [], ['name' => new StringDatum('show')]);
        $total = new NodeDatum('total', [], ['name' => new StringDatum('total')]);
        $get = new NodeDatum('get', [], ['name' => new StringDatum('get')]);
        $showCallsTotal = new EdgeDatum('show>total', ['call'], [], 'show', 'total');
        $totalCallsGet = new EdgeDatum('total>get', ['call'], [], 'total', 'get');
        $graph = new ElementGraph(
            ['show' => $show, 'total' => $total, 'get' => $get],
            ['show' => [$showCallsTotal], 'total' => [$totalCallsGet]],
            ['total' => [$showCallsTotal], 'get' => [$totalCallsGet]],
        );
        $clause = new MatchClause(
            new GraphPattern([new PathPattern([new NodePattern('a'), new EdgePattern(EdgeDirection::Along), new NodePattern('b')], PathMode::Walk)]),
            new BinaryExpression(BinaryOperator::Equal, new PropertyExpression(new VariableExpression('b'), 'name'), new LiteralExpression(new StringDatum('get'))),
        );

        self::assertEquals(
            [new BindingRow(['a' => $total, 'b' => $get])],
            (new MatchExecution($graph, new ExpressionEvaluation()))->kept($clause, BindingRow::unit()),
        );
    }

    /**
     * @throws GqlException
     */
    public function testKeptKeepsEveryMatchWhenNothingNarrowsIt(): void
    {
        $show = new NodeDatum('show');
        $clause = new MatchClause(new GraphPattern([new PathPattern([new NodePattern('p')], PathMode::Walk)]));

        self::assertEquals(
            [new BindingRow(['p' => $show])],
            (new MatchExecution(new ElementGraph(['show' => $show], [], []), new ExpressionEvaluation()))->kept($clause, BindingRow::unit()),
        );
    }

    /**
     * @throws GqlException
     */
    public function testKeptMatchesNothingOverAGraphWithNoSymbols(): void
    {
        $clause = new MatchClause(new GraphPattern([new PathPattern([new NodePattern('p')], PathMode::Walk)]));

        self::assertSame([], (new MatchExecution(new ElementGraph([], [], []), new ExpressionEvaluation()))->kept($clause, BindingRow::unit()));
    }
}
