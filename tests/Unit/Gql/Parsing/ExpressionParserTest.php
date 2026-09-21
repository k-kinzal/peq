<?php

declare(strict_types=1);

namespace Tests\Unit\Gql\Parsing;

use App\Gql\Datum\BooleanDatum;
use App\Gql\Datum\DatumKind;
use App\Gql\Datum\DecimalDatum;
use App\Gql\Datum\IntegerDatum;
use App\Gql\Datum\StringDatum;
use App\Gql\GqlException;
use App\Gql\Lexing\Lexer;
use App\Gql\Lexing\QuotedScanner;
use App\Gql\Lexing\SourceCursor;
use App\Gql\Lexing\Token;
use App\Gql\Lexing\TokenKind;
use App\Gql\Lexing\TokenList;
use App\Gql\Parsing\ExpressionParser;
use App\Gql\Parsing\NameReader;
use App\Gql\Parsing\OperandParser;
use App\Gql\Parsing\TokenReader;
use App\Gql\ReservedWords;
use App\Gql\StatusCode;
use App\Gql\Syntax\Expression;
use App\Gql\Syntax\Expression\BinaryExpression;
use App\Gql\Syntax\Expression\BinaryOperator;
use App\Gql\Syntax\Expression\LiteralExpression;
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
#[UsesClass(NameReader::class)]
#[UsesClass(OperandParser::class)]
#[UsesClass(ReservedWords::class)]
#[UsesClass(BooleanDatum::class)]
#[UsesClass(DatumKind::class)]
#[UsesClass(DecimalDatum::class)]
#[UsesClass(IntegerDatum::class)]
#[UsesClass(StringDatum::class)]
#[UsesClass(BinaryExpression::class)]
#[UsesClass(BinaryOperator::class)]
#[UsesClass(LiteralExpression::class)]
#[UsesClass(UnaryExpression::class)]
#[UsesClass(UnaryOperator::class)]
#[UsesClass(VariableExpression::class)]
#[Small]
final class ExpressionParserTest extends TestCase
{
    /**
     * @throws GqlException
     */
    #[DataProvider('providerExpressionsAndTheTreeTheirOperatorsDescribe')]
    public function testParseReadsAnExpressionAsTheTreeItsOperatorsDescribe(string $written, Expression $expected): void
    {
        self::assertEquals($expected, (new ExpressionParser(TokenReader::of($written)))->parse());
    }

    /**
     * @return iterable<string, array{string, Expression}>
     */
    public static function providerExpressionsAndTheTreeTheirOperatorsDescribe(): iterable
    {
        yield 'disjunction binds looser than conjunction' => [
            'a AND b OR c',
            new BinaryExpression(
                BinaryOperator::Or,
                new BinaryExpression(BinaryOperator::And, new VariableExpression('a'), new VariableExpression('b')),
                new VariableExpression('c'),
            ),
        ];

        yield 'conjunction binds tighter than disjunction' => [
            'a OR b AND c',
            new BinaryExpression(
                BinaryOperator::Or,
                new VariableExpression('a'),
                new BinaryExpression(BinaryOperator::And, new VariableExpression('b'), new VariableExpression('c')),
            ),
        ];

        yield 'exclusive disjunction shares a layer with disjunction, read left to right' => [
            'a OR b XOR c',
            new BinaryExpression(
                BinaryOperator::Xor,
                new BinaryExpression(BinaryOperator::Or, new VariableExpression('a'), new VariableExpression('b')),
                new VariableExpression('c'),
            ),
        ];

        yield 'disjunction after exclusive disjunction, read left to right' => [
            'a XOR b OR c',
            new BinaryExpression(
                BinaryOperator::Or,
                new BinaryExpression(BinaryOperator::Xor, new VariableExpression('a'), new VariableExpression('b')),
                new VariableExpression('c'),
            ),
        ];

        yield 'a negation takes the whole comparison after it' => [
            'NOT a = b',
            new UnaryExpression(
                UnaryOperator::Not,
                new BinaryExpression(BinaryOperator::Equal, new VariableExpression('a'), new VariableExpression('b')),
            ),
        ];

        yield 'a negation binds tighter than a conjunction' => [
            'NOT a AND b',
            new BinaryExpression(
                BinaryOperator::And,
                new UnaryExpression(UnaryOperator::Not, new VariableExpression('a')),
                new VariableExpression('b'),
            ),
        ];

        yield 'a truth-value test binds tighter than a negation' => [
            'NOT a IS TRUE',
            new UnaryExpression(
                UnaryOperator::Not,
                new UnaryExpression(UnaryOperator::IsTrue, new VariableExpression('a')),
            ),
        ];

        yield 'a truth-value test takes the whole comparison before it' => [
            'a = b IS UNKNOWN',
            new UnaryExpression(
                UnaryOperator::IsUnknown,
                new BinaryExpression(BinaryOperator::Equal, new VariableExpression('a'), new VariableExpression('b')),
            ),
        ];

        yield 'a null test takes only the value before it' => [
            'a = b IS NULL',
            new BinaryExpression(
                BinaryOperator::Equal,
                new VariableExpression('a'),
                new UnaryExpression(UnaryOperator::IsNull, new VariableExpression('b')),
            ),
        ];

        yield 'addition binds tighter than comparison' => [
            'a + b < c',
            new BinaryExpression(
                BinaryOperator::Less,
                new BinaryExpression(BinaryOperator::Add, new VariableExpression('a'), new VariableExpression('b')),
                new VariableExpression('c'),
            ),
        ];

        yield 'multiplication binds tighter than addition' => [
            'a + b * c',
            new BinaryExpression(
                BinaryOperator::Add,
                new VariableExpression('a'),
                new BinaryExpression(BinaryOperator::Multiply, new VariableExpression('b'), new VariableExpression('c')),
            ),
        ];

        yield 'concatenation binds as tightly as addition' => [
            "a || ' ' || b",
            new BinaryExpression(
                BinaryOperator::Concatenate,
                new BinaryExpression(BinaryOperator::Concatenate, new VariableExpression('a'), new LiteralExpression(new StringDatum(' '))),
                new VariableExpression('b'),
            ),
        ];

        yield 'a sign takes only the value after it' => [
            '-a * b',
            new BinaryExpression(
                BinaryOperator::Multiply,
                new UnaryExpression(UnaryOperator::Negate, new VariableExpression('a')),
                new VariableExpression('b'),
            ),
        ];

        yield 'a sign before a number is an operator rather than part of the number' => [
            '-1',
            new UnaryExpression(UnaryOperator::Negate, new LiteralExpression(new IntegerDatum(1))),
        ];

        yield 'parentheses override every binding' => [
            '(a OR b) AND c',
            new BinaryExpression(
                BinaryOperator::And,
                new BinaryExpression(BinaryOperator::Or, new VariableExpression('a'), new VariableExpression('b')),
                new VariableExpression('c'),
            ),
        ];
    }

    /**
     * @throws GqlException
     */
    public function testParseStopsBeforeAMembershipTestBecauseGqlWritesNone(): void
    {
        $tokens = TokenReader::of('a IN [1]');

        self::assertEquals(new VariableExpression('a'), (new ExpressionParser($tokens))->parse());
        self::assertEquals(new Token(TokenKind::Name, 'IN', 'IN', 1, 3, 2), $tokens->current());
    }

    /**
     * @throws GqlException
     */
    public function testParseOrReadsADisjunction(): void
    {
        self::assertEquals(
            new BinaryExpression(BinaryOperator::Or, new VariableExpression('a'), new VariableExpression('b')),
            (new ExpressionParser(TokenReader::of('a OR b')))->parseOr(),
        );
    }

    /**
     * @throws GqlException
     */
    public function testParseOrReadsAnExclusiveDisjunction(): void
    {
        self::assertEquals(
            new BinaryExpression(BinaryOperator::Xor, new VariableExpression('a'), new VariableExpression('b')),
            (new ExpressionParser(TokenReader::of('a XOR b')))->parseOr(),
        );
    }

    /**
     * @throws GqlException
     */
    public function testParseOrReadsAValueWithNoDisjunctionAsThatValue(): void
    {
        self::assertEquals(new VariableExpression('a'), (new ExpressionParser(TokenReader::of('a')))->parseOr());
    }

    /**
     * @throws GqlException
     */
    public function testParseAndReadsAConjunctionLeftToRight(): void
    {
        self::assertEquals(
            new BinaryExpression(
                BinaryOperator::And,
                new BinaryExpression(BinaryOperator::And, new VariableExpression('a'), new VariableExpression('b')),
                new VariableExpression('c'),
            ),
            (new ExpressionParser(TokenReader::of('a AND b AND c')))->parseAnd(),
        );
    }

    /**
     * @throws GqlException
     */
    public function testParseNotReadsANegation(): void
    {
        self::assertEquals(
            new UnaryExpression(UnaryOperator::Not, new VariableExpression('a')),
            (new ExpressionParser(TokenReader::of('NOT a')))->parseNot(),
        );
    }

    /**
     * @throws GqlException
     */
    public function testParseNotRefusesASecondNegation(): void
    {
        $this->expectException(GqlException::class);
        $this->expectExceptionMessage('expected an expression, since GQL writes one NOT before a test and a second one inside parentheses');

        (new ExpressionParser(TokenReader::of('NOT NOT a')))->parseNot();
    }

    /**
     * @throws GqlException
     */
    public function testParseNotReadsASecondNegationWrittenInParentheses(): void
    {
        self::assertEquals(
            new UnaryExpression(
                UnaryOperator::Not,
                new UnaryExpression(UnaryOperator::Not, new VariableExpression('a')),
            ),
            (new ExpressionParser(TokenReader::of('NOT (NOT a)')))->parseNot(),
        );
    }

    /**
     * @throws GqlException
     */
    #[DataProvider('providerTruthValueTests')]
    public function testParseTestReadsATruthValueTest(string $written, Expression $expected): void
    {
        self::assertEquals($expected, (new ExpressionParser(TokenReader::of($written)))->parseTest());
    }

    /**
     * @return iterable<string, array{string, Expression}>
     */
    public static function providerTruthValueTests(): iterable
    {
        yield 'for truth' => ['a IS TRUE', new UnaryExpression(UnaryOperator::IsTrue, new VariableExpression('a'))];

        yield 'for anything but truth' => ['a IS NOT TRUE', new UnaryExpression(UnaryOperator::IsNotTrue, new VariableExpression('a'))];

        yield 'for falsehood' => ['a IS FALSE', new UnaryExpression(UnaryOperator::IsFalse, new VariableExpression('a'))];

        yield 'for anything but falsehood' => ['a IS NOT FALSE', new UnaryExpression(UnaryOperator::IsNotFalse, new VariableExpression('a'))];

        yield 'for the undecided' => ['a IS UNKNOWN', new UnaryExpression(UnaryOperator::IsUnknown, new VariableExpression('a'))];

        yield 'for anything decided' => ['a IS NOT UNKNOWN', new UnaryExpression(UnaryOperator::IsNotUnknown, new VariableExpression('a'))];

        yield 'written in lower case' => ['a is not true', new UnaryExpression(UnaryOperator::IsNotTrue, new VariableExpression('a'))];
    }

    /**
     * @throws GqlException
     */
    public function testParseTestReadsAValueThatIsNotTestedAsThatValue(): void
    {
        self::assertEquals(new VariableExpression('a'), (new ExpressionParser(TokenReader::of('a')))->parseTest());
    }

    /**
     * @throws GqlException
     */
    public function testParseTestRefusesATestOfAnythingButATruthValue(): void
    {
        $this->expectException(GqlException::class);
        $this->expectExceptionMessage('expected TRUE, FALSE or UNKNOWN at line 1, column 10 (found "1")');

        (new ExpressionParser(TokenReader::of('a IS NOT 1')))->parseTest();
    }

    /**
     * @throws GqlException
     */
    public function testParseComparisonReadsAComparison(): void
    {
        self::assertEquals(
            new BinaryExpression(BinaryOperator::Greater, new VariableExpression('a'), new VariableExpression('b')),
            (new ExpressionParser(TokenReader::of('a > b')))->parseComparison(),
        );
    }

    /**
     * @throws GqlException
     */
    public function testParseComparisonReadsOneComparisonAndStopsBeforeASecond(): void
    {
        $tokens = TokenReader::of('a < b < c');

        self::assertEquals(
            new BinaryExpression(BinaryOperator::Less, new VariableExpression('a'), new VariableExpression('b')),
            (new ExpressionParser($tokens))->parseComparison(),
        );
        self::assertEquals(new Token(TokenKind::Symbol, '<', '<', 1, 7, 6), $tokens->current());
    }

    /**
     * @throws GqlException
     */
    #[DataProvider('providerComparingOperators')]
    public function testComparisonInReadsTheComparingOperatorStandingAtAReader(string $written, BinaryOperator $operator): void
    {
        self::assertSame($operator, ExpressionParser::comparisonIn(TokenReader::of($written)));
    }

    /**
     * @return iterable<string, array{string, BinaryOperator}>
     */
    public static function providerComparingOperators(): iterable
    {
        yield 'equality' => ['= 3', BinaryOperator::Equal];

        yield 'inequality' => ['<> 3', BinaryOperator::NotEqual];

        yield 'ordering' => ['< 3', BinaryOperator::Less];

        yield 'ordering or equality' => ['<= 3', BinaryOperator::LessOrEqual];

        yield 'the other ordering' => ['> 3', BinaryOperator::Greater];

        yield 'the other ordering or equality' => ['>= 3', BinaryOperator::GreaterOrEqual];
    }

    /**
     * @throws GqlException
     */
    public function testComparisonInTakesTheOperatorItReads(): void
    {
        $tokens = TokenReader::of('>= 3');
        ExpressionParser::comparisonIn($tokens);

        self::assertEquals(new Token(TokenKind::Integer, '3', '3', 1, 4, 3), $tokens->current());
    }

    /**
     * @throws GqlException
     */
    #[DataProvider('providerOperatorsThatAreNotComparisons')]
    public function testComparisonInReadsNothingWhereNoComparingOperatorStands(string $written): void
    {
        $tokens = TokenReader::of($written);

        self::assertNull(ExpressionParser::comparisonIn($tokens));
        self::assertSame(0, $tokens->position());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function providerOperatorsThatAreNotComparisons(): iterable
    {
        yield 'a membership test, which GQL does not write' => ['IN [1]'];

        yield 'an inequality GQL does not write' => ['!= 3'];

        yield 'an addition' => ['+ 3'];
    }

    /**
     * @throws GqlException
     */
    public function testParseAdditiveReadsAdditionSubtractionAndConcatenationLeftToRight(): void
    {
        self::assertEquals(
            new BinaryExpression(
                BinaryOperator::Concatenate,
                new BinaryExpression(
                    BinaryOperator::Subtract,
                    new BinaryExpression(BinaryOperator::Add, new VariableExpression('a'), new VariableExpression('b')),
                    new VariableExpression('c'),
                ),
                new VariableExpression('d'),
            ),
            (new ExpressionParser(TokenReader::of('a + b - c || d')))->parseAdditive(),
        );
    }

    /**
     * @throws GqlException
     */
    public function testParseMultiplicativeReadsMultiplicationAndDivisionLeftToRight(): void
    {
        self::assertEquals(
            new BinaryExpression(
                BinaryOperator::Divide,
                new BinaryExpression(BinaryOperator::Multiply, new VariableExpression('a'), new VariableExpression('b')),
                new VariableExpression('c'),
            ),
            (new ExpressionParser(TokenReader::of('a * b / c')))->parseMultiplicative(),
        );
    }

    /**
     * @throws GqlException
     */
    #[DataProvider('providerSignedValues')]
    public function testParseUnaryReadsASignWrittenBeforeAValue(string $written, Expression $expected): void
    {
        self::assertEquals($expected, (new ExpressionParser(TokenReader::of($written)))->parseUnary());
    }

    /**
     * @return iterable<string, array{string, Expression}>
     */
    public static function providerSignedValues(): iterable
    {
        yield 'a sign that changes nothing' => ['+a', new UnaryExpression(UnaryOperator::Identity, new VariableExpression('a'))];

        yield 'a reversed sign' => ['-a', new UnaryExpression(UnaryOperator::Negate, new VariableExpression('a'))];

        yield 'two signs, written apart so as not to start a note' => [
            '- -a',
            new UnaryExpression(UnaryOperator::Negate, new UnaryExpression(UnaryOperator::Negate, new VariableExpression('a'))),
        ];

        yield 'a reversed sign before an exact number' => [
            '-1.5',
            new UnaryExpression(UnaryOperator::Negate, new LiteralExpression(new DecimalDatum(15, 1))),
        ];

        yield 'no sign at all' => ['TRUE', new LiteralExpression(new BooleanDatum(true))];
    }
}
