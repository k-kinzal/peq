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
use App\Gql\Evaluation\Logic;
use App\Gql\Evaluation\UnaryOperation;
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

/**
 * @internal
 */
#[CoversClass(UnaryOperation::class)]
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
#[UsesClass(Arithmetic::class)]
#[UsesClass(Logic::class)]
#[Small]
final class UnaryOperationTest extends TestCase
{
    /**
     * @throws GqlException
     */
    #[DataProvider('providerOperatorsAndTheRuleTheyFollow')]
    public function testApplySortsEveryOperatorIntoTheRulesItFollows(UnaryOperator $operator, string $expected): void
    {
        self::assertSame($expected, UnaryOperation::apply($operator, new IntegerDatum(3))->toText());
    }

    /**
     * @return iterable<string, array{UnaryOperator, string}>
     */
    public static function providerOperatorsAndTheRuleTheyFollow(): iterable
    {
        yield 'a sign is arithmetic' => [UnaryOperator::Negate, '-3'];

        yield 'a positive sign says the value is meant to be a number' => [UnaryOperator::Identity, '3'];

        yield 'a null test always has an answer' => [UnaryOperator::IsNull, 'FALSE'];

        yield 'and so does its opposite' => [UnaryOperator::IsNotNull, 'TRUE'];
    }

    /**
     * @throws GqlException
     */
    public function testApplyNegatesUnderThreeValuedLogic(): void
    {
        self::assertSame(DatumKind::Null, UnaryOperation::apply(UnaryOperator::Not, new NullDatum())->kind());
    }

    /**
     * @throws GqlException
     */
    public function testApplyDecidesANullTestEvenForAnAbsentValue(): void
    {
        self::assertSame('TRUE', UnaryOperation::apply(UnaryOperator::IsNull, new NullDatum())->toText());
    }

    /**
     * @throws GqlException
     */
    public function testIdentityReturnsTheNumberItWasWrittenBefore(): void
    {
        self::assertSame('3', UnaryOperation::identity(new IntegerDatum(3))->toText());
    }

    /**
     * @throws GqlException
     */
    public function testIdentityLeavesTheAbsenceOfAValueAlone(): void
    {
        self::assertSame(DatumKind::Null, UnaryOperation::identity(new NullDatum())->kind());
    }

    /**
     * @throws GqlException
     */
    public function testIdentityReportsSomethingThatIsNotANumber(): void
    {
        $this->expectException(GqlException::class);
        $this->expectExceptionMessage('a number was expected');

        UnaryOperation::identity(new StringDatum('3'));
    }
}
