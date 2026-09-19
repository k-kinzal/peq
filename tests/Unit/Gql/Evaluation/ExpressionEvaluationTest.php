<?php

declare(strict_types=1);

namespace Tests\Unit\Gql\Evaluation;

use App\Gql\Argument\NumberArgument;
use App\Gql\Argument\TextArgument;
use App\Gql\Binding\BindingRow;
use App\Gql\Datum\BooleanDatum;
use App\Gql\Datum\DatumIdentity;
use App\Gql\Datum\DatumKind;
use App\Gql\Datum\DatumOrder;
use App\Gql\Datum\EdgeDatum;
use App\Gql\Datum\FloatDatum;
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
use App\Gql\Evaluation\Membership;
use App\Gql\Evaluation\TextOperation;
use App\Gql\Evaluation\UnaryOperation;
use App\Gql\GqlException;
use App\Gql\Invocation\Accumulator;
use App\Gql\Invocation\AggregateCatalog;
use App\Gql\Invocation\CollectAccumulator;
use App\Gql\Invocation\CountAccumulator;
use App\Gql\Invocation\DistinctAccumulator;
use App\Gql\Invocation\ExtremeAccumulator;
use App\Gql\Invocation\FunctionCatalog;
use App\Gql\Invocation\GeneralFunctions;
use App\Gql\Invocation\GraphFunctions;
use App\Gql\Invocation\ListFunctions;
use App\Gql\Invocation\SumAccumulator;
use App\Gql\Invocation\TextFunctions;
use App\Gql\Lexing\Lexer;
use App\Gql\Lexing\QuotedScanner;
use App\Gql\Lexing\SourceCursor;
use App\Gql\Lexing\Token;
use App\Gql\Lexing\TokenKind;
use App\Gql\Lexing\TokenList;
use App\Gql\Parsing\ExpressionParser;
use App\Gql\Parsing\OperandParser;
use App\Gql\Parsing\TokenReader;
use App\Gql\StatusCode;
use App\Gql\Syntax\Expression\BinaryExpression;
use App\Gql\Syntax\Expression\BinaryOperator;
use App\Gql\Syntax\Expression\CallExpression;
use App\Gql\Syntax\Expression\CaseBranch;
use App\Gql\Syntax\Expression\CaseExpression;
use App\Gql\Syntax\Expression\IndexExpression;
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
use Tests\Fixture\Gql\ExpressionWorth;

/**
 * @internal
 */
#[CoversClass(ExpressionEvaluation::class)]
#[UsesClass(BooleanDatum::class)]
#[UsesClass(DatumKind::class)]
#[UsesClass(DatumIdentity::class)]
#[UsesClass(DatumOrder::class)]
#[UsesClass(EdgeDatum::class)]
#[UsesClass(FloatDatum::class)]
#[UsesClass(NodeDatum::class)]
#[UsesClass(IntegerDatum::class)]
#[UsesClass(ListDatum::class)]
#[UsesClass(NullDatum::class)]
#[UsesClass(StringDatum::class)]
#[UsesClass(GqlException::class)]
#[UsesClass(StatusCode::class)]
#[UsesClass(Lexer::class)]
#[UsesClass(QuotedScanner::class)]
#[UsesClass(SourceCursor::class)]
#[UsesClass(Token::class)]
#[UsesClass(TokenKind::class)]
#[UsesClass(TokenList::class)]
#[UsesClass(ExpressionParser::class)]
#[UsesClass(OperandParser::class)]
#[UsesClass(TokenReader::class)]
#[UsesClass(BinaryExpression::class)]
#[UsesClass(BinaryOperator::class)]
#[UsesClass(CallExpression::class)]
#[UsesClass(CaseBranch::class)]
#[UsesClass(CaseExpression::class)]
#[UsesClass(IndexExpression::class)]
#[UsesClass(ListExpression::class)]
#[UsesClass(LiteralExpression::class)]
#[UsesClass(PropertyExpression::class)]
#[UsesClass(UnaryExpression::class)]
#[UsesClass(UnaryOperator::class)]
#[UsesClass(VariableExpression::class)]
#[UsesClass(NumberArgument::class)]
#[UsesClass(TextArgument::class)]
#[UsesClass(BindingRow::class)]
#[UsesClass(Arithmetic::class)]
#[UsesClass(BinaryOperation::class)]
#[UsesClass(Comparison::class)]
#[UsesClass(Logic::class)]
#[UsesClass(Membership::class)]
#[UsesClass(TextOperation::class)]
#[UsesClass(UnaryOperation::class)]
#[UsesClass(Accumulator::class)]
#[UsesClass(AggregateCatalog::class)]
#[UsesClass(CollectAccumulator::class)]
#[UsesClass(CountAccumulator::class)]
#[UsesClass(DistinctAccumulator::class)]
#[UsesClass(ExtremeAccumulator::class)]
#[UsesClass(FunctionCatalog::class)]
#[UsesClass(GeneralFunctions::class)]
#[UsesClass(GraphFunctions::class)]
#[UsesClass(ListFunctions::class)]
#[UsesClass(SumAccumulator::class)]
#[UsesClass(TextFunctions::class)]
#[Small]
final class ExpressionEvaluationTest extends TestCase
{
    /**
     * @throws GqlException
     */
    #[DataProvider('providerExpressionsAndWhatTheyAreWorth')]
    public function testEvaluateWorksOutWhatAnExpressionIsWorthForARow(string $written, string $expected): void
    {
        self::assertSame($expected, ExpressionWorth::of($written, ['n' => new IntegerDatum(3)]));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function providerExpressionsAndWhatTheyAreWorth(): iterable
    {
        yield 'a literal is worth what it says' => ['42', '42'];

        yield 'a name is worth what the row bound it to' => ['n', '3'];

        yield 'an operator written before one value' => ['-n', '-3'];

        yield 'an operator written between two' => ['n * 2', '6'];

        yield 'a list is worth its values' => ['[n, 1]', '[3, 1]'];

        yield 'a choice is worth the branch it takes' => ["CASE WHEN n > 1 THEN 'many' ELSE 'one' END", 'many'];

        yield 'a call is worth what the function produced' => ["upper('a')", 'A'];
    }

    /**
     * @throws GqlException
     */
    public function testBoundReadsWhatARowBoundAName(): void
    {
        self::assertSame('3', ExpressionWorth::of('n', ['n' => new IntegerDatum(3)]));
    }

    /**
     * @throws GqlException
     */
    public function testBoundReportsANameNothingBoundRatherThanReadItAsAbsent(): void
    {
        $this->expectException(GqlException::class);
        $this->expectExceptionMessage('nothing binds "p" here');

        ExpressionWorth::of('p');
    }

    /**
     * @throws GqlException
     */
    public function testPropertyOfReadsAPropertyOffASymbol(): void
    {
        $node = new NodeDatum('a', [], ['line' => new IntegerDatum(12)]);

        self::assertSame('12', ExpressionEvaluation::propertyOf($node, 'line')->toText());
    }

    /**
     * @throws GqlException
     */
    public function testPropertyOfReadsAPropertyOffEveryValueOfAList(): void
    {
        $first = new NodeDatum('a', [], ['line' => new IntegerDatum(1)]);
        $second = new NodeDatum('b', [], ['line' => new IntegerDatum(2)]);

        self::assertSame('[1, 2]', ExpressionEvaluation::propertyOf(new ListDatum([$first, $second]), 'line')->toText());
    }

    /**
     * @throws GqlException
     */
    public function testPropertyOfReadsNothingOffSomethingAbsent(): void
    {
        self::assertSame(DatumKind::Null, ExpressionEvaluation::propertyOf(new NullDatum(), 'line')->kind());
    }

    /**
     * @throws GqlException
     */
    public function testPropertyOfReportsAValueWithNoPropertiesToRead(): void
    {
        $this->expectException(GqlException::class);
        $this->expectExceptionMessage('a INT64 has no properties to read');

        ExpressionEvaluation::propertyOf(new IntegerDatum(1), 'line');
    }

    /**
     * @throws GqlException
     */
    public function testEvaluateIndexTakesOneValueOutOfAListByItsPlace(): void
    {
        self::assertSame('2', ExpressionWorth::of('xs[1]', ['xs' => new ListDatum([new IntegerDatum(1), new IntegerDatum(2)])]));
    }

    /**
     * @throws GqlException
     */
    public function testEvaluateIndexReadsAPlaceTheListDoesNotHaveAsAbsent(): void
    {
        self::assertSame('NULL', ExpressionWorth::of('xs[5]', ['xs' => new ListDatum([])]));
    }

    /**
     * @throws GqlException
     */
    public function testEvaluateIndexReadsAnAbsentListAsAbsent(): void
    {
        self::assertSame('NULL', ExpressionWorth::of('xs[0]', ['xs' => new NullDatum()]));
    }

    /**
     * @throws GqlException
     */
    public function testEvaluateIndexReportsAPlaceThatIsNotAWholeNumber(): void
    {
        $this->expectException(GqlException::class);
        $this->expectExceptionMessage('a whole number was expected, and a STRING was given');

        ExpressionWorth::of("xs['a']", ['xs' => new ListDatum([])]);
    }

    /**
     * @throws GqlException
     */
    public function testEvaluateBinaryStopsReadingAConjunctionOnceItsLeftSideSettlesIt(): void
    {
        self::assertSame('FALSE', ExpressionWorth::of('FALSE AND 1 / 0 > 1'));
    }

    /**
     * @throws GqlException
     */
    public function testEvaluateBinaryStopsReadingADisjunctionOnceItsLeftSideSettlesIt(): void
    {
        self::assertSame('TRUE', ExpressionWorth::of('TRUE OR 1 / 0 > 1'));
    }

    /**
     * @throws GqlException
     */
    public function testEvaluateCaseTakesTheFirstBranchThatHolds(): void
    {
        self::assertSame('second', ExpressionWorth::of("CASE WHEN FALSE THEN 'first' WHEN TRUE THEN 'second' END"));
    }

    /**
     * @throws GqlException
     */
    public function testEvaluateCaseComparesAgainstWhatTheChoiceIsAbout(): void
    {
        self::assertSame('three', ExpressionWorth::of("CASE n WHEN 3 THEN 'three' ELSE 'other' END", ['n' => new IntegerDatum(3)]));
    }

    /**
     * @throws GqlException
     */
    public function testEvaluateCaseIsWorthNothingWhenItMatchesNothingAndOffersNoFallback(): void
    {
        self::assertSame('NULL', ExpressionWorth::of("CASE WHEN FALSE THEN 'x' END"));
    }

    /**
     * @throws GqlException
     */
    public function testEvaluateCallAppliesAnOrdinaryFunctionToWhatItWasGiven(): void
    {
        self::assertSame('A', ExpressionWorth::of("upper('a')"));
    }

    /**
     * @throws GqlException
     */
    public function testOverSummarisesTheRowsTheEvaluatorStandsOver(): void
    {
        self::assertSame('2', ExpressionWorth::over('count(*)', [BindingRow::unit(), BindingRow::unit()]));
    }

    /**
     * @throws GqlException
     */
    public function testEvaluateSummarySummarisesAListWhereOneIsWrittenOver(): void
    {
        self::assertSame('4', ExpressionWorth::of('max(e)', ['e' => new ListDatum([new IntegerDatum(1), new IntegerDatum(4)])]));
    }

    /**
     * @throws GqlException
     */
    public function testEvaluateSummarySummarisesOneValueWhereThereIsNoGroupAtAll(): void
    {
        self::assertSame('3', ExpressionWorth::of('max(n)', ['n' => new IntegerDatum(3)]));
    }

    /**
     * @throws GqlException
     */
    public function testEvaluateSummarySummarisesNoRowsWhateverItWasWrittenOver(): void
    {
        self::assertSame('0', ExpressionWorth::over('count(DISTINCT caller)', []));
    }

    /**
     * @throws GqlException
     */
    public function testEvaluateSummaryReportsASummaryGivenMoreThanOneThingToSummarise(): void
    {
        $this->expectException(GqlException::class);
        $this->expectExceptionMessage('count summarises one thing, and was given 2');

        ExpressionWorth::over('count(a, b)', [BindingRow::unit()]);
    }

    /**
     * @throws GqlException
     */
    public function testSummaryOverRowsReportsASummaryWrittenWhereThereAreNoRowsToSummarise(): void
    {
        $this->expectException(GqlException::class);
        $this->expectExceptionMessage('count summarises rows, and can only be written where a projection produces them');

        ExpressionWorth::of('count(*)');
    }
}
