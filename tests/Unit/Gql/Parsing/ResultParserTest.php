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
use App\Gql\Parsing\ResultParser;
use App\Gql\Parsing\TokenReader;
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
use Tests\Fixture\Gql\QuerySpelling;
use Tests\Fixture\Gql\ResultSpelling;

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
#[Small]
final class ResultParserTest extends TestCase
{
    /**
     * @throws GqlException
     */
    #[DataProvider('providerProjectionsAndTheirShape')]
    public function testParseReadsWhatTheReaderIsShown(string $written, string $shape): void
    {
        self::assertSame($shape, ResultSpelling::of($written));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function providerProjectionsAndTheirShape(): iterable
    {
        yield 'a column named by the query' => ['RETURN p.name AS symbol', 'RETURN p.name AS symbol'];

        yield 'a column named by what produced it' => ['RETURN p.name', 'RETURN p.name AS p.name'];

        yield 'everything that is bound' => ['RETURN *', 'RETURN *'];

        yield 'only the rows that differ' => ['RETURN DISTINCT p.kind', 'RETURN DISTINCT p.kind AS p.kind'];

        yield 'rows gathered by something' => [
            'RETURN kind, count(*) AS total GROUP BY kind',
            'RETURN kind AS kind,count(*) AS total GROUP BY kind',
        ];

        yield 'rows put in an order' => [
            'RETURN p.line ORDER BY p.line DESC',
            'RETURN p.line AS p.line ORDER BY p.line DESC',
        ];

        yield 'a stretch of the rows' => [
            'RETURN p OFFSET 5 LIMIT 10',
            'RETURN p AS p PAGE off=5 limit=10',
        ];

        yield 'all four at once' => [
            'RETURN kind, count(*) AS total GROUP BY kind ORDER BY total DESC LIMIT 3',
            'RETURN kind AS kind,count(*) AS total GROUP BY kind ORDER BY total DESC PAGE off=0 limit=3',
        ];
    }

    /**
     * @throws GqlException
     */
    public function testParseColumnsReadsEveryColumnTheProjectionShows(): void
    {
        self::assertCount(2, ResultSpelling::parser('p.name, p.kind')->parseColumns());
    }

    /**
     * @throws GqlException
     */
    public function testParseColumnReadsTheNameAColumnIsGiven(): void
    {
        self::assertSame('symbol', ResultSpelling::parser('p.name AS symbol')->parseColumn()->heading());
    }

    /**
     * @throws GqlException
     */
    public function testParseColumnHeadsAnUnnamedColumnByWhatProducedIt(): void
    {
        self::assertSame("p.firstName || ' '", ResultSpelling::parser("p.firstName || ' '")->parseColumn()->heading());
    }

    /**
     * @throws GqlException
     */
    public function testParseGroupByReadsEverythingTheRowsAreGatheredBy(): void
    {
        self::assertCount(2, ResultSpelling::parser('GROUP BY kind, owner.name')->parseGroupBy());
    }

    /**
     * @throws GqlException
     */
    public function testParseGroupByReadsAProjectionThatGathersNothing(): void
    {
        self::assertSame([], ResultSpelling::parser('LIMIT 10')->parseGroupBy());
    }

    /**
     * @throws GqlException
     */
    public function testParseOrderByReadsEveryKeyInTheOrderTheyAreTriedIn(): void
    {
        self::assertSame(
            'a DESC,b ASC',
            QuerySpelling::keys(ResultSpelling::parser('ORDER BY a DESC, b')->parseOrderBy()->keys),
        );
    }

    /**
     * @throws GqlException
     */
    #[DataProvider('providerDirectionsAndWhatTheyOrderBy')]
    public function testParseDirectionReadsWhichWayAKeyOrders(string $written, SortDirection $direction): void
    {
        self::assertSame($direction, ResultSpelling::parser($written)->parseDirection());
    }

    /**
     * @return iterable<string, array{string, SortDirection}>
     */
    public static function providerDirectionsAndWhatTheyOrderBy(): iterable
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
    #[DataProvider('providerStretchesOfTheResult')]
    public function testParsePageReadsWhichStretchOfTheResultIsShown(string $written, string $shape): void
    {
        $page = ResultSpelling::parser($written)->parsePage();

        self::assertSame($shape, $page === null ? 'all of it' : QuerySpelling::page($page));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function providerStretchesOfTheResult(): iterable
    {
        yield 'some rows skipped and some kept' => ['OFFSET 10 LIMIT 5', 'PAGE off=10 limit=5'];

        yield 'only rows skipped' => ['OFFSET 10', 'PAGE off=10 limit=all'];

        yield 'only rows kept' => ['LIMIT 5', 'PAGE off=0 limit=5'];

        yield 'skipping written the other way' => ['SKIP 10', 'PAGE off=10 limit=all'];

        yield 'a projection that says nothing' => ['', 'all of it'];
    }

    /**
     * @throws GqlException
     */
    public function testParseCountReadsAWholeNumberOfRows(): void
    {
        self::assertSame(10, ResultSpelling::parser('10')->parseCount());
    }

    /**
     * @throws GqlException
     */
    public function testParseCountReportsAnythingElseRatherThanGuessAtIt(): void
    {
        $this->expectException(GqlException::class);
        $this->expectExceptionMessage('a whole number of rows');

        ResultSpelling::parser('many')->parseCount();
    }
}
