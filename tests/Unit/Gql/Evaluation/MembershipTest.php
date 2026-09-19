<?php

declare(strict_types=1);

namespace Tests\Unit\Gql\Evaluation;

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
use App\Gql\Evaluation\Logic;
use App\Gql\Evaluation\Membership;
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
#[CoversClass(Membership::class)]
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
#[UsesClass(Logic::class)]
#[Small]
final class MembershipTest extends TestCase
{
    /**
     * @throws GqlException
     */
    #[DataProvider('providerMemberships')]
    public function testOfReportsWhetherAValueIsAmongTheValuesOfAList(string $written, string $expected): void
    {
        self::assertSame($expected, ExpressionWorth::of($written));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function providerMemberships(): iterable
    {
        yield 'a value that is there' => ["'a' IN ['a', 'b']", 'TRUE'];

        yield 'a value that is not there, among known values' => ["'c' IN ['a', 'b']", 'FALSE'];

        yield 'a list holding an absent value cannot rule anything out' => ["'c' IN ['a', NULL]", 'NULL'];

        yield 'a list holding an absent value still finds what is there' => ["'a' IN ['a', NULL]", 'TRUE'];

        yield 'nothing is looked for in an absent list' => ["'a' IN NULL", 'NULL'];

        yield 'an absent value is looked for nowhere' => ['NULL IN [1]', 'NULL'];

        yield 'nothing is in an empty list' => ["'a' IN []", 'FALSE'];

        yield 'what is not in a list is reported as not in it' => ["'c' NOT IN ['a']", 'TRUE'];
    }

    /**
     * @throws GqlException
     */
    public function testOfReportsSomethingThatIsNotAListToLookIn(): void
    {
        $this->expectException(GqlException::class);
        $this->expectExceptionMessage('a list was expected, and a STRING was given');

        ExpressionWorth::of("'a' IN 'abc'");
    }
}
