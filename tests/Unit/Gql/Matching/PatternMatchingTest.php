<?php

declare(strict_types=1);

namespace Tests\Unit\Gql\Matching;

use App\Gql\Binding\BindingRow;
use App\Gql\Datum\DatumKind;
use App\Gql\Datum\DatumOrder;
use App\Gql\Datum\EdgeDatum;
use App\Gql\Datum\IntegerDatum;
use App\Gql\Datum\NodeDatum;
use App\Gql\Datum\PathDatum;
use App\Gql\Element\ElementGraph;
use App\Gql\Evaluation\ExpressionEvaluation;
use App\Gql\GqlException;
use App\Gql\Matching\EdgeMatching;
use App\Gql\Matching\EdgeTraversal;
use App\Gql\Matching\ElementMatching;
use App\Gql\Matching\LabelMatching;
use App\Gql\Matching\MatchState;
use App\Gql\Matching\PathMatching;
use App\Gql\Matching\PathModeRule;
use App\Gql\Matching\PatternMatching;
use App\Gql\Syntax\Pattern\EdgeDirection;
use App\Gql\Syntax\Pattern\EdgePattern;
use App\Gql\Syntax\Pattern\ElementFilter;
use App\Gql\Syntax\Pattern\GraphPattern;
use App\Gql\Syntax\Pattern\LabelOperator;
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
#[CoversClass(PatternMatching::class)]
#[UsesClass(BindingRow::class)]
#[UsesClass(DatumKind::class)]
#[UsesClass(DatumOrder::class)]
#[UsesClass(EdgeDatum::class)]
#[UsesClass(IntegerDatum::class)]
#[UsesClass(NodeDatum::class)]
#[UsesClass(PathDatum::class)]
#[UsesClass(ElementGraph::class)]
#[UsesClass(ExpressionEvaluation::class)]
#[UsesClass(EdgeMatching::class)]
#[UsesClass(EdgeTraversal::class)]
#[UsesClass(ElementMatching::class)]
#[UsesClass(LabelMatching::class)]
#[UsesClass(MatchState::class)]
#[UsesClass(PathMatching::class)]
#[UsesClass(PathModeRule::class)]
#[UsesClass(EdgeDirection::class)]
#[UsesClass(EdgePattern::class)]
#[UsesClass(ElementFilter::class)]
#[UsesClass(GraphPattern::class)]
#[UsesClass(LabelOperator::class)]
#[UsesClass(LabelPattern::class)]
#[UsesClass(NodePattern::class)]
#[UsesClass(PathMode::class)]
#[UsesClass(PathPattern::class)]
#[Small]
final class PatternMatchingTest extends TestCase
{
    /**
     * @throws GqlException
     */
    public function testMatchMatchesEachPathAgainstWhatTheOnesBeforeItBound(): void
    {
        $controller = new NodeDatum('controller', ['Class']);
        $kernel = new NodeDatum('kernel', ['Class']);
        $show = new NodeDatum('show', ['Method']);
        $store = new NodeDatum('store', ['Method']);
        $extends = new EdgeDatum('controller>kernel', ['extends'], [], 'controller', 'kernel');
        $declaresShow = new EdgeDatum('controller>show', ['declaresMethod'], [], 'controller', 'show');
        $declaresStore = new EdgeDatum('controller>store', ['declaresMethod'], [], 'controller', 'store');
        $graph = new ElementGraph(
            ['controller' => $controller, 'kernel' => $kernel, 'show' => $show, 'store' => $store],
            ['controller' => [$extends, $declaresShow, $declaresStore]],
            ['kernel' => [$extends], 'show' => [$declaresShow], 'store' => [$declaresStore]],
        );
        $pattern = new GraphPattern([
            new PathPattern([new NodePattern('c'), new EdgePattern(EdgeDirection::Along, null, LabelPattern::named('declaresMethod')), new NodePattern('m')], PathMode::Walk),
            new PathPattern([new NodePattern('c'), new EdgePattern(EdgeDirection::Along, null, LabelPattern::named('extends')), new NodePattern('q')], PathMode::Walk),
        ]);

        self::assertEquals(
            [
                new BindingRow(['c' => $controller, 'm' => $show, 'q' => $kernel]),
                new BindingRow(['c' => $controller, 'm' => $store, 'q' => $kernel]),
            ],
            (new PatternMatching($graph, new ExpressionEvaluation()))->match($pattern, BindingRow::unit()),
        );
    }

    /**
     * @throws GqlException
     */
    public function testMatchMatchesNothingWhenOneOfThePathsMatchesNothing(): void
    {
        $controller = new NodeDatum('controller', ['Class']);
        $show = new NodeDatum('show', ['Method']);
        $declaresShow = new EdgeDatum('controller>show', ['declaresMethod'], [], 'controller', 'show');
        $graph = new ElementGraph(
            ['controller' => $controller, 'show' => $show],
            ['controller' => [$declaresShow]],
            ['show' => [$declaresShow]],
        );
        $pattern = new GraphPattern([
            new PathPattern([new NodePattern('c'), new EdgePattern(EdgeDirection::Along, null, LabelPattern::named('declaresMethod')), new NodePattern('m')], PathMode::Walk),
            new PathPattern([new NodePattern('c'), new EdgePattern(EdgeDirection::Along, null, LabelPattern::named('implements')), new NodePattern('q')], PathMode::Walk),
        ]);

        self::assertSame([], (new PatternMatching($graph, new ExpressionEvaluation()))->match($pattern, BindingRow::unit()));
    }

    /**
     * @throws GqlException
     */
    public function testMatchKeepsWhatWasBoundBeforeThePatternWasReached(): void
    {
        $missing = new NodeDatum('missing', ['Unknown', 'Unresolved']);
        $graph = new ElementGraph(['missing' => $missing], [], []);
        $pattern = new GraphPattern([new PathPattern([new NodePattern('p', LabelPattern::named('Unresolved'))], PathMode::Walk)]);

        self::assertEquals(
            [new BindingRow(['n' => new IntegerDatum(1), 'p' => $missing])],
            (new PatternMatching($graph, new ExpressionEvaluation()))->match($pattern, new BindingRow(['n' => new IntegerDatum(1)])),
        );
    }

    /**
     * @throws GqlException
     */
    public function testMatchMatchesNothingOverAGraphWithNoSymbols(): void
    {
        $matching = new PatternMatching(new ElementGraph([], [], []), new ExpressionEvaluation());
        $pattern = new GraphPattern([new PathPattern([new NodePattern('p')], PathMode::Walk)]);

        self::assertSame([], $matching->match($pattern, BindingRow::unit()));
    }
}
