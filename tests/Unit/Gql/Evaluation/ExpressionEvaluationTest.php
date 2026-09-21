<?php

declare(strict_types=1);

namespace Tests\Unit\Gql\Evaluation;

use App\Gql\Argument\ExactArithmetic;
use App\Gql\Argument\NumberArgument;
use App\Gql\Argument\TextArgument;
use App\Gql\Binding\BindingRow;
use App\Gql\Datum\BooleanDatum;
use App\Gql\Datum\Datum;
use App\Gql\Datum\DatumIdentity;
use App\Gql\Datum\DatumKind;
use App\Gql\Datum\DatumOrder;
use App\Gql\Datum\DecimalDatum;
use App\Gql\Datum\IntegerDatum;
use App\Gql\Datum\ListDatum;
use App\Gql\Datum\NodeDatum;
use App\Gql\Datum\NullDatum;
use App\Gql\Datum\StringDatum;
use App\Gql\Evaluation\Arithmetic;
use App\Gql\Evaluation\BinaryOperation;
use App\Gql\Evaluation\Comparison;
use App\Gql\Evaluation\ExpressionEvaluation;
use App\Gql\Evaluation\Logic;
use App\Gql\Evaluation\UnaryOperation;
use App\Gql\GqlException;
use App\Gql\Invocation\AggregateCatalog;
use App\Gql\Invocation\CountAccumulator;
use App\Gql\Invocation\DistinctAccumulator;
use App\Gql\Invocation\ExtremeAccumulator;
use App\Gql\Invocation\FunctionCatalog;
use App\Gql\Invocation\SumAccumulator;
use App\Gql\Invocation\TextFunctions;
use App\Gql\StatusCode;
use App\Gql\Syntax\Expression;
use App\Gql\Syntax\Expression\BinaryExpression;
use App\Gql\Syntax\Expression\BinaryOperator;
use App\Gql\Syntax\Expression\CallExpression;
use App\Gql\Syntax\Expression\CaseBranch;
use App\Gql\Syntax\Expression\CaseExpression;
use App\Gql\Syntax\Expression\ListExpression;
use App\Gql\Syntax\Expression\LiteralExpression;
use App\Gql\Syntax\Expression\PropertyExpression;
use App\Gql\Syntax\Expression\UnaryExpression;
use App\Gql\Syntax\Expression\UnaryOperator;
use App\Gql\Syntax\Expression\VariableExpression;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(ExpressionEvaluation::class)]
#[UsesClass(AggregateCatalog::class)]
#[UsesClass(Arithmetic::class)]
#[UsesClass(BinaryExpression::class)]
#[UsesClass(BinaryOperation::class)]
#[UsesClass(BindingRow::class)]
#[UsesClass(BooleanDatum::class)]
#[UsesClass(CallExpression::class)]
#[UsesClass(CaseBranch::class)]
#[UsesClass(CaseExpression::class)]
#[UsesClass(Comparison::class)]
#[UsesClass(CountAccumulator::class)]
#[UsesClass(DatumIdentity::class)]
#[UsesClass(DatumKind::class)]
#[UsesClass(DatumOrder::class)]
#[UsesClass(DecimalDatum::class)]
#[UsesClass(DistinctAccumulator::class)]
#[UsesClass(ExactArithmetic::class)]
#[UsesClass(ExtremeAccumulator::class)]
#[UsesClass(FunctionCatalog::class)]
#[UsesClass(GqlException::class)]
#[UsesClass(IntegerDatum::class)]
#[UsesClass(ListDatum::class)]
#[UsesClass(ListExpression::class)]
#[UsesClass(LiteralExpression::class)]
#[UsesClass(Logic::class)]
#[UsesClass(NodeDatum::class)]
#[UsesClass(NullDatum::class)]
#[UsesClass(NumberArgument::class)]
#[UsesClass(PropertyExpression::class)]
#[UsesClass(StatusCode::class)]
#[UsesClass(StringDatum::class)]
#[UsesClass(SumAccumulator::class)]
#[UsesClass(TextArgument::class)]
#[UsesClass(TextFunctions::class)]
#[UsesClass(UnaryExpression::class)]
#[UsesClass(UnaryOperation::class)]
#[UsesClass(VariableExpression::class)]
#[Small]
final class ExpressionEvaluationTest extends TestCase
{
    /**
     * @throws GqlException
     */
    #[DataProvider('providerExpressionsAndWhatTheyAreWorth')]
    public function testEvaluateWorksOutWhatAnExpressionIsWorthForARow(Expression $expression, Datum $expected): void
    {
        $row = BindingRow::unit()->with('n', new IntegerDatum(3));

        self::assertEquals($expected, (new ExpressionEvaluation())->evaluate($expression, $row));
    }

    /**
     * @return iterable<string, array{Expression, Datum}>
     */
    public static function providerExpressionsAndWhatTheyAreWorth(): iterable
    {
        yield 'a literal is worth what it says' => [new LiteralExpression(new IntegerDatum(42)), new IntegerDatum(42)];

        yield 'a name is worth what the row bound it to' => [new VariableExpression('n'), new IntegerDatum(3)];

        yield 'an operator written before one value' => [
            new UnaryExpression(UnaryOperator::Negate, new VariableExpression('n')),
            new IntegerDatum(-3),
        ];

        yield 'an operator written between two' => [
            new BinaryExpression(BinaryOperator::Multiply, new VariableExpression('n'), new LiteralExpression(new IntegerDatum(2))),
            new IntegerDatum(6),
        ];

        yield 'a list is worth its values' => [
            new ListExpression([new VariableExpression('n'), new LiteralExpression(new IntegerDatum(1))]),
            new ListDatum([new IntegerDatum(3), new IntegerDatum(1)]),
        ];

        yield 'a choice is worth the branch it takes' => [
            new CaseExpression(
                null,
                [
                    new CaseBranch(
                        new BinaryExpression(BinaryOperator::Greater, new VariableExpression('n'), new LiteralExpression(new IntegerDatum(1))),
                        new LiteralExpression(new StringDatum('many')),
                    ),
                ],
                new LiteralExpression(new StringDatum('one')),
            ),
            new StringDatum('many'),
        ];

        yield 'a call is worth what the function produced' => [
            new CallExpression('upper', [new LiteralExpression(new StringDatum('a'))]),
            new StringDatum('A'),
        ];
    }

    /**
     * @throws GqlException
     */
    public function testEvaluateReadsAPropertyOffWhatANameIsBoundTo(): void
    {
        $row = BindingRow::unit()->with('p', new NodeDatum('a', [], ['line' => new IntegerDatum(12)]));

        self::assertEquals(
            new IntegerDatum(12),
            (new ExpressionEvaluation())->evaluate(new PropertyExpression(new VariableExpression('p'), 'line'), $row),
        );
    }

    /**
     * @throws GqlException
     */
    public function testEvaluateReportsANameNothingBound(): void
    {
        $this->expectException(GqlException::class);
        $this->expectExceptionMessage('[42002] error: syntax error or access rule violation - invalid reference: nothing binds "p" here');

        (new ExpressionEvaluation())->evaluate(new VariableExpression('p'), BindingRow::unit());
    }

    /**
     * @throws GqlException
     */
    public function testBoundReadsWhatARowBoundAName(): void
    {
        $row = BindingRow::unit()->with('n', new IntegerDatum(3));

        self::assertEquals(new IntegerDatum(3), (new ExpressionEvaluation())->bound('n', $row));
    }

    /**
     * @throws GqlException
     */
    public function testBoundReadsANameBoundToNothingAsNothing(): void
    {
        $row = BindingRow::unit()->with('n', new NullDatum());

        self::assertEquals(new NullDatum(), (new ExpressionEvaluation())->bound('n', $row));
    }

    /**
     * @throws GqlException
     */
    public function testBoundReportsANameNothingBoundRatherThanReadItAsAbsent(): void
    {
        $this->expectException(GqlException::class);
        $this->expectExceptionMessage('[42002] error: syntax error or access rule violation - invalid reference: nothing binds "p" here');

        (new ExpressionEvaluation())->bound('p', BindingRow::unit());
    }

    /**
     * @throws GqlException
     */
    public function testPropertyOfReadsAPropertyOffASymbol(): void
    {
        $node = new NodeDatum('a', [], ['line' => new IntegerDatum(12)]);

        self::assertEquals(new IntegerDatum(12), ExpressionEvaluation::propertyOf($node, 'line'));
    }

    /**
     * @throws GqlException
     */
    public function testPropertyOfReadsAPropertyOffEveryValueOfAList(): void
    {
        $symbols = new ListDatum([
            new NodeDatum('a', [], ['line' => new IntegerDatum(1)]),
            new NodeDatum('b', [], ['line' => new IntegerDatum(2)]),
        ]);

        self::assertEquals(
            new ListDatum([new IntegerDatum(1), new IntegerDatum(2)]),
            ExpressionEvaluation::propertyOf($symbols, 'line'),
        );
    }

    /**
     * @throws GqlException
     */
    public function testPropertyOfReadsNothingOffSomethingAbsent(): void
    {
        self::assertEquals(new NullDatum(), ExpressionEvaluation::propertyOf(new NullDatum(), 'line'));
    }

    /**
     * @throws GqlException
     */
    public function testPropertyOfReportsAValueWithNoPropertiesToRead(): void
    {
        $this->expectException(GqlException::class);
        $this->expectExceptionMessage('[22G03] error: data exception - invalid value type: a INT64 has no properties to read');

        ExpressionEvaluation::propertyOf(new IntegerDatum(1), 'line');
    }

    /**
     * @throws GqlException
     */
    public function testEvaluateBinaryStopsReadingAConjunctionOnceItsLeftSideSettlesIt(): void
    {
        $guarded = new BinaryExpression(
            BinaryOperator::And,
            new LiteralExpression(new BooleanDatum(false)),
            new BinaryExpression(
                BinaryOperator::Greater,
                new BinaryExpression(BinaryOperator::Divide, new LiteralExpression(new IntegerDatum(1)), new LiteralExpression(new IntegerDatum(0))),
                new LiteralExpression(new IntegerDatum(1)),
            ),
        );

        self::assertEquals(new BooleanDatum(false), (new ExpressionEvaluation())->evaluateBinary($guarded, BindingRow::unit()));
    }

    /**
     * @throws GqlException
     */
    public function testEvaluateBinaryStopsReadingADisjunctionOnceItsLeftSideSettlesIt(): void
    {
        $guarded = new BinaryExpression(
            BinaryOperator::Or,
            new LiteralExpression(new BooleanDatum(true)),
            new BinaryExpression(
                BinaryOperator::Greater,
                new BinaryExpression(BinaryOperator::Divide, new LiteralExpression(new IntegerDatum(1)), new LiteralExpression(new IntegerDatum(0))),
                new LiteralExpression(new IntegerDatum(1)),
            ),
        );

        self::assertEquals(new BooleanDatum(true), (new ExpressionEvaluation())->evaluateBinary($guarded, BindingRow::unit()));
    }

    /**
     * @throws GqlException
     */
    public function testEvaluateBinaryReadsTheRightSideWhenTheLeftDoesNotSettleIt(): void
    {
        $unguarded = new BinaryExpression(
            BinaryOperator::And,
            new LiteralExpression(new BooleanDatum(true)),
            new BinaryExpression(
                BinaryOperator::Greater,
                new BinaryExpression(BinaryOperator::Divide, new LiteralExpression(new IntegerDatum(1)), new LiteralExpression(new IntegerDatum(0))),
                new LiteralExpression(new IntegerDatum(1)),
            ),
        );

        $this->expectException(GqlException::class);
        $this->expectExceptionMessage('[22012] error: data exception - division by zero');

        (new ExpressionEvaluation())->evaluateBinary($unguarded, BindingRow::unit());
    }

    /**
     * @throws GqlException
     */
    public function testEvaluateCaseTakesTheFirstBranchThatHolds(): void
    {
        $choice = new CaseExpression(null, [
            new CaseBranch(new LiteralExpression(new BooleanDatum(false)), new LiteralExpression(new StringDatum('first'))),
            new CaseBranch(new LiteralExpression(new BooleanDatum(true)), new LiteralExpression(new StringDatum('second'))),
            new CaseBranch(new LiteralExpression(new BooleanDatum(true)), new LiteralExpression(new StringDatum('third'))),
        ]);

        self::assertEquals(new StringDatum('second'), (new ExpressionEvaluation())->evaluateCase($choice, BindingRow::unit()));
    }

    /**
     * @throws GqlException
     */
    public function testEvaluateCaseComparesAgainstWhatTheChoiceIsAbout(): void
    {
        $choice = new CaseExpression(
            new VariableExpression('n'),
            [new CaseBranch(new LiteralExpression(new IntegerDatum(3)), new LiteralExpression(new StringDatum('three')))],
            new LiteralExpression(new StringDatum('other')),
        );
        $row = BindingRow::unit()->with('n', new IntegerDatum(3));

        self::assertEquals(new StringDatum('three'), (new ExpressionEvaluation())->evaluateCase($choice, $row));
    }

    /**
     * @throws GqlException
     */
    public function testEvaluateCaseTakesTheFallbackWhenNoBranchHolds(): void
    {
        $choice = new CaseExpression(
            null,
            [new CaseBranch(new LiteralExpression(new NullDatum()), new LiteralExpression(new StringDatum('x')))],
            new LiteralExpression(new StringDatum('y')),
        );

        self::assertEquals(new StringDatum('y'), (new ExpressionEvaluation())->evaluateCase($choice, BindingRow::unit()));
    }

    /**
     * @throws GqlException
     */
    public function testEvaluateCaseIsWorthNothingWhenItMatchesNothingAndOffersNoFallback(): void
    {
        $choice = new CaseExpression(null, [
            new CaseBranch(new LiteralExpression(new BooleanDatum(false)), new LiteralExpression(new StringDatum('x'))),
        ]);

        self::assertEquals(new NullDatum(), (new ExpressionEvaluation())->evaluateCase($choice, BindingRow::unit()));
    }

    /**
     * @throws GqlException
     */
    public function testEvaluateCaseReportsAWhenOfAKindWithNoComparisonToWhatTheChoiceIsAbout(): void
    {
        $choice = new CaseExpression(
            new VariableExpression('n'),
            [new CaseBranch(new LiteralExpression(new StringDatum('3')), new LiteralExpression(new StringDatum('three')))],
        );
        $row = BindingRow::unit()->with('n', new IntegerDatum(3));

        $this->expectException(GqlException::class);
        $this->expectExceptionMessage('[22G04] error: data exception - values not comparable');

        (new ExpressionEvaluation())->evaluateCase($choice, $row);
    }

    /**
     * @throws GqlException
     */
    public function testEvaluateCallAppliesAnOrdinaryFunctionToWhatItWasGiven(): void
    {
        $call = new CallExpression('upper', [new VariableExpression('s')]);
        $row = BindingRow::unit()->with('s', new StringDatum('a'));

        self::assertEquals(new StringDatum('A'), (new ExpressionEvaluation())->evaluateCall($call, $row));
    }

    /**
     * @throws GqlException
     */
    public function testEvaluateCallSummarisesWhenTheFunctionIsASummary(): void
    {
        $call = new CallExpression('max', [new VariableExpression('e')]);
        $row = BindingRow::unit()->with('e', new ListDatum([new IntegerDatum(1), new IntegerDatum(4)]));

        self::assertEquals(new IntegerDatum(4), (new ExpressionEvaluation())->evaluateCall($call, $row));
    }

    /**
     * @throws GqlException
     */
    public function testOverSummarisesTheRowsTheEvaluatorStandsOver(): void
    {
        $rows = [BindingRow::unit(), BindingRow::unit()];

        self::assertEquals(
            new IntegerDatum(2),
            ExpressionEvaluation::over($rows)->evaluate(new CallExpression('count', star: true), $rows[0]),
        );
    }

    /**
     * @throws GqlException
     */
    public function testEvaluateSummarySummarisesAValueDownTheGroupOfRows(): void
    {
        $rows = [
            BindingRow::unit()->with('n', new IntegerDatum(1)),
            BindingRow::unit()->with('n', new IntegerDatum(2)),
        ];

        self::assertEquals(
            new IntegerDatum(3),
            ExpressionEvaluation::over($rows)->evaluateSummary(new CallExpression('sum', [new VariableExpression('n')]), $rows[0]),
        );
    }

    /**
     * @throws GqlException
     */
    public function testEvaluateSummarySummarisesAListAlongTheRowEvenWhereThereIsAGroup(): void
    {
        $rows = [
            BindingRow::unit()->with('e', new ListDatum([new IntegerDatum(1), new IntegerDatum(4)])),
            BindingRow::unit()->with('e', new ListDatum([new IntegerDatum(9)])),
        ];

        self::assertEquals(
            new IntegerDatum(4),
            ExpressionEvaluation::over($rows)->evaluateSummary(new CallExpression('max', [new VariableExpression('e')]), $rows[0]),
        );
    }

    /**
     * @throws GqlException
     */
    public function testEvaluateSummarySummarisesAListWhereThereIsNoGroup(): void
    {
        $row = BindingRow::unit()->with('e', new ListDatum([new IntegerDatum(1), new IntegerDatum(4)]));

        self::assertEquals(
            new IntegerDatum(4),
            (new ExpressionEvaluation())->evaluateSummary(new CallExpression('max', [new VariableExpression('e')]), $row),
        );
    }

    /**
     * @throws GqlException
     */
    public function testEvaluateSummarySummarisesOneValueWhereThereIsNoGroupAtAll(): void
    {
        $row = BindingRow::unit()->with('n', new IntegerDatum(3));

        self::assertEquals(
            new IntegerDatum(3),
            (new ExpressionEvaluation())->evaluateSummary(new CallExpression('max', [new VariableExpression('n')]), $row),
        );
    }

    /**
     * @throws GqlException
     */
    public function testEvaluateSummarySummarisesNoRowsWhateverItWasWrittenOver(): void
    {
        $counted = new CallExpression('count', [new VariableExpression('caller')], distinct: true);

        self::assertEquals(new IntegerDatum(0), ExpressionEvaluation::over([])->evaluateSummary($counted, BindingRow::unit()));
    }

    /**
     * @throws GqlException
     */
    public function testEvaluateSummaryReportsASummaryGivenMoreThanOneThingToSummarise(): void
    {
        $summary = new CallExpression('count', [new VariableExpression('a'), new VariableExpression('b')]);

        $this->expectException(GqlException::class);
        $this->expectExceptionMessage('[42001] error: syntax error or access rule violation - invalid syntax: count summarises one thing, and was given 2');

        ExpressionEvaluation::over([BindingRow::unit()])->evaluateSummary($summary, BindingRow::unit());
    }

    /**
     * @throws GqlException
     */
    public function testSummaryOverRowsCountsTheRowsTheEvaluatorStandsOver(): void
    {
        $counted = new CallExpression('count', star: true);

        self::assertEquals(
            new IntegerDatum(2),
            ExpressionEvaluation::over([BindingRow::unit(), BindingRow::unit()])->summaryOverRows($counted, null, new ExpressionEvaluation()),
        );
    }

    /**
     * @throws GqlException
     */
    public function testSummaryOverRowsWorksOutWhatIsSummarisedForEachRow(): void
    {
        $rows = [
            BindingRow::unit()->with('n', new IntegerDatum(1)),
            BindingRow::unit()->with('n', new NullDatum()),
            BindingRow::unit()->with('n', new IntegerDatum(1)),
        ];
        $counted = new CallExpression('count', [new VariableExpression('n')]);

        self::assertEquals(
            new IntegerDatum(2),
            ExpressionEvaluation::over($rows)->summaryOverRows($counted, new VariableExpression('n'), new ExpressionEvaluation()),
        );
    }

    /**
     * @throws GqlException
     */
    public function testSummaryOverRowsReportsASummaryWrittenWhereThereAreNoRowsToSummarise(): void
    {
        $this->expectException(GqlException::class);
        $this->expectExceptionMessage('[42001] error: syntax error or access rule violation - invalid syntax: count summarises rows, and can only be written where a projection produces them');

        (new ExpressionEvaluation())->summaryOverRows(new CallExpression('count', star: true), null, new ExpressionEvaluation());
    }
}
