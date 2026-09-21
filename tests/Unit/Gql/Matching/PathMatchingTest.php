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
use App\Gql\Datum\PathDatum;
use App\Gql\Datum\StringDatum;
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
use App\Gql\Matching\PathMatching;
use App\Gql\Matching\PathModeRule;
use App\Gql\Matching\PatternVariables;
use App\Gql\Syntax\Expression\LiteralExpression;
use App\Gql\Syntax\Pattern\EdgeDirection;
use App\Gql\Syntax\Pattern\EdgePattern;
use App\Gql\Syntax\Pattern\ElementFilter;
use App\Gql\Syntax\Pattern\GroupPattern;
use App\Gql\Syntax\Pattern\LabelOperator;
use App\Gql\Syntax\Pattern\LabelPattern;
use App\Gql\Syntax\Pattern\NodePattern;
use App\Gql\Syntax\Pattern\PathMode;
use App\Gql\Syntax\Pattern\PathPattern;
use App\Gql\Syntax\Pattern\Quantifier;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(PathMatching::class)]
#[UsesClass(BindingRow::class)]
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
#[UsesClass(PathModeRule::class)]
#[UsesClass(LiteralExpression::class)]
#[UsesClass(EdgeDirection::class)]
#[UsesClass(EdgePattern::class)]
#[UsesClass(ElementFilter::class)]
#[UsesClass(GroupPattern::class)]
#[UsesClass(LabelOperator::class)]
#[UsesClass(LabelPattern::class)]
#[UsesClass(NodePattern::class)]
#[UsesClass(PathMode::class)]
#[UsesClass(PathPattern::class)]
#[UsesClass(Quantifier::class)]
#[UsesClass(ListDatum::class)]
#[UsesClass(PatternVariables::class)]
#[Small]
final class PathMatchingTest extends TestCase
{
    /**
     * @throws GqlException
     */
    public function testMatchBindsEveryNameThePathWritesOncePerWayItMatched(): void
    {
        $show = new NodeDatum('show', ['Method']);
        $store = new NodeDatum('store', ['Method']);
        $total = new NodeDatum('total', ['Method']);
        $showCallsTotal = new EdgeDatum('show>total', ['call'], [], 'show', 'total');
        $storeCallsTotal = new EdgeDatum('store>total', ['call'], [], 'store', 'total');
        $graph = new ElementGraph(
            ['show' => $show, 'store' => $store, 'total' => $total],
            ['show' => [$showCallsTotal], 'store' => [$storeCallsTotal]],
            ['total' => [$showCallsTotal, $storeCallsTotal]],
        );
        $path = new PathPattern([new NodePattern('a'), new EdgePattern(EdgeDirection::Along, 'e'), new NodePattern('b')], PathMode::Walk);

        self::assertEquals(
            [
                new BindingRow(['a' => $show, 'e' => $showCallsTotal, 'b' => $total]),
                new BindingRow(['a' => $store, 'e' => $storeCallsTotal, 'b' => $total]),
            ],
            (new PathMatching($graph, new ExpressionEvaluation()))->match($path, BindingRow::unit()),
        );
    }

    /**
     * @throws GqlException
     */
    public function testMatchBindsThePathUnderTheNameItWasGiven(): void
    {
        $total = new NodeDatum('total', ['Method'], ['name' => new StringDatum('total')]);
        $get = new NodeDatum('get', ['Method'], ['name' => new StringDatum('get')]);
        $totalCallsGet = new EdgeDatum('total>get', ['call'], [], 'total', 'get');
        $graph = new ElementGraph(['total' => $total, 'get' => $get], ['total' => [$totalCallsGet]], ['get' => [$totalCallsGet]]);
        $path = new PathPattern(
            [
                new NodePattern('a', null, new ElementFilter(['name' => new LiteralExpression(new StringDatum('total'))])),
                new EdgePattern(EdgeDirection::Along),
                new NodePattern('b'),
            ],
            PathMode::Walk,
            'p',
        );

        self::assertEquals(
            [new BindingRow(['a' => $total, 'b' => $get, 'p' => new PathDatum([$total, $totalCallsGet, $get])])],
            (new PathMatching($graph, new ExpressionEvaluation()))->match($path, BindingRow::unit()),
        );
    }

    /**
     * @throws GqlException
     */
    public function testMatchKeepsWhatWasBoundBeforeThePathWasReached(): void
    {
        $missing = new NodeDatum('missing', ['Unknown']);
        $graph = new ElementGraph(['missing' => $missing], [], []);
        $path = new PathPattern([new NodePattern('p')], PathMode::Walk);

        self::assertEquals(
            [new BindingRow(['n' => new IntegerDatum(1), 'p' => $missing])],
            (new PathMatching($graph, new ExpressionEvaluation()))->match($path, new BindingRow(['n' => new IntegerDatum(1)])),
        );
    }

    /**
     * @throws GqlException
     */
    public function testMatchBindsNothingWhenThePathMatchesNothing(): void
    {
        $graph = new ElementGraph(['controller' => new NodeDatum('controller', ['Class', 'ClassLike'])], [], []);
        $path = new PathPattern([new NodePattern('p', LabelPattern::named('Interface'))], PathMode::Walk);

        self::assertSame([], (new PathMatching($graph, new ExpressionEvaluation()))->match($path, BindingRow::unit()));
    }

    /**
     * @param list<BindingRow> $expected
     *
     * @throws GqlException
     */
    #[DataProvider('providerModesAndWhereTheyLetARingOfCallsBeFollowed')]
    public function testMatchFollowsARingOfCallsOnlyAsFarAsThePathModeAllows(PathMode $mode, array $expected): void
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
        $path = new PathPattern(
            [
                new NodePattern('a'),
                new EdgePattern(EdgeDirection::Along, null, null, new ElementFilter(), new Quantifier(1, null)),
                new NodePattern('b'),
            ],
            $mode,
        );

        self::assertEquals($expected, (new PathMatching($graph, new ExpressionEvaluation()))->match($path, new BindingRow(['a' => $first])));
    }

    /**
     * @return iterable<string, array{PathMode, list<BindingRow>}>
     */
    public static function providerModesAndWhereTheyLetARingOfCallsBeFollowed(): iterable
    {
        yield 'crossing no call twice, which still meets a method twice' => [
            PathMode::Trail,
            [
                new BindingRow(['a' => new NodeDatum('first'), 'b' => new NodeDatum('second')]),
                new BindingRow(['a' => new NodeDatum('first'), 'b' => new NodeDatum('third')]),
                new BindingRow(['a' => new NodeDatum('first'), 'b' => new NodeDatum('first')]),
                new BindingRow(['a' => new NodeDatum('first'), 'b' => new NodeDatum('second')]),
            ],
        ];

        yield 'meeting no method twice, except by coming back to the first' => [
            PathMode::Simple,
            [
                new BindingRow(['a' => new NodeDatum('first'), 'b' => new NodeDatum('second')]),
                new BindingRow(['a' => new NodeDatum('first'), 'b' => new NodeDatum('third')]),
                new BindingRow(['a' => new NodeDatum('first'), 'b' => new NodeDatum('first')]),
            ],
        ];

        yield 'meeting no method twice at all' => [
            PathMode::Acyclic,
            [
                new BindingRow(['a' => new NodeDatum('first'), 'b' => new NodeDatum('second')]),
                new BindingRow(['a' => new NodeDatum('first'), 'b' => new NodeDatum('third')]),
            ],
        ];
    }

    /**
     * @throws GqlException
     */
    public function testMatchTermsHasMatchedWhenThereIsNothingLeftToMatch(): void
    {
        $matching = new PathMatching(new ElementGraph([], [], []), new ExpressionEvaluation());
        $state = MatchState::before(BindingRow::unit());

        self::assertSame([$state], $matching->matchTerms([], 0, $state, PathMode::Walk));
    }

    /**
     * @throws GqlException
     */
    public function testMatchTermsMatchesThePiecesAfterTheOneItIsToldToStartFrom(): void
    {
        $show = new NodeDatum('show');
        $total = new NodeDatum('total');
        $showCallsTotal = new EdgeDatum('show>total', ['call'], [], 'show', 'total');
        $graph = new ElementGraph(['show' => $show, 'total' => $total], ['show' => [$showCallsTotal]], ['total' => [$showCallsTotal]]);
        $matching = new PathMatching($graph, new ExpressionEvaluation());
        $terms = [new NodePattern('a'), new EdgePattern(EdgeDirection::Along), new NodePattern('b')];

        $matched = $matching->matchTerms($terms, 1, MatchState::before(BindingRow::unit())->startingAt($show), PathMode::Walk);

        self::assertEquals([new BindingRow(['b' => $total])], array_column($matched, 'row'));
    }

    /**
     * @throws GqlException
     */
    public function testMatchStandingMatchesNothingForAPieceThatIsNeitherASymbolNorAGroup(): void
    {
        $matching = new PathMatching(new ElementGraph([], [], []), new ExpressionEvaluation());
        $state = MatchState::before(BindingRow::unit());

        self::assertSame([], $matching->matchStanding(new EdgePattern(EdgeDirection::Along), $state, PathMode::Walk));
    }

    /**
     * @throws GqlException
     */
    public function testMatchStandingMatchesASymbolPatternAtTheSymbolItStandsAt(): void
    {
        $show = new NodeDatum('show');
        $matching = new PathMatching(new ElementGraph(['show' => $show], [], []), new ExpressionEvaluation());
        $state = MatchState::before(BindingRow::unit())->startingAt($show);

        $matched = $matching->matchStanding(new NodePattern('p'), $state, PathMode::Walk);

        self::assertEquals([new BindingRow(['p' => $show])], array_column($matched, 'row'));
    }

    /**
     * @throws GqlException
     */
    public function testMatchNodeConsidersEverySymbolWhenTheAttemptHasNotStarted(): void
    {
        $controller = new NodeDatum('controller', ['Class', 'ClassLike']);
        $show = new NodeDatum('show', ['Method', 'Member', 'Callable']);
        $invoice = new NodeDatum('invoice', ['Class', 'ClassLike']);
        $graph = new ElementGraph(['controller' => $controller, 'show' => $show, 'invoice' => $invoice], [], []);
        $matching = new PathMatching($graph, new ExpressionEvaluation());

        $matched = $matching->matchNode(new NodePattern('p', LabelPattern::named('ClassLike')), MatchState::before(BindingRow::unit()), PathMode::Walk);

        self::assertEquals([new BindingRow(['p' => $controller]), new BindingRow(['p' => $invoice])], array_column($matched, 'row'));
    }

    /**
     * @throws GqlException
     */
    public function testMatchNodeStartsAPathAtEachSymbolItMatches(): void
    {
        $controller = new NodeDatum('controller', ['Class', 'ClassLike']);
        $matching = new PathMatching(new ElementGraph(['controller' => $controller], [], []), new ExpressionEvaluation());

        $matched = $matching->matchNode(new NodePattern(), MatchState::before(BindingRow::unit()), PathMode::Walk);

        self::assertEquals([new PathDatum([$controller])], array_column($matched, 'path'));
    }

    /**
     * @throws GqlException
     */
    public function testMatchNodeRefusesASymbolThatDoesNotAgreeWithWhatItsNameIsBoundTo(): void
    {
        $show = new NodeDatum('show');
        $matching = new PathMatching(new ElementGraph(['show' => $show], [], []), new ExpressionEvaluation());
        $state = MatchState::before(new BindingRow(['p' => new NodeDatum('store')]))->startingAt($show);

        self::assertSame([], $matching->matchNode(new NodePattern('p'), $state, PathMode::Walk));
    }

    /**
     * @throws GqlException
     */
    public function testMatchNodeMatchesNothingOverAGraphWithNoSymbols(): void
    {
        $matching = new PathMatching(new ElementGraph([], [], []), new ExpressionEvaluation());

        self::assertSame([], $matching->matchNode(new NodePattern('p'), MatchState::before(BindingRow::unit()), PathMode::Walk));
    }

    public function testCandidatesConsidersOnlyWhereTheAttemptArrived(): void
    {
        $show = new NodeDatum('show');
        $matching = new PathMatching(new ElementGraph(['show' => $show, 'store' => new NodeDatum('store')], [], []), new ExpressionEvaluation());
        $state = MatchState::before(BindingRow::unit())->startingAt($show);

        self::assertSame([$show], $matching->candidates(new NodePattern('p'), $state));
    }

    public function testCandidatesConsidersOnlyWhatAnEarlierClauseBoundTheNameTo(): void
    {
        $invoice = new NodeDatum('invoice');
        $matching = new PathMatching(new ElementGraph(['controller' => new NodeDatum('controller'), 'invoice' => $invoice], [], []), new ExpressionEvaluation());
        $state = MatchState::before(new BindingRow(['p' => $invoice]));

        self::assertSame([$invoice], $matching->candidates(new NodePattern('p'), $state));
    }

    public function testCandidatesConsidersNothingWhenTheNameIsBoundToSomethingThatIsNotASymbol(): void
    {
        $matching = new PathMatching(new ElementGraph(['controller' => new NodeDatum('controller')], [], []), new ExpressionEvaluation());
        $state = MatchState::before(new BindingRow(['p' => new IntegerDatum(1)]));

        self::assertSame([], $matching->candidates(new NodePattern('p'), $state));
    }

    public function testCandidatesConsidersEverySymbolForANameNothingBound(): void
    {
        $controller = new NodeDatum('controller');
        $invoice = new NodeDatum('invoice');
        $matching = new PathMatching(new ElementGraph(['controller' => $controller, 'invoice' => $invoice], [], []), new ExpressionEvaluation());

        self::assertSame([$controller, $invoice], $matching->candidates(new NodePattern('p'), MatchState::before(BindingRow::unit())));
    }

    /**
     * @throws GqlException
     */
    public function testMatchGroupCarriesEachRepetitionOnFromWhereTheLastArrived(): void
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
        $matching = new PathMatching($graph, new ExpressionEvaluation());
        $group = new GroupPattern([new NodePattern(), new EdgePattern(EdgeDirection::Along), new NodePattern()], new Quantifier(1, 2));

        $matched = $matching->matchGroup($group, MatchState::before(BindingRow::unit())->startingAt($show), PathMode::Walk);

        self::assertEquals(
            [
                new PathDatum([$show, $showCallsTotal, $total]),
                new PathDatum([$show, $showCallsTotal, $total, $totalCallsGet, $get]),
            ],
            array_column($matched, 'path'),
        );
    }

    /**
     * @throws GqlException
     */
    public function testMatchGroupMatchesOnceWhenNoRepetitionIsWritten(): void
    {
        $show = new NodeDatum('show');
        $total = new NodeDatum('total');
        $showCallsTotal = new EdgeDatum('show>total', ['call'], [], 'show', 'total');
        $graph = new ElementGraph(['show' => $show, 'total' => $total], ['show' => [$showCallsTotal]], ['total' => [$showCallsTotal]]);
        $matching = new PathMatching($graph, new ExpressionEvaluation());
        $group = new GroupPattern([new NodePattern('a'), new EdgePattern(EdgeDirection::Along), new NodePattern('b')]);

        $matched = $matching->matchGroup($group, MatchState::before(BindingRow::unit()), PathMode::Walk);

        self::assertEquals([new BindingRow(['a' => $show, 'b' => $total])], array_column($matched, 'row'));
    }

    /**
     * @throws GqlException
     */
    public function testMatchGroupMatchesByNotRepeatingWhenThatIsAllItCanDo(): void
    {
        $matching = new PathMatching(new ElementGraph([], [], []), new ExpressionEvaluation());
        $group = new GroupPattern([new NodePattern()], new Quantifier(0, 2));
        $state = MatchState::before(BindingRow::unit());

        self::assertSame([$state], $matching->matchGroup($group, $state, PathMode::Walk));
    }

    /**
     * @throws GqlException
     */
    public function testMatchGroupBindsANameInARepeatedGroupToWhatEachRepetitionBound(): void
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
        $group = new GroupPattern([new NodePattern('a'), new EdgePattern(EdgeDirection::Along), new NodePattern('b')], Quantifier::exactly(2));

        $matched = (new PathMatching($graph, new ExpressionEvaluation()))->matchGroup($group, MatchState::before(BindingRow::unit())->startingAt($show), PathMode::Walk);

        self::assertEquals(
            [new BindingRow(['a' => new ListDatum([$show, $total]), 'b' => new ListDatum([$total, $get])])],
            array_column($matched, 'row'),
        );
    }

    /**
     * @throws GqlException
     */
    public function testRepeatAddsWhatOneRepetitionBoundToTheListTheNameIsBoundTo(): void
    {
        $show = new NodeDatum('show');
        $matching = new PathMatching(new ElementGraph(['show' => $show], [], []), new ExpressionEvaluation());
        $group = new GroupPattern([new NodePattern('n')], Quantifier::exactly(1));
        $standing = MatchState::before(new BindingRow(['n' => new ListDatum([])]));

        self::assertEquals(
            [new BindingRow(['n' => new ListDatum([$show])])],
            array_column($matching->repeat($group, ['n'], $standing, PathMode::Walk), 'row'),
        );
    }

    /**
     * @throws GqlException
     */
    public function testRepeatBindsTheNameAfreshRatherThanAskingItToRecur(): void
    {
        $show = new NodeDatum('show');
        $matching = new PathMatching(new ElementGraph(['show' => $show], [], []), new ExpressionEvaluation());
        $group = new GroupPattern([new NodePattern('n')], Quantifier::exactly(1));
        $standing = MatchState::before(new BindingRow(['n' => new ListDatum([new NodeDatum('elsewhere')])]));

        self::assertEquals(
            [new BindingRow(['n' => new ListDatum([new NodeDatum('elsewhere'), $show])])],
            array_column($matching->repeat($group, ['n'], $standing, PathMode::Walk), 'row'),
        );
    }

    public function testCollectedAddsOneElementToTheList(): void
    {
        self::assertEquals(
            new ListDatum([new NodeDatum('a'), new NodeDatum('b')]),
            PathMatching::collected(new ListDatum([new NodeDatum('a')]), new NodeDatum('b')),
        );
    }

    public function testCollectedAddsAListARepeatedRelationBoundElementByElement(): void
    {
        $edge = new EdgeDatum('e', [], [], 'a', 'b');

        self::assertEquals(new ListDatum([$edge]), PathMatching::collected(new ListDatum([]), new ListDatum([$edge])));
    }
}
