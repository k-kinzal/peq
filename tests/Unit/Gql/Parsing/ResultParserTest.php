<?php

declare(strict_types=1);

namespace Tests\Unit\Gql\Parsing;

use App\Gql\Datum\DatumKind;
use App\Gql\Datum\DecimalDatum;
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
use App\Gql\Parsing\ResultParser;
use App\Gql\Parsing\TokenReader;
use App\Gql\ReservedWords;
use App\Gql\StatusCode;
use App\Gql\Syntax\Clause\OrderByClause;
use App\Gql\Syntax\Clause\PageClause;
use App\Gql\Syntax\Clause\Projection;
use App\Gql\Syntax\Clause\ReturnClause;
use App\Gql\Syntax\Clause\SortDirection;
use App\Gql\Syntax\Clause\SortKey;
use App\Gql\Syntax\Expression\BinaryExpression;
use App\Gql\Syntax\Expression\BinaryOperator;
use App\Gql\Syntax\Expression\CallExpression;
use App\Gql\Syntax\Expression\LiteralExpression;
use App\Gql\Syntax\Expression\PropertyExpression;
use App\Gql\Syntax\Expression\VariableExpression;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(ResultParser::class)]
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
#[UsesClass(OperandParser::class)]
#[UsesClass(ReservedWords::class)]
#[UsesClass(DatumKind::class)]
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
#[UsesClass(DecimalDatum::class)]
#[Small]
final class ResultParserTest extends TestCase
{
    /**
     * @throws GqlException
     */
    #[DataProvider('providerProjectionsAndWhatTheyShow')]
    public function testParseReadsWhatTheReaderIsShown(string $written, ReturnClause $expected): void
    {
        $tokens = TokenReader::of($written);

        self::assertEquals($expected, (new ResultParser($tokens, new ExpressionParser($tokens)))->parse());
    }

    /**
     * @return iterable<string, array{string, ReturnClause}>
     */
    public static function providerProjectionsAndWhatTheyShow(): iterable
    {
        yield 'a column named by the query' => [
            'RETURN p.name AS symbol',
            new ReturnClause([new Projection(new PropertyExpression(new VariableExpression('p'), 'name'), 'symbol', 'p.name')]),
        ];

        yield 'a column headed by what produced it' => [
            'RETURN p.name',
            new ReturnClause([new Projection(new PropertyExpression(new VariableExpression('p'), 'name'), null, 'p.name')]),
        ];

        yield 'everything that is bound' => ['RETURN *', new ReturnClause()];

        yield 'only the rows that differ' => [
            'RETURN DISTINCT p.kind',
            new ReturnClause([new Projection(new PropertyExpression(new VariableExpression('p'), 'kind'), null, 'p.kind')], true),
        ];

        yield 'rows gathered by a name' => [
            'RETURN kind, count(*) AS total GROUP BY kind',
            new ReturnClause(
                [
                    new Projection(new VariableExpression('kind'), null, 'kind'),
                    new Projection(new CallExpression('count', [], false, true), 'total', 'count(*)'),
                ],
                false,
                [new VariableExpression('kind')],
            ),
        ];

        yield 'rows put in an order' => [
            'RETURN p.line ORDER BY p.line DESC',
            new ReturnClause(
                [new Projection(new PropertyExpression(new VariableExpression('p'), 'line'), null, 'p.line')],
                false,
                [],
                [new SortKey(new PropertyExpression(new VariableExpression('p'), 'line'), SortDirection::Descending)],
            ),
        ];

        yield 'a stretch of the rows' => [
            'RETURN p OFFSET 5 LIMIT 10',
            new ReturnClause([new Projection(new VariableExpression('p'), null, 'p')], false, [], [], new PageClause(5, 10)),
        ];

        yield 'all four at once' => [
            'RETURN DISTINCT kind, count(*) AS total GROUP BY kind ORDER BY total DESC LIMIT 3',
            new ReturnClause(
                [
                    new Projection(new VariableExpression('kind'), null, 'kind'),
                    new Projection(new CallExpression('count', [], false, true), 'total', 'count(*)'),
                ],
                true,
                [new VariableExpression('kind')],
                [new SortKey(new VariableExpression('total'), SortDirection::Descending)],
                new PageClause(0, 3),
            ),
        ];
    }

    /**
     * @throws GqlException
     */
    public function testParseReportsAProjectionThatDoesNotStartWithReturn(): void
    {
        $tokens = TokenReader::of('p.name');

        $this->expectException(GqlException::class);
        $this->expectExceptionMessage('expected RETURN at line 1, column 1 (found "p")');

        (new ResultParser($tokens, new ExpressionParser($tokens)))->parse();
    }

    /**
     * @throws GqlException
     */
    public function testParseColumnsReadsEveryColumnTheProjectionShows(): void
    {
        $tokens = TokenReader::of('p.name, p.kind');

        self::assertEquals(
            [
                new Projection(new PropertyExpression(new VariableExpression('p'), 'name'), null, 'p.name'),
                new Projection(new PropertyExpression(new VariableExpression('p'), 'kind'), null, 'p.kind'),
            ],
            (new ResultParser($tokens, new ExpressionParser($tokens)))->parseColumns(),
        );
    }

    /**
     * @throws GqlException
     */
    #[DataProvider('providerColumns')]
    public function testParseColumnReadsOneColumnAndWhatItIsHeadedBy(string $written, Projection $expected): void
    {
        $tokens = TokenReader::of($written);

        self::assertEquals($expected, (new ResultParser($tokens, new ExpressionParser($tokens)))->parseColumn());
    }

    /**
     * @return iterable<string, array{string, Projection}>
     */
    public static function providerColumns(): iterable
    {
        yield 'named by the query' => [
            'p.name AS symbol',
            new Projection(new PropertyExpression(new VariableExpression('p'), 'name'), 'symbol', 'p.name'),
        ];

        yield 'named by a word GQL reserves, in back quotes' => [
            'p AS `value`',
            new Projection(new VariableExpression('p'), 'value', 'p'),
        ];

        yield 'headed by the text that produced it, exactly as it was written' => [
            "p.firstName || ' '",
            new Projection(
                new BinaryExpression(
                    BinaryOperator::Concatenate,
                    new PropertyExpression(new VariableExpression('p'), 'firstName'),
                    new LiteralExpression(new StringDatum(' ')),
                ),
                null,
                "p.firstName || ' '",
            ),
        ];
    }

    /**
     * @throws GqlException
     */
    public function testParseColumnRefusesANameGqlReserves(): void
    {
        $tokens = TokenReader::of('p AS value');

        $this->expectException(GqlException::class);
        $this->expectExceptionMessage('GQL reserves "VALUE", so it is not a name here: write it in back quotes to use it as a name');

        (new ResultParser($tokens, new ExpressionParser($tokens)))->parseColumn();
    }

    /**
     * @throws GqlException
     */
    public function testParseGroupByReadsEveryNameTheRowsAreGatheredBy(): void
    {
        $tokens = TokenReader::of('GROUP BY kind, owner');

        self::assertEquals(
            [new VariableExpression('kind'), new VariableExpression('owner')],
            (new ResultParser($tokens, new ExpressionParser($tokens)))->parseGroupBy(),
        );
    }

    /**
     * @throws GqlException
     */
    public function testParseGroupByReadsAProjectionThatGathersNothing(): void
    {
        $tokens = TokenReader::of('LIMIT 10');

        self::assertSame([], (new ResultParser($tokens, new ExpressionParser($tokens)))->parseGroupBy());
        self::assertSame(0, $tokens->position());
    }

    /**
     * @throws GqlException
     */
    public function testParseGroupByRefusesAPropertyBecauseGqlGroupsByNamesOnly(): void
    {
        $tokens = TokenReader::of('GROUP BY owner.name');

        $this->expectException(GqlException::class);
        $this->expectExceptionMessage('expected a comma or the end of GROUP BY, which groups by names only at line 1, column 15 (found ".")');

        (new ResultParser($tokens, new ExpressionParser($tokens)))->parseGroupBy();
    }

    /**
     * @throws GqlException
     */
    public function testParseGroupByRefusesAnythingButAName(): void
    {
        $tokens = TokenReader::of('GROUP BY count(*)');

        $this->expectException(GqlException::class);
        $this->expectExceptionMessage('GQL reserves "COUNT", so it is not a name here: a query binds a name to a word GQL leaves free');

        (new ResultParser($tokens, new ExpressionParser($tokens)))->parseGroupBy();
    }

    /**
     * @throws GqlException
     */
    public function testParseOrderByReadsEveryKeyInTheOrderTheyAreTriedIn(): void
    {
        $tokens = TokenReader::of('ORDER BY a DESC, b');

        self::assertEquals(
            new OrderByClause([
                new SortKey(new VariableExpression('a'), SortDirection::Descending),
                new SortKey(new VariableExpression('b'), SortDirection::Ascending),
            ]),
            (new ResultParser($tokens, new ExpressionParser($tokens)))->parseOrderBy(),
        );
    }

    /**
     * @throws GqlException
     */
    #[DataProvider('providerDirectionsAndWhichWayTheyOrder')]
    public function testParseDirectionReadsWhichWayAKeyOrders(string $written, SortDirection $direction): void
    {
        $tokens = TokenReader::of($written);

        self::assertSame($direction, (new ResultParser($tokens, new ExpressionParser($tokens)))->parseDirection());
    }

    /**
     * @return iterable<string, array{string, SortDirection}>
     */
    public static function providerDirectionsAndWhichWayTheyOrder(): iterable
    {
        yield 'a key that says nothing' => [', b', SortDirection::Ascending];

        yield 'the short word for largest first' => ['DESC', SortDirection::Descending];

        yield 'the long word for largest first' => ['DESCENDING', SortDirection::Descending];

        yield 'the short word for smallest first' => ['ASC', SortDirection::Ascending];

        yield 'the long word for smallest first' => ['ASCENDING', SortDirection::Ascending];
    }

    /**
     * @throws GqlException
     */
    public function testParseDirectionTakesTheWordThatSaysWhichWay(): void
    {
        $tokens = TokenReader::of('ASCENDING, b');
        (new ResultParser($tokens, new ExpressionParser($tokens)))->parseDirection();

        self::assertEquals(new Token(TokenKind::Symbol, ',', ',', 1, 10, 9), $tokens->current());
    }

    /**
     * @throws GqlException
     */
    #[DataProvider('providerStretchesOfTheResult')]
    public function testParsePageReadsWhichStretchOfTheResultIsShown(string $written, PageClause $expected): void
    {
        $tokens = TokenReader::of($written);

        self::assertEquals($expected, (new ResultParser($tokens, new ExpressionParser($tokens)))->parsePage());
    }

    /**
     * @return iterable<string, array{string, PageClause}>
     */
    public static function providerStretchesOfTheResult(): iterable
    {
        yield 'some rows skipped and some kept' => ['OFFSET 10 LIMIT 5', new PageClause(10, 5)];

        yield 'only rows skipped' => ['OFFSET 10', new PageClause(10, null)];

        yield 'only rows kept' => ['LIMIT 5', new PageClause(0, 5)];

        yield 'skipping written the other way' => ['SKIP 10', new PageClause(10, null)];

        yield 'no rows kept at all' => ['LIMIT 0', new PageClause(0, 0)];
    }

    /**
     * @throws GqlException
     */
    public function testParsePageShowsAllOfTheResultWhenNoStretchIsWritten(): void
    {
        $tokens = TokenReader::of('');

        self::assertNull((new ResultParser($tokens, new ExpressionParser($tokens)))->parsePage());
    }

    /**
     * @throws GqlException
     */
    public function testParseCountReadsAWholeNumberOfRows(): void
    {
        $tokens = TokenReader::of('10');

        self::assertSame(10, (new ResultParser($tokens, new ExpressionParser($tokens)))->parseCount());
    }

    /**
     * @throws GqlException
     */
    #[DataProvider('providerCountsThatAreNotWholeNumbersOfRows')]
    public function testParseCountReportsAnythingElseRatherThanGuessAtIt(string $written, string $found): void
    {
        $tokens = TokenReader::of($written);

        $this->expectException(GqlException::class);
        $this->expectExceptionMessage('expected a whole number of rows at line 1, column 1 (found '.$found.')');

        (new ResultParser($tokens, new ExpressionParser($tokens)))->parseCount();
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function providerCountsThatAreNotWholeNumbersOfRows(): iterable
    {
        yield 'a word' => ['many', '"many"'];

        yield 'an exact number with digits after its point' => ['1.5', '"1.5"'];

        yield 'a negative number' => ['-1', '"-"'];
    }

    /**
     * @throws GqlException
     */
    public function testParseCountRefusesACountTooLargeToHold(): void
    {
        $tokens = TokenReader::of('99999999999999999999');

        $this->expectException(GqlException::class);
        $this->expectExceptionMessage('[22003] error: data exception - numeric value out of range');

        (new ResultParser($tokens, new ExpressionParser($tokens)))->parseCount();
    }
}
