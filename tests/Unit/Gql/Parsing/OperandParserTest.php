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
#[UsesClass(ExpressionParser::class)]
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
final class OperandParserTest extends TestCase
{
    /**
     * @throws GqlException
     */
    public function testParseReadsAnOperandTogetherWithWhateverAttachesToIt(): void
    {
        self::assertSame('p.firstName', ExpressionSpelling::of('p.firstName'));
    }

    /**
     * @throws GqlException
     */
    #[DataProvider('providerThingsThatAttachToAValue')]
    public function testParseAttachedReadsWhateverAttachesToAValue(string $written, string $shape): void
    {
        self::assertSame($shape, ExpressionSpelling::of($written));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function providerThingsThatAttachToAValue(): iterable
    {
        yield 'a property' => ['p.name', 'p.name'];

        yield 'a property of a property' => ['p.a.b', 'p.a.b'];

        yield 'an index' => ['e[0]', 'e[0]'];

        yield 'a property of an indexed value' => ['e[0].line', 'e[0].line'];

        yield 'a test for absence' => ['p.name IS NULL', '(p.name IS NULL)'];

        yield 'a test for presence' => ['p.name IS NOT NULL', '(p.name IS NOT NULL)'];
    }

    /**
     * @throws GqlException
     */
    #[DataProvider('providerValuesWrittenOnTheirOwn')]
    public function testParsePrimaryReadsAValueWrittenOnItsOwn(string $written, string $shape): void
    {
        self::assertSame($shape, ExpressionSpelling::of($written));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function providerValuesWrittenOnTheirOwn(): iterable
    {
        yield 'a name that is not called' => ['p', 'p'];

        yield 'a name that is called' => ['upper(p)', 'upper(p)'];

        yield 'a name in backticks' => ['`return`', 'return'];

        yield 'a parenthesised expression' => ['(a)', 'a'];
    }

    /**
     * @throws GqlException
     */
    public function testParsePrimaryReportsSomethingThatIsNotAValue(): void
    {
        $this->expectException(GqlException::class);
        $this->expectExceptionMessage('expected an expression');

        ExpressionSpelling::of(',');
    }

    /**
     * @throws GqlException
     */
    #[DataProvider('providerLiterals')]
    public function testParseLiteralReadsAValueWrittenDirectlyIntoTheQuery(string $written, string $shape): void
    {
        self::assertSame($shape, ExpressionSpelling::of($written));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function providerLiterals(): iterable
    {
        yield 'a whole number' => ['42', '42'];

        yield 'an approximate number' => ['1.5', '1.5'];

        yield 'a string' => ["'a'", "'a'"];

        yield 'truth' => ['TRUE', 'TRUE'];

        yield 'its opposite' => ['FALSE', 'FALSE'];

        yield 'the absence of a value' => ['NULL', 'NULL'];

        yield 'the unknown truth value, which is the same thing' => ['UNKNOWN', 'NULL'];
    }

    public function testApproximateReadsASuffixedNumberWithoutChangingWhatItIsWorth(): void
    {
        self::assertSame(1.0, OperandParser::approximate('1.0d')->value);
    }

    /**
     * @throws GqlException
     */
    public function testParseListReadsAListWrittenOutInTheQuery(): void
    {
        self::assertSame("['a','b']", ExpressionSpelling::of("['a', 'b']"));
    }

    /**
     * @throws GqlException
     */
    public function testParseListReadsAListThatHoldsNothing(): void
    {
        self::assertSame('[]', ExpressionSpelling::of('[]'));
    }

    /**
     * @throws GqlException
     */
    #[DataProvider('providerCalls')]
    public function testParseCallReadsTheArgumentsAFunctionIsAppliedTo(string $written, string $shape): void
    {
        self::assertSame($shape, ExpressionSpelling::of($written));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function providerCalls(): iterable
    {
        yield 'over rows rather than over a value' => ['count(*)', 'count(*)'];

        yield 'dropping repeated values' => ['count(DISTINCT p)', 'count(DISTINCT p)'];

        yield 'over nothing at all' => ['zoned_datetime()', 'zoned_datetime()'];

        yield 'over several values' => ["coalesce(a, b, 'c')", "coalesce(a,b,'c')"];
    }

    /**
     * @throws GqlException
     */
    public function testParseCaseReadsAChoiceTriedBranchByBranch(): void
    {
        self::assertSame(
            "CASE WHEN a THEN 'x' ELSE 'y' END",
            ExpressionSpelling::of("CASE WHEN a THEN 'x' ELSE 'y' END"),
        );
    }

    /**
     * @throws GqlException
     */
    public function testParseCaseReadsAChoiceAgainstOneValue(): void
    {
        self::assertSame(
            "CASE a WHEN 1 THEN 'x' END",
            ExpressionSpelling::of("CASE a WHEN 1 THEN 'x' END"),
        );
    }

    /**
     * @throws GqlException
     */
    public function testParseCaseReportsAChoiceThatOffersNoBranch(): void
    {
        $this->expectException(GqlException::class);
        $this->expectExceptionMessage('expected WHEN');

        ExpressionSpelling::of('CASE END');
    }
}
