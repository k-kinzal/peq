<?php

declare(strict_types=1);

namespace Tests\Unit\Gql\Matching;

use App\Gql\Binding\BindingRow;
use App\Gql\Datum\BooleanDatum;
use App\Gql\Datum\DatumKind;
use App\Gql\Datum\DatumOrder;
use App\Gql\Datum\EdgeDatum;
use App\Gql\Datum\IntegerDatum;
use App\Gql\Datum\ListDatum;
use App\Gql\Datum\NodeDatum;
use App\Gql\Datum\NullDatum;
use App\Gql\Datum\PathDatum;
use App\Gql\Element\ElementGraph;
use App\Gql\Evaluation\BinaryOperation;
use App\Gql\Evaluation\Comparison;
use App\Gql\Evaluation\ExpressionEvaluation;
use App\Gql\Evaluation\Logic;
use App\Gql\GqlException;
use App\Gql\Matching\EdgeMatching;
use App\Gql\Matching\EdgeTraversal;
use App\Gql\Matching\ElementMatching;
use App\Gql\Matching\LabelMatching;
use App\Gql\Matching\MatchState;
use App\Gql\Matching\PathModeRule;
use App\Gql\Syntax\Expression\BinaryExpression;
use App\Gql\Syntax\Expression\BinaryOperator;
use App\Gql\Syntax\Expression\LiteralExpression;
use App\Gql\Syntax\Expression\PropertyExpression;
use App\Gql\Syntax\Expression\VariableExpression;
use App\Gql\Syntax\Pattern\EdgeDirection;
use App\Gql\Syntax\Pattern\EdgePattern;
use App\Gql\Syntax\Pattern\ElementFilter;
use App\Gql\Syntax\Pattern\LabelOperator;
use App\Gql\Syntax\Pattern\LabelPattern;
use App\Gql\Syntax\Pattern\PathMode;
use App\Gql\Syntax\Pattern\Quantifier;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(EdgeMatching::class)]
#[UsesClass(BindingRow::class)]
#[UsesClass(BooleanDatum::class)]
#[UsesClass(DatumKind::class)]
#[UsesClass(DatumOrder::class)]
#[UsesClass(EdgeDatum::class)]
#[UsesClass(IntegerDatum::class)]
#[UsesClass(ListDatum::class)]
#[UsesClass(NodeDatum::class)]
#[UsesClass(NullDatum::class)]
#[UsesClass(PathDatum::class)]
#[UsesClass(ElementGraph::class)]
#[UsesClass(BinaryOperation::class)]
#[UsesClass(Comparison::class)]
#[UsesClass(ExpressionEvaluation::class)]
#[UsesClass(Logic::class)]
#[UsesClass(EdgeTraversal::class)]
#[UsesClass(ElementMatching::class)]
#[UsesClass(LabelMatching::class)]
#[UsesClass(MatchState::class)]
#[UsesClass(PathModeRule::class)]
#[UsesClass(BinaryExpression::class)]
#[UsesClass(BinaryOperator::class)]
#[UsesClass(LiteralExpression::class)]
#[UsesClass(PropertyExpression::class)]
#[UsesClass(VariableExpression::class)]
#[UsesClass(EdgeDirection::class)]
#[UsesClass(EdgePattern::class)]
#[UsesClass(ElementFilter::class)]
#[UsesClass(LabelOperator::class)]
#[UsesClass(LabelPattern::class)]
#[UsesClass(PathMode::class)]
#[UsesClass(Quantifier::class)]
#[Small]
final class EdgeMatchingTest extends TestCase
{
    /**
     * @throws GqlException
     */
    public function testMatchesCrossesOneRelationAndBindsItWhenNoRepetitionIsWritten(): void
    {
        $show = new NodeDatum('show');
        $total = new NodeDatum('total');
        $get = new NodeDatum('get');
        $showCallsTotal = new EdgeDatum('show>total', ['call'], [], 'show', 'total');
        $totalCallsGet = new EdgeDatum('total>get', ['call'], [], 'total', 'get');
        $graph = new ElementGraph(
            ['show' => $show, 'total' => $total, 'get' => $get],
            ['show' => [$showCallsTotal], 'total' => [$totalCallsGet]],
            ['total' => [$showCallsTotal], 'get' => [$totalCallsGet]],
        );
        $matching = new EdgeMatching($graph, new ExpressionEvaluation());

        $reached = $matching->matches(
            new EdgePattern(EdgeDirection::Along, 'e'),
            MatchState::before(BindingRow::unit())->startingAt($show),
            PathMode::Walk,
        );

        self::assertEquals([new BindingRow(['e' => $showCallsTotal])], array_column($reached, 'row'));
        self::assertEquals([new PathDatum([$show, $showCallsTotal, $total])], array_column($reached, 'path'));
    }

    /**
     * @throws GqlException
     */
    public function testMatchesBindsTheNameToTheWholeChainWhenARepetitionIsWritten(): void
    {
        $show = new NodeDatum('show');
        $total = new NodeDatum('total');
        $get = new NodeDatum('get');
        $showCallsTotal = new EdgeDatum('show>total', ['call'], [], 'show', 'total');
        $totalCallsGet = new EdgeDatum('total>get', ['call'], [], 'total', 'get');
        $graph = new ElementGraph(
            ['show' => $show, 'total' => $total, 'get' => $get],
            ['show' => [$showCallsTotal], 'total' => [$totalCallsGet]],
            ['total' => [$showCallsTotal], 'get' => [$totalCallsGet]],
        );
        $matching = new EdgeMatching($graph, new ExpressionEvaluation());

        $reached = $matching->matches(
            new EdgePattern(EdgeDirection::Along, 'e', null, new ElementFilter(), new Quantifier(2, 2)),
            MatchState::before(BindingRow::unit())->startingAt($show),
            PathMode::Walk,
        );

        self::assertEquals(
            [new BindingRow(['e' => new ListDatum([$showCallsTotal, $totalCallsGet])])],
            array_column($reached, 'row'),
        );
        self::assertEquals(
            [new PathDatum([$show, $showCallsTotal, $total, $totalCallsGet, $get])],
            array_column($reached, 'path'),
        );
    }

    /**
     * @throws GqlException
     */
    public function testMatchesReachesEverythingWithinTheRepetitionItIsAllowed(): void
    {
        $show = new NodeDatum('show');
        $total = new NodeDatum('total');
        $get = new NodeDatum('get');
        $showCallsTotal = new EdgeDatum('show>total', ['call'], [], 'show', 'total');
        $totalCallsGet = new EdgeDatum('total>get', ['call'], [], 'total', 'get');
        $graph = new ElementGraph(
            ['show' => $show, 'total' => $total, 'get' => $get],
            ['show' => [$showCallsTotal], 'total' => [$totalCallsGet]],
            ['total' => [$showCallsTotal], 'get' => [$totalCallsGet]],
        );
        $matching = new EdgeMatching($graph, new ExpressionEvaluation());

        $reached = $matching->matches(
            new EdgePattern(EdgeDirection::Along, null, null, new ElementFilter(), new Quantifier(1, 2)),
            MatchState::before(BindingRow::unit())->startingAt($show),
            PathMode::Walk,
        );

        self::assertEquals(
            [
                new PathDatum([$show, $showCallsTotal, $total]),
                new PathDatum([$show, $showCallsTotal, $total, $totalCallsGet, $get]),
            ],
            array_column($reached, 'path'),
        );
    }

    /**
     * @throws GqlException
     */
    public function testMatchesStandsStillWhenARepetitionIsAllowedToHappenNoTimes(): void
    {
        $show = new NodeDatum('show');
        $total = new NodeDatum('total');
        $showCallsTotal = new EdgeDatum('show>total', ['call'], [], 'show', 'total');
        $graph = new ElementGraph(
            ['show' => $show, 'total' => $total],
            ['show' => [$showCallsTotal]],
            ['total' => [$showCallsTotal]],
        );
        $matching = new EdgeMatching($graph, new ExpressionEvaluation());

        $reached = $matching->matches(
            new EdgePattern(EdgeDirection::Along, 'e', null, new ElementFilter(), new Quantifier(0, 1)),
            MatchState::before(BindingRow::unit())->startingAt($total),
            PathMode::Walk,
        );

        self::assertEquals([new BindingRow(['e' => new ListDatum([])])], array_column($reached, 'row'));
        self::assertEquals([new PathDatum([$total])], array_column($reached, 'path'));
    }

    /**
     * @throws GqlException
     */
    public function testMatchesRepeatsWithoutAnUpperBoundUntilTheModeEndsIt(): void
    {
        $first = new NodeDatum('first');
        $second = new NodeDatum('second');
        $third = new NodeDatum('third');
        $firstCallsSecond = new EdgeDatum('first>second', ['call'], [], 'first', 'second');
        $secondCallsThird = new EdgeDatum('second>third', ['call'], [], 'second', 'third');
        $thirdCallsFirst = new EdgeDatum('third>first', ['call'], [], 'third', 'first');
        $thirdCallsSecond = new EdgeDatum('third>second', ['call'], [], 'third', 'second');
        $graph = new ElementGraph(
            ['first' => $first, 'second' => $second, 'third' => $third],
            ['first' => [$firstCallsSecond], 'second' => [$secondCallsThird], 'third' => [$thirdCallsFirst, $thirdCallsSecond]],
            ['second' => [$firstCallsSecond, $thirdCallsSecond], 'third' => [$secondCallsThird], 'first' => [$thirdCallsFirst]],
        );
        $matching = new EdgeMatching($graph, new ExpressionEvaluation());

        $reached = $matching->matches(
            new EdgePattern(EdgeDirection::Along, null, null, new ElementFilter(), new Quantifier(1, null)),
            MatchState::before(BindingRow::unit())->startingAt($first),
            PathMode::Trail,
        );

        self::assertSame([$second, $third, $first, $second], array_column($reached, 'current'));
    }

    /**
     * @throws GqlException
     */
    public function testMatchesNarrowsAChainRelationByRelation(): void
    {
        $show = new NodeDatum('show');
        $total = new NodeDatum('total');
        $get = new NodeDatum('get');
        $showCallsTotal = new EdgeDatum('show>total', ['call'], ['line' => new IntegerDatum(22)], 'show', 'total');
        $totalCallsGet = new EdgeDatum('total>get', ['call'], ['line' => new IntegerDatum(14)], 'total', 'get');
        $graph = new ElementGraph(
            ['show' => $show, 'total' => $total, 'get' => $get],
            ['show' => [$showCallsTotal], 'total' => [$totalCallsGet]],
            ['total' => [$showCallsTotal], 'get' => [$totalCallsGet]],
        );
        $matching = new EdgeMatching($graph, new ExpressionEvaluation());
        $laterThanLineTwenty = new BinaryExpression(
            BinaryOperator::Greater,
            new PropertyExpression(new VariableExpression('e'), 'line'),
            new LiteralExpression(new IntegerDatum(20)),
        );

        $reached = $matching->matches(
            new EdgePattern(EdgeDirection::Along, 'e', null, new ElementFilter([], $laterThanLineTwenty), new Quantifier(1, 3)),
            MatchState::before(BindingRow::unit())->startingAt($show),
            PathMode::Walk,
        );

        self::assertEquals([new BindingRow(['e' => new ListDatum([$showCallsTotal])])], array_column($reached, 'row'));
    }

    /**
     * @throws GqlException
     */
    public function testMatchesCrossesOnlyARelationCarryingTheLabelItAsksFor(): void
    {
        $controller = new NodeDatum('controller');
        $kernel = new NodeDatum('kernel');
        $show = new NodeDatum('show');
        $extends = new EdgeDatum('controller>kernel', ['extends'], [], 'controller', 'kernel');
        $declares = new EdgeDatum('controller>show', ['declaresMethod'], [], 'controller', 'show');
        $graph = new ElementGraph(
            ['controller' => $controller, 'kernel' => $kernel, 'show' => $show],
            ['controller' => [$extends, $declares]],
            ['kernel' => [$extends], 'show' => [$declares]],
        );
        $matching = new EdgeMatching($graph, new ExpressionEvaluation());

        $reached = $matching->matches(
            new EdgePattern(EdgeDirection::Along, null, LabelPattern::named('extends')),
            MatchState::before(BindingRow::unit())->startingAt($controller),
            PathMode::Walk,
        );

        self::assertSame([$kernel], array_column($reached, 'current'));
    }

    /**
     * @throws GqlException
     */
    public function testMatchesCrossesARelationAgainstTheWayItPointsWhenDrawnBackwards(): void
    {
        $show = new NodeDatum('show');
        $total = new NodeDatum('total');
        $showCallsTotal = new EdgeDatum('show>total', ['call'], [], 'show', 'total');
        $graph = new ElementGraph(
            ['show' => $show, 'total' => $total],
            ['show' => [$showCallsTotal]],
            ['total' => [$showCallsTotal]],
        );
        $matching = new EdgeMatching($graph, new ExpressionEvaluation());

        $reached = $matching->matches(
            new EdgePattern(EdgeDirection::Against, 'e'),
            MatchState::before(BindingRow::unit())->startingAt($total),
            PathMode::Walk,
        );

        self::assertEquals([new PathDatum([$total, $showCallsTotal, $show])], array_column($reached, 'path'));
    }

    /**
     * @throws GqlException
     */
    public function testMatchesCrossesNothingFromAnAttemptStandingNowhere(): void
    {
        $matching = new EdgeMatching(new ElementGraph([], [], []), new ExpressionEvaluation());

        self::assertSame(
            [],
            $matching->matches(new EdgePattern(EdgeDirection::Along), MatchState::before(BindingRow::unit()), PathMode::Walk),
        );
    }

    /**
     * @throws GqlException
     */
    public function testCrossOnceBindsTheNameToTheRelationItselfWhenNotRepeating(): void
    {
        $show = new NodeDatum('show');
        $total = new NodeDatum('total');
        $showCallsTotal = new EdgeDatum('show>total', ['call'], [], 'show', 'total');
        $graph = new ElementGraph(
            ['show' => $show, 'total' => $total],
            ['show' => [$showCallsTotal]],
            ['total' => [$showCallsTotal]],
        );
        $matching = new EdgeMatching($graph, new ExpressionEvaluation());

        $reached = $matching->crossOnce(
            new EdgePattern(EdgeDirection::Along, 'e'),
            MatchState::before(BindingRow::unit())->startingAt($show),
            PathMode::Walk,
            false,
        );

        self::assertEquals([new BindingRow(['e' => $showCallsTotal])], array_column($reached, 'row'));
    }

    /**
     * @throws GqlException
     */
    public function testCrossOnceAddsTheRelationToTheChainWhenRepeating(): void
    {
        $show = new NodeDatum('show');
        $total = new NodeDatum('total');
        $showCallsTotal = new EdgeDatum('show>total', ['call'], [], 'show', 'total');
        $graph = new ElementGraph(
            ['show' => $show, 'total' => $total],
            ['show' => [$showCallsTotal]],
            ['total' => [$showCallsTotal]],
        );
        $matching = new EdgeMatching($graph, new ExpressionEvaluation());

        $reached = $matching->crossOnce(
            new EdgePattern(EdgeDirection::Along, 'e'),
            MatchState::before(BindingRow::unit())->startingAt($show)->bind('e', new ListDatum([])),
            PathMode::Walk,
            true,
        );

        self::assertEquals([new BindingRow(['e' => new ListDatum([$showCallsTotal])])], array_column($reached, 'row'));
    }

    /**
     * @throws GqlException
     */
    public function testCrossOnceCrossesNothingFromAnAttemptStandingNowhere(): void
    {
        $matching = new EdgeMatching(new ElementGraph([], [], []), new ExpressionEvaluation());

        self::assertSame(
            [],
            $matching->crossOnce(new EdgePattern(EdgeDirection::Along), MatchState::before(BindingRow::unit()), PathMode::Walk, false),
        );
    }

    /**
     * @throws GqlException
     */
    public function testArrivalCrossesNothingLeadingWhereTheGraphKnowsOfNoSymbol(): void
    {
        $edge = new EdgeDatum('a>b', ['call'], [], 'a', 'b');
        $matching = new EdgeMatching(new ElementGraph([], [], []), new ExpressionEvaluation());
        $state = MatchState::before(BindingRow::unit())->startingAt(new NodeDatum('a'));

        self::assertNull($matching->arrival(new EdgePattern(EdgeDirection::Along), $state, PathMode::Walk, new EdgeTraversal($edge, 'b'), false));
    }

    /**
     * @throws GqlException
     */
    public function testArrivalCrossesARelationAgainWhereNothingIsForbidden(): void
    {
        $recursive = new NodeDatum('recursive');
        $callsItself = new EdgeDatum('recursive>recursive', ['call'], [], 'recursive', 'recursive');
        $graph = new ElementGraph(['recursive' => $recursive], ['recursive' => [$callsItself]], ['recursive' => [$callsItself]]);
        $matching = new EdgeMatching($graph, new ExpressionEvaluation());
        $state = MatchState::before(BindingRow::unit())->startingAt($recursive)->across($callsItself, $recursive);

        $arrived = $matching->arrival(new EdgePattern(EdgeDirection::Along), $state, PathMode::Walk, new EdgeTraversal($callsItself, 'recursive'), false);

        self::assertEquals(new PathDatum([$recursive, $callsItself, $recursive, $callsItself, $recursive]), $arrived?->path);
    }

    /**
     * @throws GqlException
     */
    public function testArrivalRefusesARelationAlreadyCrossedUnderTheModeThatForbidsIt(): void
    {
        $recursive = new NodeDatum('recursive');
        $callsItself = new EdgeDatum('recursive>recursive', ['call'], [], 'recursive', 'recursive');
        $graph = new ElementGraph(['recursive' => $recursive], ['recursive' => [$callsItself]], ['recursive' => [$callsItself]]);
        $matching = new EdgeMatching($graph, new ExpressionEvaluation());
        $state = MatchState::before(BindingRow::unit())->startingAt($recursive)->across($callsItself, $recursive);

        self::assertNull($matching->arrival(new EdgePattern(EdgeDirection::Along), $state, PathMode::Trail, new EdgeTraversal($callsItself, 'recursive'), false));
    }

    /**
     * @throws GqlException
     */
    public function testArrivalRefusesToMeetASymbolAgainUnderTheModeThatForbidsIt(): void
    {
        $recursive = new NodeDatum('recursive');
        $callsItself = new EdgeDatum('recursive>recursive', ['call'], [], 'recursive', 'recursive');
        $graph = new ElementGraph(['recursive' => $recursive], ['recursive' => [$callsItself]], ['recursive' => [$callsItself]]);
        $matching = new EdgeMatching($graph, new ExpressionEvaluation());
        $state = MatchState::before(BindingRow::unit())->startingAt($recursive);

        self::assertNull($matching->arrival(new EdgePattern(EdgeDirection::Along), $state, PathMode::Acyclic, new EdgeTraversal($callsItself, 'recursive'), false));
    }

    /**
     * @throws GqlException
     */
    public function testArrivalRefusesARelationOtherThanTheOneItsNameIsAlreadyBoundTo(): void
    {
        $show = new NodeDatum('show');
        $total = new NodeDatum('total');
        $showCallsTotal = new EdgeDatum('show>total', ['call'], [], 'show', 'total');
        $storeCallsTotal = new EdgeDatum('store>total', ['call'], [], 'store', 'total');
        $graph = new ElementGraph(['show' => $show, 'total' => $total], ['show' => [$showCallsTotal]], ['total' => [$showCallsTotal]]);
        $matching = new EdgeMatching($graph, new ExpressionEvaluation());
        $state = MatchState::before(new BindingRow(['e' => $storeCallsTotal]))->startingAt($show);

        self::assertNull($matching->arrival(new EdgePattern(EdgeDirection::Along, 'e'), $state, PathMode::Walk, new EdgeTraversal($showCallsTotal, 'total'), false));
    }

    public function testExtendedGrowsAChainByOneRelationAtATime(): void
    {
        $first = new EdgeDatum('a>b', ['call'], [], 'a', 'b');
        $second = new EdgeDatum('b>c', ['call'], [], 'b', 'c');

        self::assertEquals(new ListDatum([$first, $second]), EdgeMatching::extended(new ListDatum([$first]), $second));
    }

    public function testExtendedStartsAChainFromSomethingThatIsNotOne(): void
    {
        $edge = new EdgeDatum('a>b', ['call'], [], 'a', 'b');

        self::assertEquals(new ListDatum([$edge]), EdgeMatching::extended(new NullDatum(), $edge));
    }
}
