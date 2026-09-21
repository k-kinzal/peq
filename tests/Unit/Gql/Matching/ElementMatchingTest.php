<?php

declare(strict_types=1);

namespace Tests\Unit\Gql\Matching;

use App\Gql\Binding\BindingRow;
use App\Gql\Datum\BooleanDatum;
use App\Gql\Datum\DatumKind;
use App\Gql\Datum\DatumOrder;
use App\Gql\Datum\EdgeDatum;
use App\Gql\Datum\IntegerDatum;
use App\Gql\Datum\NodeDatum;
use App\Gql\Datum\NullDatum;
use App\Gql\Datum\StringDatum;
use App\Gql\Evaluation\BinaryOperation;
use App\Gql\Evaluation\Comparison;
use App\Gql\Evaluation\ExpressionEvaluation;
use App\Gql\Evaluation\Logic;
use App\Gql\GqlException;
use App\Gql\Matching\ElementMatching;
use App\Gql\StatusCode;
use App\Gql\Syntax\Expression\BinaryExpression;
use App\Gql\Syntax\Expression\BinaryOperator;
use App\Gql\Syntax\Expression\LiteralExpression;
use App\Gql\Syntax\Expression\PropertyExpression;
use App\Gql\Syntax\Expression\VariableExpression;
use App\Gql\Syntax\Pattern\ElementFilter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(ElementMatching::class)]
#[UsesClass(BindingRow::class)]
#[UsesClass(BooleanDatum::class)]
#[UsesClass(DatumKind::class)]
#[UsesClass(DatumOrder::class)]
#[UsesClass(EdgeDatum::class)]
#[UsesClass(IntegerDatum::class)]
#[UsesClass(NodeDatum::class)]
#[UsesClass(NullDatum::class)]
#[UsesClass(StringDatum::class)]
#[UsesClass(BinaryOperation::class)]
#[UsesClass(Comparison::class)]
#[UsesClass(ExpressionEvaluation::class)]
#[UsesClass(Logic::class)]
#[UsesClass(GqlException::class)]
#[UsesClass(StatusCode::class)]
#[UsesClass(BinaryExpression::class)]
#[UsesClass(BinaryOperator::class)]
#[UsesClass(LiteralExpression::class)]
#[UsesClass(PropertyExpression::class)]
#[UsesClass(VariableExpression::class)]
#[UsesClass(ElementFilter::class)]
#[Small]
final class ElementMatchingTest extends TestCase
{
    /**
     * @throws GqlException
     */
    public function testSatisfiesAcceptsASymbolCarryingThePropertyAPatternAskedFor(): void
    {
        $get = new NodeDatum('get', ['Method'], ['visibility' => new StringDatum('protected')]);
        $filter = new ElementFilter(['visibility' => new LiteralExpression(new StringDatum('protected'))]);

        self::assertTrue(ElementMatching::satisfies($get, 'p', $filter, BindingRow::unit(), new ExpressionEvaluation()));
    }

    /**
     * @throws GqlException
     */
    public function testSatisfiesRefusesASymbolCarryingADifferentValueForIt(): void
    {
        $get = new NodeDatum('get', ['Method'], ['visibility' => new StringDatum('protected')]);
        $filter = new ElementFilter(['visibility' => new LiteralExpression(new StringDatum('private'))]);

        self::assertFalse(ElementMatching::satisfies($get, 'p', $filter, BindingRow::unit(), new ExpressionEvaluation()));
    }

    /**
     * @throws GqlException
     */
    public function testSatisfiesRefusesASymbolThatDoesNotCarryThePropertyAtAll(): void
    {
        $missing = new NodeDatum('missing', ['Unknown']);
        $filter = new ElementFilter(['visibility' => new LiteralExpression(new StringDatum('protected'))]);

        self::assertFalse(ElementMatching::satisfies($missing, 'p', $filter, BindingRow::unit(), new ExpressionEvaluation()));
    }

    /**
     * @throws GqlException
     */
    public function testSatisfiesAcceptsASymbolThePredicateHoldsOf(): void
    {
        $store = new NodeDatum('store', ['Method'], ['line' => new IntegerDatum(30)]);
        $laterThanLineTwenty = new BinaryExpression(
            BinaryOperator::Greater,
            new PropertyExpression(new VariableExpression('p'), 'line'),
            new LiteralExpression(new IntegerDatum(20)),
        );

        self::assertTrue(ElementMatching::satisfies($store, 'p', new ElementFilter([], $laterThanLineTwenty), BindingRow::unit(), new ExpressionEvaluation()));
    }

    /**
     * @throws GqlException
     */
    public function testSatisfiesRefusesASymbolThePredicateIsFalseOf(): void
    {
        $get = new NodeDatum('get', ['Method'], ['line' => new IntegerDatum(8)]);
        $laterThanLineTwenty = new BinaryExpression(
            BinaryOperator::Greater,
            new PropertyExpression(new VariableExpression('p'), 'line'),
            new LiteralExpression(new IntegerDatum(20)),
        );

        self::assertFalse(ElementMatching::satisfies($get, 'p', new ElementFilter([], $laterThanLineTwenty), BindingRow::unit(), new ExpressionEvaluation()));
    }

    /**
     * @throws GqlException
     */
    public function testSatisfiesRefusesASymbolThePredicateCannotBeDecidedFor(): void
    {
        $missing = new NodeDatum('missing', ['Unknown']);
        $laterThanLineZero = new BinaryExpression(
            BinaryOperator::Greater,
            new PropertyExpression(new VariableExpression('p'), 'line'),
            new LiteralExpression(new IntegerDatum(0)),
        );

        self::assertFalse(ElementMatching::satisfies($missing, 'p', new ElementFilter([], $laterThanLineZero), BindingRow::unit(), new ExpressionEvaluation()));
    }

    /**
     * @throws GqlException
     */
    public function testSatisfiesLetsThePredicateReadWhatIsAlreadyBound(): void
    {
        $store = new NodeDatum('store', ['Method'], ['line' => new IntegerDatum(30)]);
        $laterThanShow = new BinaryExpression(
            BinaryOperator::Greater,
            new PropertyExpression(new VariableExpression('p'), 'line'),
            new PropertyExpression(new VariableExpression('show'), 'line'),
        );
        $row = new BindingRow(['show' => new NodeDatum('show', ['Method'], ['line' => new IntegerDatum(20)])]);

        self::assertTrue(ElementMatching::satisfies($store, 'p', new ElementFilter([], $laterThanShow), $row, new ExpressionEvaluation()));
    }

    /**
     * @throws GqlException
     */
    public function testSatisfiesAcceptsAnyElementWhenThePatternRequiresNothingBeyondItsLabels(): void
    {
        self::assertTrue(ElementMatching::satisfies(new NodeDatum('missing'), 'p', new ElementFilter(), BindingRow::unit(), new ExpressionEvaluation()));
    }

    /**
     * @throws GqlException
     */
    public function testSatisfiesNarrowsARelationByThePredicateWrittenOnIt(): void
    {
        $call = new EdgeDatum('total>get', ['call'], ['line' => new IntegerDatum(14)], 'total', 'get');
        $beforeLineTwenty = new BinaryExpression(
            BinaryOperator::Less,
            new PropertyExpression(new VariableExpression('e'), 'line'),
            new LiteralExpression(new IntegerDatum(20)),
        );

        self::assertTrue(ElementMatching::satisfies($call, 'e', new ElementFilter([], $beforeLineTwenty), BindingRow::unit(), new ExpressionEvaluation()));
    }

    /**
     * @throws GqlException
     */
    public function testSatisfiesReportsAPredicateComparingValuesOfUnrelatedKinds(): void
    {
        $store = new NodeDatum('store', ['Method'], ['line' => new IntegerDatum(30)]);
        $laterThanAWord = new BinaryExpression(
            BinaryOperator::Greater,
            new PropertyExpression(new VariableExpression('p'), 'line'),
            new LiteralExpression(new StringDatum('twenty')),
        );

        $this->expectException(GqlException::class);
        $this->expectExceptionMessage('[22G04] error: data exception - values not comparable: INT64 and STRING cannot be compared');

        ElementMatching::satisfies($store, 'p', new ElementFilter([], $laterThanAWord), BindingRow::unit(), new ExpressionEvaluation());
    }

    /**
     * @throws GqlException
     */
    public function testAgreesAcceptsAnythingForANameNothingBound(): void
    {
        self::assertTrue(ElementMatching::agrees('p', new NodeDatum('a'), BindingRow::unit()));
    }

    /**
     * @throws GqlException
     */
    public function testAgreesAcceptsAnythingWhenThePatternNamesNothing(): void
    {
        self::assertTrue(ElementMatching::agrees(null, new NodeDatum('a'), new BindingRow(['p' => new NodeDatum('b')])));
    }

    /**
     * @throws GqlException
     */
    public function testAgreesAcceptsTheElementTheNameIsBoundTo(): void
    {
        self::assertTrue(ElementMatching::agrees('p', new NodeDatum('a'), new BindingRow(['p' => new NodeDatum('a')])));
    }

    /**
     * @throws GqlException
     */
    public function testAgreesRefusesAnElementThatIsNotWhatTheNameIsBoundTo(): void
    {
        self::assertFalse(ElementMatching::agrees('p', new NodeDatum('b'), new BindingRow(['p' => new NodeDatum('a')])));
    }

    /**
     * @throws GqlException
     */
    public function testAgreesRefusesAnElementWhereTheNameIsBoundToNothing(): void
    {
        self::assertFalse(ElementMatching::agrees('p', new NodeDatum('a'), new BindingRow(['p' => new NullDatum()])));
    }

    /**
     * @throws GqlException
     */
    public function testAgreesReportsANameBoundToAValueThatCannotBeComparedWithAnElement(): void
    {
        $this->expectException(GqlException::class);
        $this->expectExceptionMessage('[22G04] error: data exception - values not comparable: INT64 and NODE cannot be compared');

        ElementMatching::agrees('p', new NodeDatum('a'), new BindingRow(['p' => new IntegerDatum(1)]));
    }
}
