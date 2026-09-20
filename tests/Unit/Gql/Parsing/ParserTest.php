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
use App\Gql\Parsing\LabelParser;
use App\Gql\Parsing\OperandParser;
use App\Gql\Parsing\Parser;
use App\Gql\Parsing\PatternParser;
use App\Gql\Parsing\ResultParser;
use App\Gql\Parsing\TokenReader;
use App\Gql\StatusCode;
use App\Gql\Syntax\Clause\FilterClause;
use App\Gql\Syntax\Clause\LetClause;
use App\Gql\Syntax\Clause\MatchClause;
use App\Gql\Syntax\Clause\OrderByClause;
use App\Gql\Syntax\Clause\PageClause;
use App\Gql\Syntax\Clause\Projection;
use App\Gql\Syntax\Clause\ReturnClause;
use App\Gql\Syntax\Clause\SortDirection;
use App\Gql\Syntax\Clause\SortKey;
use App\Gql\Syntax\Clause\VariableBinding;
use App\Gql\Syntax\Expression\BinaryExpression;
use App\Gql\Syntax\Expression\BinaryOperator;
use App\Gql\Syntax\Expression\CallExpression;
use App\Gql\Syntax\Expression\LiteralExpression;
use App\Gql\Syntax\Expression\PropertyExpression;
use App\Gql\Syntax\Expression\VariableExpression;
use App\Gql\Syntax\Pattern\EdgeDirection;
use App\Gql\Syntax\Pattern\EdgePattern;
use App\Gql\Syntax\Pattern\ElementFilter;
use App\Gql\Syntax\Pattern\GraphPattern;
use App\Gql\Syntax\Pattern\LabelOperator;
use App\Gql\Syntax\Pattern\LabelPattern;
use App\Gql\Syntax\Pattern\NodePattern;
use App\Gql\Syntax\Pattern\PathMode;
use App\Gql\Syntax\Pattern\PathPattern;
use App\Gql\Syntax\Query;
use App\Gql\Syntax\QueryBlock;
use App\Gql\Syntax\SetOperator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Tests\Fixture\Gql\QuerySpelling;

/**
 * @internal
 */
#[CoversClass(Parser::class)]
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
#[UsesClass(LiteralExpression::class)]
#[UsesClass(PropertyExpression::class)]
#[UsesClass(VariableExpression::class)]
#[UsesClass(OrderByClause::class)]
#[UsesClass(PageClause::class)]
#[UsesClass(Projection::class)]
#[UsesClass(ReturnClause::class)]
#[UsesClass(SortDirection::class)]
#[UsesClass(SortKey::class)]
#[UsesClass(LabelParser::class)]
#[UsesClass(PatternParser::class)]
#[UsesClass(EdgeDirection::class)]
#[UsesClass(EdgePattern::class)]
#[UsesClass(ElementFilter::class)]
#[UsesClass(GraphPattern::class)]
#[UsesClass(LabelOperator::class)]
#[UsesClass(LabelPattern::class)]
#[UsesClass(NodePattern::class)]
#[UsesClass(PathMode::class)]
#[UsesClass(PathPattern::class)]
#[UsesClass(ResultParser::class)]
#[UsesClass(FilterClause::class)]
#[UsesClass(LetClause::class)]
#[UsesClass(MatchClause::class)]
#[UsesClass(VariableBinding::class)]
#[UsesClass(Query::class)]
#[UsesClass(QueryBlock::class)]
#[UsesClass(SetOperator::class)]
#[Small]
final class ParserTest extends TestCase
{
    /**
     * @throws GqlException
     */
    #[DataProvider('providerQueriesAndTheirShape')]
    public function testReadReadsAQueryWrittenAsText(string $written, string $shape): void
    {
        self::assertSame($shape, QuerySpelling::of(Parser::read($written)));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function providerQueriesAndTheirShape(): iterable
    {
        yield 'a match and a projection' => [
            'MATCH (p:Method) RETURN p',
            'MATCH (p:Method) RETURN p AS p',
        ];

        yield 'a match narrowed after the pattern' => [
            "MATCH (p) WHERE p.name = 'render' RETURN p",
            "MATCH (p) WHERE (p.name = 'render') RETURN p AS p",
        ];

        yield 'a match that keeps the rows matching nothing' => [
            'OPTIONAL MATCH (p)-[:calls]->(q) RETURN q',
            'OPTIONAL MATCH (p)-[:calls]->(q) RETURN q AS q',
        ];

        yield 'a naming of computed values' => [
            'MATCH (p) LET kind = p.kind RETURN kind',
            'MATCH (p) LET kind=p.kind RETURN kind AS kind',
        ];

        yield 'a filter between the match and the projection' => [
            'MATCH (p) FILTER p.line > 10 RETURN p',
            'MATCH (p) FILTER (p.line > 10) RETURN p AS p',
        ];

        yield 'an ordering written as a clause of its own' => [
            'MATCH (p) ORDER BY p.line RETURN p',
            'MATCH (p) ORDER BY p.line ASC RETURN p AS p',
        ];

        yield 'a stretch written as a clause of its own' => [
            'MATCH (p) LIMIT 10 RETURN p',
            'MATCH (p) PAGE off=0 limit=10 RETURN p AS p',
        ];

        yield 'two runs of clauses combined' => [
            'MATCH (p) RETURN p UNION ALL MATCH (q) RETURN q',
            'MATCH (p) RETURN p AS p UNION ALL MATCH (q) RETURN q AS q',
        ];
    }

    /**
     * @throws GqlException
     */
    public function testReadSaysWhatWasExpectedAndWhereWhenTheQueryIsNotGql(): void
    {
        $this->expectException(GqlException::class);
        $this->expectExceptionMessage('expected a RETURN statement, which is what GQL shows a query\'s answer with at line 1, column 11');

        Parser::read('MATCH (p) RETRUN p');
    }

    /**
     * @throws GqlException
     */
    public function testParseReportsWhatIsLeftOverAfterTheLastBlock(): void
    {
        $this->expectException(GqlException::class);
        $this->expectExceptionMessage('expected the end of the query');

        Parser::read('MATCH (p) RETURN p AS p garbage');
    }

    /**
     * @throws GqlException
     */
    public function testParseBlockReadsAsManyClausesAsAreWritten(): void
    {
        self::assertCount(3, (new Parser(TokenReader::of('MATCH (p) FILTER p.line > 1 RETURN p')))->parseBlock()->clauses);
    }

    /**
     * @throws GqlException
     */
    #[DataProvider('providerWordsAndWhetherTheyBeginAClause')]
    public function testAtClauseReportsWhetherAClauseBeginsHere(string $written, bool $begins): void
    {
        self::assertSame($begins, (new Parser(TokenReader::of($written)))->atClause());
    }

    /**
     * @return iterable<string, array{string, bool}>
     */
    public static function providerWordsAndWhetherTheyBeginAClause(): iterable
    {
        yield 'the word naming a projection' => ['RETURN p', true];

        yield 'the word naming a match' => ['MATCH (p)', true];

        yield 'the word making a match optional' => ['OPTIONAL MATCH (p)', true];

        yield 'a set operator' => ['UNION ALL', false];

        yield 'the end of the query' => ['', false];
    }

    /**
     * @throws GqlException
     */
    public function testParseClauseReadsTheClauseTheFirstWordNames(): void
    {
        self::assertSame(
            'OPTIONAL MATCH (p)',
            QuerySpelling::clause((new Parser(TokenReader::of('OPTIONAL MATCH (p)')))->parseClause()),
        );
    }

    /**
     * @throws GqlException
     */
    public function testParseMatchReadsAMatchNarrowedAfterThePattern(): void
    {
        self::assertNotNull((new Parser(TokenReader::of("MATCH (p:Method) WHERE p.visibility = 'public'")))->parseMatch(false)->where);
    }

    /**
     * @throws GqlException
     */
    public function testParseMatchRemembersThatARowMatchingNothingIsKept(): void
    {
        self::assertTrue((new Parser(TokenReader::of('MATCH (p)')))->parseMatch(true)->optional);
    }

    /**
     * @throws GqlException
     */
    public function testParseLetReadsEveryValueOneClauseNames(): void
    {
        self::assertCount(2, (new Parser(TokenReader::of('LET a = p.line, b = p.name')))->parseLet()->bindings);
    }

    /**
     * @throws GqlException
     */
    public function testParseFilterReadsTheSameWithOrWithoutTheOptionalWord(): void
    {
        self::assertSame(
            QuerySpelling::clause((new Parser(TokenReader::of('FILTER p.line > 10')))->parseFilter()),
            QuerySpelling::clause((new Parser(TokenReader::of('FILTER WHERE p.line > 10')))->parseFilter()),
        );
    }

    /**
     * @throws GqlException
     */
    public function testParsePageReadsAStretchWrittenAsAnOffsetAndALimit(): void
    {
        self::assertSame(10, (new Parser(TokenReader::of('OFFSET 5 LIMIT 10')))->parsePage()->limit);
    }

    /**
     * @throws GqlException
     */
    public function testParsePageReportsAWordThatNamesNoClause(): void
    {
        $this->expectException(GqlException::class);
        $this->expectExceptionMessage('expected a clause');

        (new Parser(TokenReader::of('DELETE (p)')))->parsePage();
    }

    /**
     * @throws GqlException
     */
    #[DataProvider('providerSetOperators')]
    public function testParseSetOperatorReadsWhatCombinesThisRunWithTheNext(string $written, ?SetOperator $operator): void
    {
        self::assertSame($operator, (new Parser(TokenReader::of($written)))->parseSetOperator());
    }

    /**
     * @return iterable<string, array{string, null|SetOperator}>
     */
    public static function providerSetOperators(): iterable
    {
        yield 'keeping every row' => ['UNION ALL MATCH (p)', SetOperator::UnionAll];

        yield 'dropping the rows that repeat' => ['UNION MATCH (p)', SetOperator::Union];

        yield 'dropping them, said out loud' => ['UNION DISTINCT MATCH (p)', SetOperator::Union];

        yield 'the rows one run has and the other does not' => ['EXCEPT MATCH (p)', SetOperator::Except];

        yield 'the rows both runs have' => ['INTERSECT MATCH (p)', SetOperator::Intersect];

        yield 'the second run only when the first finds nothing' => ['OTHERWISE MATCH (p)', SetOperator::Otherwise];

        yield 'a query that combines nothing' => ['', null];
    }
}
