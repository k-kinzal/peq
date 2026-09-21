<?php

declare(strict_types=1);

namespace Tests\Unit\Gql\Parsing;

use App\Gql\Datum\BooleanDatum;
use App\Gql\Datum\DatumKind;
use App\Gql\Datum\DecimalDatum;
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
use App\Gql\Parsing\NameReader;
use App\Gql\Parsing\OperandParser;
use App\Gql\Parsing\TokenReader;
use App\Gql\ReservedWords;
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
#[CoversClass(OperandParser::class)]
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
#[UsesClass(ExpressionParser::class)]
#[UsesClass(ReservedWords::class)]
#[UsesClass(BooleanDatum::class)]
#[UsesClass(DatumKind::class)]
#[UsesClass(DecimalDatum::class)]
#[UsesClass(FloatDatum::class)]
#[UsesClass(IntegerDatum::class)]
#[UsesClass(NullDatum::class)]
#[UsesClass(StringDatum::class)]
#[UsesClass(BinaryExpression::class)]
#[UsesClass(BinaryOperator::class)]
#[UsesClass(CallExpression::class)]
#[UsesClass(CaseBranch::class)]
#[UsesClass(CaseExpression::class)]
#[UsesClass(ListExpression::class)]
#[UsesClass(LiteralExpression::class)]
#[UsesClass(PropertyExpression::class)]
#[UsesClass(UnaryExpression::class)]
#[UsesClass(UnaryOperator::class)]
#[UsesClass(VariableExpression::class)]
#[Small]
final class OperandParserTest extends TestCase
{
    /**
     * @throws GqlException
     */
    public function testParseReadsAnOperandTogetherWithWhateverAttachesToIt(): void
    {
        $tokens = TokenReader::of('p.name IS NULL');

        self::assertEquals(
            new UnaryExpression(UnaryOperator::IsNull, new PropertyExpression(new VariableExpression('p'), 'name')),
            (new OperandParser($tokens, new ExpressionParser($tokens)))->parse(),
        );
    }

    /**
     * @throws GqlException
     */
    #[DataProvider('providerThingsThatAttachToAValue')]
    public function testParseAttachedReadsWhateverAttachesToAValue(string $written, Expression $expected): void
    {
        $tokens = TokenReader::of($written);

        self::assertEquals($expected, (new OperandParser($tokens, new ExpressionParser($tokens)))->parseAttached(new VariableExpression('p')));
    }

    /**
     * @return iterable<string, array{string, Expression}>
     */
    public static function providerThingsThatAttachToAValue(): iterable
    {
        yield 'a property' => ['.name', new PropertyExpression(new VariableExpression('p'), 'name')];

        yield 'a property of a property' => ['.a.b', new PropertyExpression(new PropertyExpression(new VariableExpression('p'), 'a'), 'b')];

        yield 'a property GQL reserves the name of, in back quotes' => ['.`value`', new PropertyExpression(new VariableExpression('p'), 'value')];

        yield 'a property GQL reserves the name of, in double quotes' => ['."value"', new PropertyExpression(new VariableExpression('p'), 'value')];

        yield 'a test for absence' => [' IS NULL', new UnaryExpression(UnaryOperator::IsNull, new VariableExpression('p'))];

        yield 'a test for presence' => [' IS NOT NULL', new UnaryExpression(UnaryOperator::IsNotNull, new VariableExpression('p'))];

        yield 'a test for absence of a property' => [
            '.name IS NULL',
            new UnaryExpression(UnaryOperator::IsNull, new PropertyExpression(new VariableExpression('p'), 'name')),
        ];

        yield 'nothing' => ['', new VariableExpression('p')];
    }

    /**
     * @throws GqlException
     */
    #[DataProvider('providerThingsThatDoNotAttachToAValue')]
    public function testParseAttachedLeavesWhatDoesNotAttachToAValue(string $written): void
    {
        $tokens = TokenReader::of($written);

        self::assertEquals(new VariableExpression('p'), (new OperandParser($tokens, new ExpressionParser($tokens)))->parseAttached(new VariableExpression('p')));
        self::assertSame(0, $tokens->position());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function providerThingsThatDoNotAttachToAValue(): iterable
    {
        yield 'a truth-value test, which tests a whole predicate' => ['IS TRUE'];

        yield 'a subscript, which GQL does not write' => ['[0]'];

        yield 'an operator' => ['+ 1'];
    }

    /**
     * @throws GqlException
     */
    public function testParseAttachedRefusesAPropertyNamedByAWordGqlReserves(): void
    {
        $tokens = TokenReader::of('.value');

        $this->expectException(GqlException::class);
        $this->expectExceptionMessage('GQL reserves "VALUE", so it is not a name here: write it in back quotes to use it as a name');

        (new OperandParser($tokens, new ExpressionParser($tokens)))->parseAttached(new VariableExpression('p'));
    }

    /**
     * @throws GqlException
     */
    #[DataProvider('providerNullTestsAndWhatLooksLikeThem')]
    public function testAtNullTestReportsWhetherANullTestStandsHere(string $written, bool $expected): void
    {
        $tokens = TokenReader::of($written);

        self::assertSame($expected, (new OperandParser($tokens, new ExpressionParser($tokens)))->atNullTest());
    }

    /**
     * @return iterable<string, array{string, bool}>
     */
    public static function providerNullTestsAndWhatLooksLikeThem(): iterable
    {
        yield 'a test for absence' => ['IS NULL', true];

        yield 'a test for presence' => ['IS NOT NULL', true];

        yield 'a test for truth' => ['IS TRUE', false];

        yield 'a test for anything but the undecided' => ['IS NOT UNKNOWN', false];

        yield 'the absence of a value on its own' => ['NULL', false];
    }

    /**
     * @throws GqlException
     */
    public function testAtNullTestLooksAheadWithoutTakingAnything(): void
    {
        $tokens = TokenReader::of('IS NOT NULL');

        self::assertTrue((new OperandParser($tokens, new ExpressionParser($tokens)))->atNullTest());
        self::assertSame(0, $tokens->position());
    }

    /**
     * @throws GqlException
     */
    #[DataProvider('providerValuesWrittenOnTheirOwn')]
    public function testParsePrimaryReadsAValueWrittenOnItsOwn(string $written, Expression $expected): void
    {
        $tokens = TokenReader::of($written);

        self::assertEquals($expected, (new OperandParser($tokens, new ExpressionParser($tokens)))->parsePrimary());
    }

    /**
     * @return iterable<string, array{string, Expression}>
     */
    public static function providerValuesWrittenOnTheirOwn(): iterable
    {
        yield 'a name that is not called' => ['p', new VariableExpression('p')];

        yield 'a name that is called' => ['upper(p)', new CallExpression('upper', [new VariableExpression('p')])];

        yield 'a word GQL reserves, called' => ['abs(p)', new CallExpression('abs', [new VariableExpression('p')])];

        yield 'a parenthesised expression' => [
            '(a + b)',
            new BinaryExpression(BinaryOperator::Add, new VariableExpression('a'), new VariableExpression('b')),
        ];

        yield 'a literal' => ["'a'", new LiteralExpression(new StringDatum('a'))];

        yield 'a list' => ['[1]', new ListExpression([new LiteralExpression(new IntegerDatum(1))])];

        yield 'a choice' => [
            'CASE WHEN a THEN 1 END',
            new CaseExpression(null, [new CaseBranch(new VariableExpression('a'), new LiteralExpression(new IntegerDatum(1)))]),
        ];

        yield 'a value without the property after it' => ['p.name', new VariableExpression('p')];
    }

    /**
     * @throws GqlException
     */
    public function testParsePrimaryReportsSomethingThatIsNotAValue(): void
    {
        $tokens = TokenReader::of(',');

        $this->expectException(GqlException::class);
        $this->expectExceptionMessage('expected an expression at line 1, column 1 (found ",")');

        (new OperandParser($tokens, new ExpressionParser($tokens)))->parsePrimary();
    }

    /**
     * @throws GqlException
     */
    public function testParsePrimaryRefusesAVariableNamedByAWordGqlReserves(): void
    {
        $tokens = TokenReader::of('value');

        $this->expectException(GqlException::class);
        $this->expectExceptionMessage('GQL reserves "VALUE", so it is not a name here: a query binds a name to a word GQL leaves free');

        (new OperandParser($tokens, new ExpressionParser($tokens)))->parsePrimary();
    }

    /**
     * @throws GqlException
     */
    public function testParsePrimaryReportsAParenthesisThatIsNeverClosed(): void
    {
        $tokens = TokenReader::of('(a');

        $this->expectException(GqlException::class);
        $this->expectExceptionMessage('expected ")" at line 1, column 3 (found the end of the query)');

        (new OperandParser($tokens, new ExpressionParser($tokens)))->parsePrimary();
    }

    /**
     * @throws GqlException
     */
    #[DataProvider('providerLiterals')]
    public function testParseLiteralReadsAValueWrittenDirectlyIntoTheQuery(string $written, LiteralExpression $expected): void
    {
        $tokens = TokenReader::of($written);

        self::assertEquals($expected, (new OperandParser($tokens, new ExpressionParser($tokens)))->parseLiteral());
    }

    /**
     * @return iterable<string, array{string, LiteralExpression}>
     */
    public static function providerLiterals(): iterable
    {
        yield 'a whole number' => ['42', new LiteralExpression(new IntegerDatum(42))];

        yield 'a whole number with leading zeros' => ['007', new LiteralExpression(new IntegerDatum(7))];

        yield 'a number with digits after its point, which is exact' => ['1.5', new LiteralExpression(new DecimalDatum(15, 1))];

        yield 'a whole number marked exact, which is an integer' => ['15M', new LiteralExpression(new IntegerDatum(15))];

        yield 'a number with digits after its point marked exact' => ['1.5M', new LiteralExpression(new DecimalDatum(15, 1))];

        yield 'an exponent marked exact that leaves nothing after the point' => ['1e3M', new LiteralExpression(new IntegerDatum(1000))];

        yield 'an exponent marked exact that moves the point left' => ['1.5e-1M', new LiteralExpression(new DecimalDatum(15, 2))];

        yield 'a number with an exponent, which is approximate' => ['1e3', new LiteralExpression(new FloatDatum(1000.0))];

        yield 'a number marked as a float' => ['1.5f', new LiteralExpression(new FloatDatum(1.5))];

        yield 'a number marked as a double' => ['1.0d', new LiteralExpression(new FloatDatum(1.0))];

        yield 'a string' => ["'a'", new LiteralExpression(new StringDatum('a'))];

        yield 'truth' => ['TRUE', new LiteralExpression(new BooleanDatum(true))];

        yield 'falsehood, however it is cased' => ['false', new LiteralExpression(new BooleanDatum(false))];

        yield 'the absence of a value' => ['NULL', new LiteralExpression(new NullDatum())];

        yield 'the unknown truth value, which is the same thing' => ['UNKNOWN', new LiteralExpression(new NullDatum())];
    }

    /**
     * @throws GqlException
     */
    public function testParseLiteralLeavesWhatIsNotALiteral(): void
    {
        $tokens = TokenReader::of('p');

        self::assertNull((new OperandParser($tokens, new ExpressionParser($tokens)))->parseLiteral());
        self::assertSame(0, $tokens->position());
    }

    /**
     * @throws GqlException
     */
    public function testParseLiteralRefusesAWholeNumberTooLongToHold(): void
    {
        $tokens = TokenReader::of('99999999999999999999');

        $this->expectException(GqlException::class);
        $this->expectExceptionMessage('[22003] error: data exception - numeric value out of range: 99999999999999999999 has more digits than an exact number can hold');

        (new OperandParser($tokens, new ExpressionParser($tokens)))->parseLiteral();
    }

    #[DataProvider('providerApproximateNumbers')]
    public function testApproximateReadsANumberWithoutChangingWhatItIsWorth(string $written, FloatDatum $expected): void
    {
        self::assertEquals($expected, OperandParser::approximate($written));
    }

    /**
     * @return iterable<string, array{string, FloatDatum}>
     */
    public static function providerApproximateNumbers(): iterable
    {
        yield 'marked as a double' => ['1.0d', new FloatDatum(1.0)];

        yield 'marked as a float in upper case' => ['2.5F', new FloatDatum(2.5)];

        yield 'written with an exponent' => ['1e3', new FloatDatum(1000.0)];

        yield 'written with a negative exponent and a suffix' => ['15e-1f', new FloatDatum(1.5)];
    }

    /**
     * @throws GqlException
     */
    #[DataProvider('providerLists')]
    public function testParseListReadsAListWrittenOutInTheQuery(string $written, ListExpression $expected): void
    {
        $tokens = TokenReader::of($written);

        self::assertEquals($expected, (new OperandParser($tokens, new ExpressionParser($tokens)))->parseList());
    }

    /**
     * @return iterable<string, array{string, ListExpression}>
     */
    public static function providerLists(): iterable
    {
        yield 'holding values' => [
            "['a', 'b']",
            new ListExpression([new LiteralExpression(new StringDatum('a')), new LiteralExpression(new StringDatum('b'))]),
        ];

        yield 'holding something computed' => [
            '[p.name]',
            new ListExpression([new PropertyExpression(new VariableExpression('p'), 'name')]),
        ];

        yield 'holding nothing' => ['[]', new ListExpression()];
    }

    /**
     * @throws GqlException
     */
    public function testParseListReportsAListThatIsNeverClosed(): void
    {
        $tokens = TokenReader::of('[1, 2');

        $this->expectException(GqlException::class);
        $this->expectExceptionMessage('expected "]" at line 1, column 6 (found the end of the query)');

        (new OperandParser($tokens, new ExpressionParser($tokens)))->parseList();
    }

    /**
     * @throws GqlException
     */
    public function testParseCallReadsCountOverRowsRatherThanOverAValue(): void
    {
        $tokens = TokenReader::of('(*)');

        self::assertEquals(new CallExpression('count', [], false, true), (new OperandParser($tokens, new ExpressionParser($tokens)))->parseCall('count'));
    }

    /**
     * @throws GqlException
     */
    public function testParseCallReadsAnAggregateThatDropsRepeatedValues(): void
    {
        $tokens = TokenReader::of('(DISTINCT p)');

        self::assertEquals(new CallExpression('sum', [new VariableExpression('p')], true), (new OperandParser($tokens, new ExpressionParser($tokens)))->parseCall('sum'));
    }

    /**
     * @throws GqlException
     */
    public function testParseCallReadsAnAggregateThatSaysItKeepsEveryValue(): void
    {
        $tokens = TokenReader::of('(ALL p)');

        self::assertEquals(new CallExpression('sum', [new VariableExpression('p')]), (new OperandParser($tokens, new ExpressionParser($tokens)))->parseCall('sum'));
    }

    /**
     * @throws GqlException
     */
    public function testParseCallRefusesAnAsteriskAnywhereButCount(): void
    {
        $tokens = TokenReader::of('(*)');

        $this->expectException(GqlException::class);
        $this->expectExceptionMessage('expected an argument, since GQL writes an asterisk only as COUNT(*)');

        (new OperandParser($tokens, new ExpressionParser($tokens)))->parseCall('sum');
    }

    /**
     * @throws GqlException
     */
    public function testParseCallRefusesCountingDistinctRows(): void
    {
        $tokens = TokenReader::of('(DISTINCT *)');

        $this->expectException(GqlException::class);
        $this->expectExceptionMessage('expected an argument, since GQL writes an asterisk only as COUNT(*)');

        (new OperandParser($tokens, new ExpressionParser($tokens)))->parseCall('count');
    }

    /**
     * @throws GqlException
     */
    public function testParseCallRefusesASetQuantifierInAFunctionThatIsNoAggregate(): void
    {
        $tokens = TokenReader::of('(DISTINCT p)');

        $this->expectException(GqlException::class);
        $this->expectExceptionMessage('expected an argument, since only an aggregate function is written with DISTINCT or ALL');

        (new OperandParser($tokens, new ExpressionParser($tokens)))->parseCall('upper');
    }

    /**
     * @throws GqlException
     */
    #[DataProvider('providerArgumentsAndTheCallTheyMake')]
    public function testParseCallReadsTheArgumentsAFunctionIsAppliedTo(string $written, CallExpression $expected): void
    {
        $tokens = TokenReader::of($written);

        self::assertEquals($expected, (new OperandParser($tokens, new ExpressionParser($tokens)))->parseCall('f'));
    }

    /**
     * @return iterable<string, array{string, CallExpression}>
     */
    public static function providerArgumentsAndTheCallTheyMake(): iterable
    {

        yield 'over nothing at all' => ['()', new CallExpression('f')];

        yield 'over several values' => [
            "(a, b, 'c')",
            new CallExpression('f', [new VariableExpression('a'), new VariableExpression('b'), new LiteralExpression(new StringDatum('c'))]),
        ];
    }

    /**
     * @throws GqlException
     */
    public function testParseCallReportsArgumentsThatAreNeverClosed(): void
    {
        $tokens = TokenReader::of('(a');

        $this->expectException(GqlException::class);
        $this->expectExceptionMessage('expected ")" at line 1, column 3 (found the end of the query)');

        (new OperandParser($tokens, new ExpressionParser($tokens)))->parseCall('f');
    }

    /**
     * @throws GqlException
     */
    #[DataProvider('providerChoices')]
    public function testParseCaseReadsAChoiceBetweenValues(string $written, CaseExpression $expected): void
    {
        $tokens = TokenReader::of($written);

        self::assertEquals($expected, (new OperandParser($tokens, new ExpressionParser($tokens)))->parseCase());
    }

    /**
     * @return iterable<string, array{string, CaseExpression}>
     */
    public static function providerChoices(): iterable
    {
        yield 'tried branch by branch, with a fallback' => [
            "CASE WHEN a THEN 'x' ELSE 'y' END",
            new CaseExpression(
                null,
                [new CaseBranch(new VariableExpression('a'), new LiteralExpression(new StringDatum('x')))],
                new LiteralExpression(new StringDatum('y')),
            ),
        ];

        yield 'against one value, without a fallback' => [
            "CASE a WHEN 1 THEN 'x' WHEN 2 THEN 'y' END",
            new CaseExpression(
                new VariableExpression('a'),
                [
                    new CaseBranch(new LiteralExpression(new IntegerDatum(1)), new LiteralExpression(new StringDatum('x'))),
                    new CaseBranch(new LiteralExpression(new IntegerDatum(2)), new LiteralExpression(new StringDatum('y'))),
                ],
            ),
        ];
    }

    /**
     * @throws GqlException
     */
    public function testParseCaseReportsAChoiceThatOffersNoBranch(): void
    {
        $tokens = TokenReader::of('CASE END');

        $this->expectException(GqlException::class);
        $this->expectExceptionMessage('expected WHEN');

        (new OperandParser($tokens, new ExpressionParser($tokens)))->parseCase();
    }

    /**
     * @throws GqlException
     */
    public function testParseCaseReportsAChoiceThatIsNeverEnded(): void
    {
        $tokens = TokenReader::of('CASE WHEN a THEN 1');

        $this->expectException(GqlException::class);
        $this->expectExceptionMessage('expected END at line 1, column 19 (found the end of the query)');

        (new OperandParser($tokens, new ExpressionParser($tokens)))->parseCase();
    }
}
