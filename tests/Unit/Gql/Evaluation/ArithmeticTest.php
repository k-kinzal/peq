<?php

declare(strict_types=1);

namespace Tests\Unit\Gql\Evaluation;

use App\Gql\Argument\NumberArgument;
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
use App\Gql\GqlException;
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
#[CoversClass(Arithmetic::class)]
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
#[Small]
final class ArithmeticTest extends TestCase
{
    /**
     * @throws GqlException
     */
    #[DataProvider('providerArithmetic')]
    public function testApplyFollowsTheRulesAboutMixingKindsOfNumber(string $written, string $expected): void
    {
        self::assertSame($expected, ExpressionWorth::of($written));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function providerArithmetic(): iterable
    {
        yield 'whole numbers added stay whole' => ['1 + 2', '3'];

        yield 'whole numbers subtracted stay whole' => ['5 - 2', '3'];

        yield 'whole numbers multiplied stay whole' => ['2 * 3', '6'];

        yield 'whole numbers divided stay whole' => ['7 / 2', '3'];

        yield 'meeting an approximate number makes the result approximate' => ['1 + 1.5', '2.5'];

        yield 'an approximate division keeps its fraction' => ['3 / 2.0', '1.5'];

        yield 'meeting the absence of a value produces the absence of one' => ['1 + NULL', 'NULL'];

        yield 'the absence of a value on the left too' => ['NULL * 2', 'NULL'];
    }

    /**
     * @throws GqlException
     */
    public function testApplyReportsAValueThatIsNotANumber(): void
    {
        $this->expectException(GqlException::class);
        $this->expectExceptionMessage('a number was expected');

        ExpressionWorth::of("1 + 'a'");
    }

    /**
     * @throws GqlException
     */
    public function testDivideKeepsTwoWholeNumbersWhole(): void
    {
        self::assertSame('1999', Arithmetic::divide(19990101, 10000, false)->toText());
    }

    /**
     * @throws GqlException
     */
    public function testDivideKeepsAnApproximateNumberApproximate(): void
    {
        self::assertSame('1.5', Arithmetic::divide(3, 2.0, true)->toText());
    }

    /**
     * @throws GqlException
     */
    public function testDivideReportsDivisionByAWholeZero(): void
    {
        $this->expectException(GqlException::class);
        $this->expectExceptionMessage('a number cannot be divided by zero');

        Arithmetic::divide(1, 0, false);
    }

    /**
     * @throws GqlException
     */
    public function testDivideReportsDivisionByAnApproximateZero(): void
    {
        $this->expectException(GqlException::class);
        $this->expectExceptionMessage('a number cannot be divided by zero');

        Arithmetic::divide(1.0, 0.0, true);
    }

    /**
     * @throws GqlException
     */
    public function testNegateKeepsAWholeNumberWhole(): void
    {
        self::assertSame('-3', Arithmetic::negate(new IntegerDatum(3))->toText());
    }

    /**
     * @throws GqlException
     */
    public function testNegateKeepsAnApproximateNumberApproximate(): void
    {
        self::assertSame('-1.5', Arithmetic::negate(new FloatDatum(1.5))->toText());
    }

    /**
     * @throws GqlException
     */
    public function testNegateFindsNoSignToReverseOnTheAbsenceOfAValue(): void
    {
        self::assertSame(DatumKind::Null, Arithmetic::negate(new NullDatum())->kind());
    }
}
