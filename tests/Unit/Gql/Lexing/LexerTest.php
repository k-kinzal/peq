<?php

declare(strict_types=1);

namespace Tests\Unit\Gql\Lexing;

use App\Gql\GqlException;
use App\Gql\Lexing\Lexer;
use App\Gql\Lexing\QuotedScanner;
use App\Gql\Lexing\SourceCursor;
use App\Gql\Lexing\Token;
use App\Gql\Lexing\TokenKind;
use App\Gql\Lexing\TokenList;
use App\Gql\StatusCode;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(Lexer::class)]
#[UsesClass(GqlException::class)]
#[UsesClass(QuotedScanner::class)]
#[UsesClass(SourceCursor::class)]
#[UsesClass(StatusCode::class)]
#[UsesClass(Token::class)]
#[UsesClass(TokenKind::class)]
#[UsesClass(TokenList::class)]
#[Small]
final class LexerTest extends TestCase
{
    /**
     * @throws GqlException
     */
    public function testOverReadsAQueryFromItsBeginning(): void
    {
        self::assertEquals(new Token(TokenKind::Name, 'MATCH', 'MATCH', 1, 1, 0), Lexer::over('MATCH (p)')->next());
    }

    /**
     * @throws GqlException
     */
    public function testTokenizeEndsTheListWithThePieceStandingForTheEnd(): void
    {
        self::assertEquals(
            new TokenList(
                [
                    new Token(TokenKind::Name, 'LIMIT', 'LIMIT', 1, 1, 0),
                    new Token(TokenKind::Integer, '10', '10', 1, 7, 6),
                    new Token(TokenKind::End, '', '', 1, 9, 8),
                ],
                'LIMIT 10',
            ),
            Lexer::over('LIMIT 10')->tokenize(),
        );
    }

    /**
     * @throws GqlException
     */
    public function testTokenizeReadsAQueryThatSaysNothingAsOnlyItsEnd(): void
    {
        self::assertEquals(
            new TokenList([new Token(TokenKind::End, '', '', 1, 1, 0)], ''),
            Lexer::over('')->tokenize(),
        );
    }

    /**
     * @param list<Token> $pieces
     *
     * @throws GqlException
     */
    #[DataProvider('providerQueriesAndTheirPieces')]
    public function testTokenizeReadsThePiecesAQueryIsWrittenFrom(string $query, array $pieces): void
    {
        self::assertEquals(new TokenList($pieces, $query), Lexer::over($query)->tokenize());
    }

    /**
     * @return iterable<string, array{string, list<Token>}>
     */
    public static function providerQueriesAndTheirPieces(): iterable
    {
        yield 'a name in back quotes' => ['`return`', [
            new Token(TokenKind::QuotedName, '`return`', 'return', 1, 1, 0),
            new Token(TokenKind::End, '', '', 1, 9, 8),
        ]];

        yield 'a string in single quotes' => ["'a'", [
            new Token(TokenKind::Text, "'a'", 'a', 1, 1, 0),
            new Token(TokenKind::End, '', '', 1, 4, 3),
        ]];

        yield 'a string in double quotes' => ['"a"', [
            new Token(TokenKind::Text, '"a"', 'a', 1, 1, 0),
            new Token(TokenKind::End, '', '', 1, 4, 3),
        ]];

        yield 'a string after an at sign, whose backslash escapes nothing' => ["@'a\\tb'", [
            new Token(TokenKind::Text, "@'a\\tb'", 'a\tb', 1, 1, 0),
            new Token(TokenKind::End, '', '', 1, 8, 7),
        ]];

        yield 'a property access' => ['p.name', [
            new Token(TokenKind::Name, 'p', 'p', 1, 1, 0),
            new Token(TokenKind::Symbol, '.', '.', 1, 2, 1),
            new Token(TokenKind::Name, 'name', 'name', 1, 3, 2),
            new Token(TokenKind::End, '', '', 1, 7, 6),
        ]];

        yield 'a concatenation' => ["a || 'b'", [
            new Token(TokenKind::Name, 'a', 'a', 1, 1, 0),
            new Token(TokenKind::Symbol, '||', '||', 1, 3, 2),
            new Token(TokenKind::Text, "'b'", 'b', 1, 6, 5),
            new Token(TokenKind::End, '', '', 1, 9, 8),
        ]];

        yield 'an edge pattern, in pieces' => ['-[:x]->', [
            new Token(TokenKind::Symbol, '-', '-', 1, 1, 0),
            new Token(TokenKind::Symbol, '[', '[', 1, 2, 1),
            new Token(TokenKind::Symbol, ':', ':', 1, 3, 2),
            new Token(TokenKind::Name, 'x', 'x', 1, 4, 3),
            new Token(TokenKind::Symbol, ']', ']', 1, 5, 4),
            new Token(TokenKind::Symbol, '-', '-', 1, 6, 5),
            new Token(TokenKind::Symbol, '>', '>', 1, 7, 6),
            new Token(TokenKind::End, '', '', 1, 8, 7),
        ]];

        yield 'an undirected edge pattern, in pieces' => ['~[e]~>', [
            new Token(TokenKind::Symbol, '~', '~', 1, 1, 0),
            new Token(TokenKind::Symbol, '[', '[', 1, 2, 1),
            new Token(TokenKind::Name, 'e', 'e', 1, 3, 2),
            new Token(TokenKind::Symbol, ']', ']', 1, 4, 3),
            new Token(TokenKind::Symbol, '~', '~', 1, 5, 4),
            new Token(TokenKind::Symbol, '>', '>', 1, 6, 5),
            new Token(TokenKind::End, '', '', 1, 7, 6),
        ]];

        yield 'an arrow pointing both ways, in pieces' => ['<->', [
            new Token(TokenKind::Symbol, '<', '<', 1, 1, 0),
            new Token(TokenKind::Symbol, '-', '-', 1, 2, 1),
            new Token(TokenKind::Symbol, '>', '>', 1, 3, 2),
            new Token(TokenKind::End, '', '', 1, 4, 3),
        ]];

        yield 'a comparison with a negative number, which is not an arrow' => ['1 < -2', [
            new Token(TokenKind::Integer, '1', '1', 1, 1, 0),
            new Token(TokenKind::Symbol, '<', '<', 1, 3, 2),
            new Token(TokenKind::Symbol, '-', '-', 1, 5, 4),
            new Token(TokenKind::Integer, '2', '2', 1, 6, 5),
            new Token(TokenKind::End, '', '', 1, 7, 6),
        ]];

        yield 'an inequality GQL does not write, which is two pieces' => ['!=', [
            new Token(TokenKind::Symbol, '!', '!', 1, 1, 0),
            new Token(TokenKind::Symbol, '=', '=', 1, 2, 1),
            new Token(TokenKind::End, '', '', 1, 3, 2),
        ]];

        yield 'a label disjunction' => [':A|B', [
            new Token(TokenKind::Symbol, ':', ':', 1, 1, 0),
            new Token(TokenKind::Name, 'A', 'A', 1, 2, 1),
            new Token(TokenKind::Symbol, '|', '|', 1, 3, 2),
            new Token(TokenKind::Name, 'B', 'B', 1, 4, 3),
            new Token(TokenKind::End, '', '', 1, 5, 4),
        ]];

        yield 'a wildcard label' => [':%', [
            new Token(TokenKind::Symbol, ':', ':', 1, 1, 0),
            new Token(TokenKind::Symbol, '%', '%', 1, 2, 1),
            new Token(TokenKind::End, '', '', 1, 3, 2),
        ]];

        yield 'a line note in the C form' => ["// a note\nLIMIT", [
            new Token(TokenKind::Name, 'LIMIT', 'LIMIT', 2, 1, 10),
            new Token(TokenKind::End, '', '', 2, 6, 15),
        ]];

        yield 'a line note in the SQL form' => ["-- a note\nLIMIT", [
            new Token(TokenKind::Name, 'LIMIT', 'LIMIT', 2, 1, 10),
            new Token(TokenKind::End, '', '', 2, 6, 15),
        ]];

        yield 'a block note' => ['/* a note */ LIMIT', [
            new Token(TokenKind::Name, 'LIMIT', 'LIMIT', 1, 14, 13),
            new Token(TokenKind::End, '', '', 1, 19, 18),
        ]];

        yield 'a block note that is never closed' => ['/* a note that runs out', [
            new Token(TokenKind::End, '', '', 1, 24, 23),
        ]];
    }

    /**
     * @throws GqlException
     */
    public function testNextReadsOnePieceAtATime(): void
    {
        $lexer = Lexer::over('p.name');

        self::assertEquals(new Token(TokenKind::Name, 'p', 'p', 1, 1, 0), $lexer->next());
        self::assertEquals(new Token(TokenKind::Symbol, '.', '.', 1, 2, 1), $lexer->next());
        self::assertEquals(new Token(TokenKind::Name, 'name', 'name', 1, 3, 2), $lexer->next());
        self::assertEquals(new Token(TokenKind::End, '', '', 1, 7, 6), $lexer->next());
    }

    /**
     * @throws GqlException
     */
    public function testNextReportsACharacterAQueryCannotBeWrittenWith(): void
    {
        $this->expectException(GqlException::class);
        $this->expectExceptionMessage('a query cannot be written with this character at line 1, column 1 (found "@")');

        Lexer::over('@')->next();
    }

    public function testSkipTriviaMovesPastEverythingThatStandsBetweenPieces(): void
    {
        $cursor = new SourceCursor("  -- a note\n/* a block */ LIMIT");
        (new Lexer($cursor))->skipTrivia();

        self::assertSame(26, $cursor->offset());
        self::assertSame(2, $cursor->line());
        self::assertSame(15, $cursor->column());
    }

    public function testSkipTriviaRunsABlockNoteThatIsNeverClosedToTheEnd(): void
    {
        $cursor = new SourceCursor('/* LIMIT 10');
        (new Lexer($cursor))->skipTrivia();

        self::assertTrue($cursor->atEnd());
    }

    public function testSkipTriviaLeavesAPieceWhereItStands(): void
    {
        $cursor = new SourceCursor('LIMIT');
        (new Lexer($cursor))->skipTrivia();

        self::assertSame(0, $cursor->offset());
    }

    public function testScanNameReadsANameAsFarAsANameCanGo(): void
    {
        self::assertEquals(
            new Token(TokenKind::Name, 'firstName', 'firstName', 1, 1, 0),
            Lexer::over('firstName || x')->scanName(),
        );
    }

    public function testScanNameReadsANameThatStartsWithAConnectingMarkAndHoldsDigits(): void
    {
        self::assertEquals(new Token(TokenKind::Name, '_total42', '_total42', 1, 1, 0), Lexer::over('_total42')->scanName());
    }

    public function testScanNameReadsANameWrittenInAnyScript(): void
    {
        self::assertEquals(new Token(TokenKind::Name, '顧客', '顧客', 1, 1, 0), Lexer::over('顧客.name')->scanName());
    }

    #[DataProvider('providerNumbersAndHowTheyAreRead')]
    public function testScanNumberTellsAnExactNumberFromAnApproximateOneByHowItIsWritten(string $written, Token $expected): void
    {
        self::assertEquals($expected, Lexer::over($written)->scanNumber());
    }

    /**
     * @return iterable<string, array{string, Token}>
     */
    public static function providerNumbersAndHowTheyAreRead(): iterable
    {
        yield 'a whole number' => ['42', new Token(TokenKind::Integer, '42', '42', 1, 1, 0)];

        yield 'a number with digits after its point, which is exact' => ['1.5', new Token(TokenKind::Decimal, '1.5', '1.5', 1, 1, 0)];

        yield 'a whole number marked exact' => ['15M', new Token(TokenKind::Decimal, '15M', '15M', 1, 1, 0)];

        yield 'a number with an exponent marked exact' => ['1e3M', new Token(TokenKind::Decimal, '1e3M', '1e3M', 1, 1, 0)];

        yield 'a number with an exponent' => ['1e3', new Token(TokenKind::Approximate, '1e3', '1e3', 1, 1, 0)];

        yield 'a number with a negative exponent' => ['1.5e-3', new Token(TokenKind::Approximate, '1.5e-3', '1.5e-3', 1, 1, 0)];

        yield 'a number marked as a float' => ['1.5f', new Token(TokenKind::Approximate, '1.5f', '1.5f', 1, 1, 0)];

        yield 'a number marked as a double' => ['1.0d', new Token(TokenKind::Approximate, '1.0d', '1.0d', 1, 1, 0)];

        yield 'a point with no digits after it, which GQL counts as part of the number' => ['1.name', new Token(TokenKind::Decimal, '1.', '1.', 1, 1, 0)];

        yield 'a point with nothing before it' => ['.5', new Token(TokenKind::Decimal, '.5', '.5', 1, 1, 0)];

        yield 'digits grouped by underscores, which the value leaves out' => ['1_000_000', new Token(TokenKind::Integer, '1_000_000', '1000000', 1, 1, 0)];

        yield 'an underscore only ever between two digits' => ['1__0', new Token(TokenKind::Integer, '1', '1', 1, 1, 0)];
    }

    #[DataProvider('providerLiteralsAndTheirKinds')]
    public function testNumberKindTellsWhichKindOfNumberALiteralIs(string $written, TokenKind $kind): void
    {
        self::assertSame($kind, Lexer::numberKind($written));
    }

    /**
     * @return iterable<string, array{string, TokenKind}>
     */
    public static function providerLiteralsAndTheirKinds(): iterable
    {
        yield 'digits alone' => ['42', TokenKind::Integer];

        yield 'digits with leading zeros' => ['0042', TokenKind::Integer];

        yield 'digits after a point' => ['1.5', TokenKind::Decimal];

        yield 'digits marked exact' => ['15M', TokenKind::Decimal];

        yield 'digits marked exact in lower case' => ['1.5m', TokenKind::Decimal];

        yield 'an exponent marked exact' => ['1e3M', TokenKind::Decimal];

        yield 'an exponent' => ['1e3', TokenKind::Approximate];

        yield 'an exponent in upper case' => ['1E3', TokenKind::Approximate];

        yield 'a float suffix' => ['1.5f', TokenKind::Approximate];

        yield 'a double suffix in upper case' => ['2D', TokenKind::Approximate];
    }

    /**
     * @throws GqlException
     */
    #[DataProvider('providerSymbols')]
    public function testScanSymbolReadsAnOperatorOrAPieceOfPunctuation(string $written, Token $expected): void
    {
        self::assertEquals($expected, Lexer::over($written)->scanSymbol());
    }

    /**
     * @return iterable<string, array{string, Token}>
     */
    public static function providerSymbols(): iterable
    {
        yield 'an inequality' => ['<> 3', new Token(TokenKind::Symbol, '<>', '<>', 1, 1, 0)];

        yield 'an ordering or equality' => ['<= 3', new Token(TokenKind::Symbol, '<=', '<=', 1, 1, 0)];

        yield 'the other ordering or equality' => ['>= 3', new Token(TokenKind::Symbol, '>=', '>=', 1, 1, 0)];

        yield 'a concatenation' => ["|| 'a'", new Token(TokenKind::Symbol, '||', '||', 1, 1, 0)];

        yield 'a tilde' => ['~(b)', new Token(TokenKind::Symbol, '~', '~', 1, 1, 0)];

        yield 'a less-than sign before a minus' => ['<-', new Token(TokenKind::Symbol, '<', '<', 1, 1, 0)];
    }

    /**
     * @throws GqlException
     */
    #[DataProvider('providerCharactersAQueryIsNotWrittenWith')]
    public function testScanSymbolReportsACharacterAQueryCannotBeWrittenWith(string $written): void
    {
        $this->expectException(GqlException::class);
        $this->expectExceptionMessage('[42001] error: syntax error or access rule violation - invalid syntax: a query cannot be written with this character');

        Lexer::over($written)->scanSymbol();
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function providerCharactersAQueryIsNotWrittenWith(): iterable
    {
        yield 'an at sign with no string after it' => ['@'];

        yield 'a dollar sign' => ['$x'];

        yield 'a semicolon' => [';'];
    }
}
