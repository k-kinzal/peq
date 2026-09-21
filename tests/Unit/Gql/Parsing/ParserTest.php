<?php

declare(strict_types=1);

namespace Tests\Unit\Gql\Parsing;

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
use App\Gql\Parsing\ElementParser;
use App\Gql\Parsing\ExpressionParser;
use App\Gql\Parsing\LabelParser;
use App\Gql\Parsing\NameReader;
use App\Gql\Parsing\OperandParser;
use App\Gql\Parsing\Parser;
use App\Gql\Parsing\PatternParser;
use App\Gql\Parsing\QuantifierParser;
use App\Gql\Parsing\ResultParser;
use App\Gql\Parsing\StatementRefusal;
use App\Gql\Parsing\TokenReader;
use App\Gql\ReservedWords;
use App\Gql\StatusCode;
use App\Gql\Syntax\Clause;
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
#[UsesClass(NameReader::class)]
#[UsesClass(ElementParser::class)]
#[UsesClass(ExpressionParser::class)]
#[UsesClass(LabelParser::class)]
#[UsesClass(OperandParser::class)]
#[UsesClass(PatternParser::class)]
#[UsesClass(QuantifierParser::class)]
#[UsesClass(ResultParser::class)]
#[UsesClass(StatementRefusal::class)]
#[UsesClass(ReservedWords::class)]
#[UsesClass(DatumKind::class)]
#[UsesClass(DecimalDatum::class)]
#[UsesClass(IntegerDatum::class)]
#[UsesClass(StringDatum::class)]
#[UsesClass(BinaryExpression::class)]
#[UsesClass(BinaryOperator::class)]
#[UsesClass(LiteralExpression::class)]
#[UsesClass(PropertyExpression::class)]
#[UsesClass(VariableExpression::class)]
#[UsesClass(FilterClause::class)]
#[UsesClass(LetClause::class)]
#[UsesClass(MatchClause::class)]
#[UsesClass(OrderByClause::class)]
#[UsesClass(PageClause::class)]
#[UsesClass(Projection::class)]
#[UsesClass(ReturnClause::class)]
#[UsesClass(SortDirection::class)]
#[UsesClass(SortKey::class)]
#[UsesClass(VariableBinding::class)]
#[UsesClass(EdgeDirection::class)]
#[UsesClass(EdgePattern::class)]
#[UsesClass(ElementFilter::class)]
#[UsesClass(GraphPattern::class)]
#[UsesClass(LabelOperator::class)]
#[UsesClass(LabelPattern::class)]
#[UsesClass(NodePattern::class)]
#[UsesClass(PathMode::class)]
#[UsesClass(PathPattern::class)]
#[UsesClass(Query::class)]
#[UsesClass(QueryBlock::class)]
#[UsesClass(SetOperator::class)]
#[Small]
final class ParserTest extends TestCase
{
    /**
     * @throws GqlException
     */
    #[DataProvider('providerQueriesAndWhatTheyAsk')]
    public function testReadReadsAQueryWrittenAsText(string $written, Query $expected): void
    {
        self::assertEquals($expected, Parser::read($written));
    }

    /**
     * @return iterable<string, array{string, Query}>
     */
    public static function providerQueriesAndWhatTheyAsk(): iterable
    {
        yield 'a match and a projection' => [
            'MATCH (p:Method) RETURN p',
            new Query([new QueryBlock([
                new MatchClause(new GraphPattern([new PathPattern([new NodePattern('p', LabelPattern::named('Method'))], PathMode::Walk)])),
                new ReturnClause([new Projection(new VariableExpression('p'), null, 'p')]),
            ])]),
        ];

        yield 'a match narrowed after the pattern' => [
            "MATCH (p) WHERE p.name = 'render' RETURN p",
            new Query([new QueryBlock([
                new MatchClause(
                    new GraphPattern([new PathPattern([new NodePattern('p')], PathMode::Walk)]),
                    new BinaryExpression(
                        BinaryOperator::Equal,
                        new PropertyExpression(new VariableExpression('p'), 'name'),
                        new LiteralExpression(new StringDatum('render')),
                    ),
                ),
                new ReturnClause([new Projection(new VariableExpression('p'), null, 'p')]),
            ])]),
        ];

        yield 'a match that keeps the rows matching nothing' => [
            'OPTIONAL MATCH (p)-[:calls]->(q) RETURN q',
            new Query([new QueryBlock([
                new MatchClause(
                    new GraphPattern([new PathPattern(
                        [new NodePattern('p'), new EdgePattern(EdgeDirection::Along, null, LabelPattern::named('calls')), new NodePattern('q')],
                        PathMode::Walk,
                    )]),
                    null,
                    true,
                ),
                new ReturnClause([new Projection(new VariableExpression('q'), null, 'q')]),
            ])]),
        ];

        yield 'a naming of computed values' => [
            'MATCH (p) LET kind = p.kind RETURN kind',
            new Query([new QueryBlock([
                new MatchClause(new GraphPattern([new PathPattern([new NodePattern('p')], PathMode::Walk)])),
                new LetClause([new VariableBinding('kind', new PropertyExpression(new VariableExpression('p'), 'kind'))]),
                new ReturnClause([new Projection(new VariableExpression('kind'), null, 'kind')]),
            ])]),
        ];

        yield 'an ordering and a stretch written as clauses of their own' => [
            'MATCH (p) ORDER BY p.line LIMIT 10 RETURN p',
            new Query([new QueryBlock([
                new MatchClause(new GraphPattern([new PathPattern([new NodePattern('p')], PathMode::Walk)])),
                new OrderByClause([new SortKey(new PropertyExpression(new VariableExpression('p'), 'line'))]),
                new PageClause(0, 10),
                new ReturnClause([new Projection(new VariableExpression('p'), null, 'p')]),
            ])]),
        ];

        yield 'a projection with nothing before it' => [
            'RETURN 1 AS one',
            new Query([new QueryBlock([
                new ReturnClause([new Projection(new LiteralExpression(new IntegerDatum(1)), 'one', '1')]),
            ])]),
        ];

        yield 'two runs of clauses combined' => [
            'MATCH (p) RETURN p UNION ALL MATCH (q) RETURN q',
            new Query(
                [
                    new QueryBlock([
                        new MatchClause(new GraphPattern([new PathPattern([new NodePattern('p')], PathMode::Walk)])),
                        new ReturnClause([new Projection(new VariableExpression('p'), null, 'p')]),
                    ]),
                    new QueryBlock([
                        new MatchClause(new GraphPattern([new PathPattern([new NodePattern('q')], PathMode::Walk)])),
                        new ReturnClause([new Projection(new VariableExpression('q'), null, 'q')]),
                    ]),
                ],
                [SetOperator::UnionAll],
            ),
        ];
    }

    /**
     * @throws GqlException
     */
    public function testReadSaysWhatWasExpectedAndWhereWhenTheQueryIsNotGql(): void
    {
        $this->expectException(GqlException::class);
        $this->expectExceptionMessage('expected a RETURN statement, which is what GQL shows a query\'s answer with at line 1, column 11 (found "RETRUN")');

        Parser::read('MATCH (p) RETRUN p');
    }

    /**
     * @throws GqlException
     */
    #[DataProvider('providerQueriesWithSomethingGqlDoesNotWriteAfterAValue')]
    public function testReadRefusesWhatGqlDoesNotWriteAfterAValue(string $written, string $message): void
    {
        $this->expectException(GqlException::class);
        $this->expectExceptionMessage($message);

        Parser::read($written);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function providerQueriesWithSomethingGqlDoesNotWriteAfterAValue(): iterable
    {
        yield 'a subscript' => ['RETURN xs[0]', 'expected the end of the query at line 1, column 10 (found "[")'];

        yield 'a membership test' => [
            'RETURN a IN [1]',
            'expected the end of the query at line 1, column 10 (found "IN")',
        ];

        yield 'a second comparison' => ['RETURN a < b < c', 'expected the end of the query at line 1, column 14 (found "<")'];
    }

    /**
     * @throws GqlException
     */
    public function testReadRefusesAStatementThatWouldChangeTheGraphByName(): void
    {
        $this->expectException(GqlException::class);
        $this->expectExceptionMessage('[42000] error: syntax error or access rule violation: INSERT adds nodes and edges to a graph');

        Parser::read('INSERT (p:Person)');
    }

    /**
     * @throws GqlException
     */
    public function testParseReadsEveryRunOfClausesAndTheOperatorsBetweenThem(): void
    {
        self::assertEquals(
            new Query(
                [
                    new QueryBlock([new ReturnClause([new Projection(new LiteralExpression(new IntegerDatum(1)), null, '1')])]),
                    new QueryBlock([new ReturnClause([new Projection(new LiteralExpression(new IntegerDatum(2)), null, '2')])]),
                    new QueryBlock([new ReturnClause([new Projection(new LiteralExpression(new IntegerDatum(3)), null, '3')])]),
                ],
                [SetOperator::Except, SetOperator::Otherwise],
            ),
            (new Parser(TokenReader::of('RETURN 1 EXCEPT RETURN 2 OTHERWISE RETURN 3')))->parse(),
        );
    }

    /**
     * @throws GqlException
     */
    public function testParseReportsWhatIsLeftOverAfterTheLastBlock(): void
    {
        $this->expectException(GqlException::class);
        $this->expectExceptionMessage('expected the end of the query at line 1, column 25 (found "garbage")');

        (new Parser(TokenReader::of('MATCH (p) RETURN p AS p garbage')))->parse();
    }

    /**
     * @throws GqlException
     */
    public function testParseBlockReadsAsManyClausesAsAreWritten(): void
    {
        self::assertEquals(
            new QueryBlock([
                new MatchClause(new GraphPattern([new PathPattern([new NodePattern('p')], PathMode::Walk)])),
                new FilterClause(new BinaryExpression(
                    BinaryOperator::Greater,
                    new PropertyExpression(new VariableExpression('p'), 'line'),
                    new LiteralExpression(new IntegerDatum(1)),
                )),
                new ReturnClause([new Projection(new VariableExpression('p'), null, 'p')]),
            ]),
            (new Parser(TokenReader::of('MATCH (p) FILTER p.line > 1 RETURN p')))->parseBlock(),
        );
    }

    /**
     * @throws GqlException
     */
    public function testParseBlockRefusesARunThatNeverSaysWhatToShow(): void
    {
        $this->expectException(GqlException::class);
        $this->expectExceptionMessage('expected a RETURN statement, which is what GQL shows a query\'s answer with at line 1, column 10 (found the end of the query)');

        (new Parser(TokenReader::of('MATCH (p)')))->parseBlock();
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

        yield 'the word naming a naming' => ['LET a = 1', true];

        yield 'the word naming a filter' => ['FILTER a', true];

        yield 'the word naming an ordering' => ['ORDER BY a', true];

        yield 'the words naming a stretch' => ['SKIP 1', true];

        yield 'a set operator' => ['UNION ALL', false];

        yield 'the end of the query' => ['', false];
    }

    /**
     * @throws GqlException
     */
    #[DataProvider('providerClausesAndTheWordsThatNameThem')]
    public function testParseClauseReadsTheClauseTheFirstWordNames(string $written, Clause $expected): void
    {
        self::assertEquals($expected, (new Parser(TokenReader::of($written)))->parseClause());
    }

    /**
     * @return iterable<string, array{string, Clause}>
     */
    public static function providerClausesAndTheWordsThatNameThem(): iterable
    {
        yield 'a match that keeps the rows matching nothing' => [
            'OPTIONAL MATCH (p)',
            new MatchClause(new GraphPattern([new PathPattern([new NodePattern('p')], PathMode::Walk)]), null, true),
        ];

        yield 'an ordering' => ['ORDER BY a DESC', new OrderByClause([new SortKey(new VariableExpression('a'), SortDirection::Descending)])];

        yield 'a stretch' => ['OFFSET 5', new PageClause(5, null)];

        yield 'a projection' => ['RETURN *', new ReturnClause()];
    }

    /**
     * @throws GqlException
     */
    public function testParseClauseRefusesAStatementGqlDefinesAndPeqDoesNotRunByName(): void
    {
        $this->expectException(GqlException::class);
        $this->expectExceptionMessage('DELETE removes nodes and edges from a graph, and peq answers questions about source code rather than keeping a graph, so it does not run one');

        (new Parser(TokenReader::of('DELETE (p)')))->parseClause();
    }

    /**
     * @throws GqlException
     */
    public function testParseClauseReportsAWordThatNamesNoClause(): void
    {
        $this->expectException(GqlException::class);
        $this->expectExceptionMessage('expected a clause at line 1, column 1 (found "banana")');

        (new Parser(TokenReader::of('banana')))->parseClause();
    }

    /**
     * @throws GqlException
     */
    public function testParseMatchReadsAMatchNarrowedAfterThePattern(): void
    {
        self::assertEquals(
            new MatchClause(
                new GraphPattern([new PathPattern([new NodePattern('p', LabelPattern::named('Method'))], PathMode::Walk)]),
                new BinaryExpression(
                    BinaryOperator::Equal,
                    new PropertyExpression(new VariableExpression('p'), 'visibility'),
                    new LiteralExpression(new StringDatum('public')),
                ),
            ),
            (new Parser(TokenReader::of("MATCH (p:Method) WHERE p.visibility = 'public'")))->parseMatch(false),
        );
    }

    /**
     * @throws GqlException
     */
    public function testParseMatchRemembersThatARowMatchingNothingIsKept(): void
    {
        self::assertEquals(
            new MatchClause(new GraphPattern([new PathPattern([new NodePattern('p')], PathMode::Walk)]), null, true),
            (new Parser(TokenReader::of('MATCH (p)')))->parseMatch(true),
        );
    }

    /**
     * @throws GqlException
     */
    public function testParseLetReadsEveryValueOneClauseNames(): void
    {
        self::assertEquals(
            new LetClause([
                new VariableBinding('a', new PropertyExpression(new VariableExpression('p'), 'line')),
                new VariableBinding('b', new PropertyExpression(new VariableExpression('p'), 'name')),
            ]),
            (new Parser(TokenReader::of('LET a = p.line, b = p.name')))->parseLet(),
        );
    }

    /**
     * @throws GqlException
     */
    public function testParseLetRefusesANameGqlReserves(): void
    {
        $this->expectException(GqlException::class);
        $this->expectExceptionMessage('GQL reserves "VALUE", so it is not a name here: a query binds a name to a word GQL leaves free');

        (new Parser(TokenReader::of('LET value = 1')))->parseLet();
    }

    /**
     * @throws GqlException
     */
    #[DataProvider('providerFiltersWithAndWithoutTheOptionalWord')]
    public function testParseFilterReadsTheSameWithOrWithoutTheOptionalWord(string $written): void
    {
        self::assertEquals(
            new FilterClause(new BinaryExpression(
                BinaryOperator::Greater,
                new PropertyExpression(new VariableExpression('p'), 'line'),
                new LiteralExpression(new IntegerDatum(10)),
            )),
            (new Parser(TokenReader::of($written)))->parseFilter(),
        );
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function providerFiltersWithAndWithoutTheOptionalWord(): iterable
    {
        yield 'without it' => ['FILTER p.line > 10'];

        yield 'with it' => ['FILTER WHERE p.line > 10'];
    }

    /**
     * @throws GqlException
     */
    public function testParsePageReadsAStretchWrittenAsAnOffsetAndALimit(): void
    {
        self::assertEquals(new PageClause(5, 10), (new Parser(TokenReader::of('OFFSET 5 LIMIT 10')))->parsePage());
    }

    /**
     * @throws GqlException
     */
    public function testParsePageReportsAWordThatNamesNoClause(): void
    {
        $this->expectException(GqlException::class);
        $this->expectExceptionMessage('expected a clause at line 1, column 1 (found "DELETE")');

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
