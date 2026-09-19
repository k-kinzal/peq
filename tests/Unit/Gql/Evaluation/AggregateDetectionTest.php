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
use App\Gql\Evaluation\AggregateDetection;
use App\Gql\GqlException;
use App\Gql\Invocation\AggregateCatalog;
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
#[CoversClass(AggregateDetection::class)]
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
#[UsesClass(AggregateCatalog::class)]
#[Small]
final class AggregateDetectionTest extends TestCase
{
    /**
     * @throws GqlException
     */
    #[DataProvider('providerExpressionsAndWhetherTheySummarise')]
    public function testWithinReportsWhetherAnExpressionSummarisesRows(string $written, bool $summarises): void
    {
        self::assertSame($summarises, AggregateDetection::within(ExpressionWorth::parse($written)));
    }

    /**
     * @return iterable<string, array{string, bool}>
     */
    public static function providerExpressionsAndWhetherTheySummarise(): iterable
    {
        yield 'a projection that counts' => ['count(*)', true];

        yield 'a projection that reads a property' => ['p.name', false];

        yield 'a summary buried inside a comparison' => ['count(*) > 1', true];

        yield 'a summary buried inside a join' => ["'found ' || count(*)", true];

        yield 'a summary buried inside a sign' => ['-count(*)', true];

        yield 'a summary buried inside a list' => ['[1, count(*)]', true];

        yield 'a summary buried inside an indexing' => ['xs[count(*)]', true];

        yield 'a summary buried inside a property subject' => ['collect_list(p)[0].name', true];

        yield 'a summary buried inside an ordinary call' => ['size(collect_list(p))', true];

        yield 'a summary buried inside a choice' => ["CASE WHEN count(*) > 1 THEN 'many' ELSE 'one' END", true];

        yield 'a summary buried in what a choice is about' => ["CASE count(*) WHEN 1 THEN 'one' END", true];

        yield 'a summary buried in a fallback' => ["CASE WHEN TRUE THEN 'x' ELSE count(*) END", true];

        yield 'a choice whose branches read properties' => ['CASE WHEN a THEN b ELSE c END', false];

        yield 'an ordinary call' => ["upper('a')", false];

        yield 'a literal' => ['42', false];
    }

    public function testWithinAnyFindsNoSummaryInNothingAtAll(): void
    {
        self::assertFalse(AggregateDetection::withinAny([]));
    }

    /**
     * @throws GqlException
     */
    public function testWithinAnyFindsASummaryInAnyOneOfSeveralExpressions(): void
    {
        $expressions = [ExpressionWorth::parse('p.name'), ExpressionWorth::parse('count(*)')];

        self::assertTrue(AggregateDetection::withinAny($expressions));
    }

    /**
     * @throws GqlException
     */
    public function testWithinCaseFindsNoSummaryInAChoiceThatReadsProperties(): void
    {
        $written = ExpressionWorth::parse('CASE WHEN a THEN b END');
        self::assertInstanceOf(CaseExpression::class, $written);

        self::assertFalse(AggregateDetection::withinCase($written));
    }
}
