<?php

declare(strict_types=1);

namespace Tests\Unit\Gql\Parsing;

use App\Gql\Datum\BooleanDatum;
use App\Gql\Datum\DatumKind;
use App\Gql\Datum\FloatDatum;
use App\Gql\Datum\IntegerDatum;
use App\Gql\Datum\NullDatum;
use App\Gql\Datum\StringDatum;
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
use Tests\Fixture\Gql\ExpressionSpelling;

/**
 * @internal
 */
#[CoversClass(ExpressionParser::class)]
#[UsesClass(GqlException::class)]
#[UsesClass(StatusCode::class)]
#[UsesClass(Lexer::class)]
#[UsesClass(QuotedScanner::class)]
#[UsesClass(SourceCursor::class)]
#[UsesClass(Token::class)]
#[UsesClass(TokenKind::class)]
#[UsesClass(TokenList::class)]
#[UsesClass(TokenReader::class)]
#[UsesClass(OperandParser::class)]
#[UsesClass(BooleanDatum::class)]
#[UsesClass(DatumKind::class)]
#[UsesClass(FloatDatum::class)]
#[UsesClass(IntegerDatum::class)]
#[UsesClass(NullDatum::class)]
#[UsesClass(StringDatum::class)]
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
#[Small]
final class ExpressionParserTest extends TestCase
{
    /**
     * @throws GqlException
     */
    #[DataProvider('providerExpressionsAndTheirShape')]
    public function testParseReadsAnExpressionAsTheTreeItsOperatorsDescribe(string $written, string $shape): void
    {
        self::assertSame($shape, ExpressionSpelling::of($written));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function providerExpressionsAndTheirShape(): iterable
    {
        yield 'disjunction binds loosest' => ['a AND b OR c', '((a AND b) OR c)'];

        yield 'exclusive disjunction binds between' => ['a OR b XOR c', '(a OR (b XOR c))'];

        yield 'conjunction binds tighter than either' => ['a OR b AND c', '(a OR (b AND c))'];

        yield 'negation takes the whole comparison after it' => ['NOT a = b', '(NOT (a = b))'];

        yield 'negation binds looser than comparison, tighter than conjunction' => ['NOT a AND b', '((NOT a) AND b)'];

        yield 'comparison binds tighter than negation' => ['a < b', '(a < b)'];

        yield 'addition binds tighter than comparison' => ['a + b < c', '((a + b) < c)'];

        yield 'multiplication binds tighter than addition' => ['a + b * c', '(a + (b * c))'];

        yield 'concatenation binds as tightly as addition' => ["a || ' ' || b", "((a || ' ') || b)"];

        yield 'a sign takes only the value after it' => ['-a * b', '((- a) * b)'];

        yield 'parentheses override every binding' => ['(a OR b) AND c', '((a OR b) AND c)'];
    }

    /**
     * @throws GqlException
     */
    #[DataProvider('providerComparingOperators')]
    public function testComparisonInReadsTheComparingOperatorStandingAtAReader(string $written, string $shape): void
    {
        self::assertSame($shape, ExpressionSpelling::of($written));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function providerComparingOperators(): iterable
    {
        yield 'equality' => ['a = b', '(a = b)'];

        yield 'inequality' => ['a <> b', '(a <> b)'];

        yield 'ordering' => ['a <= b', '(a <= b)'];

        yield 'the other ordering' => ['a >= b', '(a >= b)'];

        yield 'membership' => ['a IN [b]', '(a IN [b])'];
    }

    /**
     * @throws GqlException
     */
    #[DataProvider('providerTwoWordOperators')]
    public function testTwoWordComparisonInReadsAnOperatorWrittenAsTwoWords(string $written, string $shape): void
    {
        self::assertSame($shape, ExpressionSpelling::of($written));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function providerTwoWordOperators(): iterable
    {
        yield 'a refused membership' => ['a NOT IN [b]', '(a NOT IN [b])'];
    }

    /**
     * @throws GqlException
     */
    public function testParseOrReadsADisjunction(): void
    {
        self::assertSame('(a OR b)', ExpressionSpelling::of('a OR b'));
    }

    /**
     * @throws GqlException
     */
    public function testParseXorReadsAnExclusiveDisjunction(): void
    {
        self::assertSame('(a XOR b)', ExpressionSpelling::of('a XOR b'));
    }

    /**
     * @throws GqlException
     */
    public function testParseAndReadsAConjunction(): void
    {
        self::assertSame('(a AND b)', ExpressionSpelling::of('a AND b'));
    }

    /**
     * @throws GqlException
     */
    public function testParseNotReadsANegation(): void
    {
        self::assertSame('(NOT a)', ExpressionSpelling::of('NOT a'));
    }

    /**
     * @throws GqlException
     */
    public function testParseComparisonReadsAComparison(): void
    {
        self::assertSame('(a > b)', ExpressionSpelling::of('a > b'));
    }

    /**
     * @throws GqlException
     */
    public function testParseAdditiveReadsAnAdditionASubtractionAndAConcatenation(): void
    {
        self::assertSame('((a + b) - c)', ExpressionSpelling::of('a + b - c'));
    }

    /**
     * @throws GqlException
     */
    public function testParseMultiplicativeReadsAMultiplicationAndADivision(): void
    {
        self::assertSame('((a * b) / c)', ExpressionSpelling::of('a * b / c'));
    }

    /**
     * @throws GqlException
     */
    public function testParseUnaryReadsASignWrittenBeforeAValue(): void
    {
        self::assertSame('(+ a)', ExpressionSpelling::of('+a'));
    }
}
