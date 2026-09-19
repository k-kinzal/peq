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
use Tests\Fixture\Gql\TokenSpelling;

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
        self::assertSame('MATCH', Lexer::over('MATCH (p)')->tokenize()->current()->value);
    }

    /**
     * @throws GqlException
     */
    public function testTokenizeEndsTheListWithThePieceStandingForTheEnd(): void
    {
        self::assertSame(TokenKind::End, Lexer::over('LIMIT 10')->tokenize()->at(2)->kind);
    }

    /**
     * @throws GqlException
     */
    public function testTokenizeReadsAQueryThatSaysNothingAsOnlyItsEnd(): void
    {
        self::assertSame(TokenKind::End, Lexer::over('')->tokenize()->at(0)->kind);
    }

    #[DataProvider('providerQueriesAndTheirPieces')]
    public function testNextReadsThePiecesAQueryIsWrittenFrom(string $query, string $expected): void
    {
        self::assertSame($expected, TokenSpelling::of($query));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function providerQueriesAndTheirPieces(): iterable
    {
        yield 'a name and a number' => ['LIMIT 10', 'Name(LIMIT) Integer(10)'];

        yield 'a name in backticks' => ['`return`', 'QuotedName(return)'];

        yield 'a string' => ["'a'", 'Text(a)'];

        yield 'a property access' => ['p.name', 'Name(p) Symbol(.) Name(name)'];

        yield 'a concatenation' => ["a || 'b'", 'Name(a) Symbol(||) Text(b)'];

        yield 'an edge pattern, in pieces' => ['-[:x]->', 'Symbol(-) Symbol([) Symbol(:) Name(x) Symbol(]) Symbol(-) Symbol(>)'];

        yield 'a line note in the C form' => ["// a note\nLIMIT", 'Name(LIMIT)'];

        yield 'a line note in the SQL form' => ["-- a note\nLIMIT", 'Name(LIMIT)'];

        yield 'a block note' => ['/* a note */ LIMIT', 'Name(LIMIT)'];

        yield 'a block note that is never closed' => ['/* a note that runs out', ''];

        yield 'a label disjunction' => [':A|B', 'Symbol(:) Name(A) Symbol(|) Name(B)'];

        yield 'a wildcard label' => [':%', 'Symbol(:) Symbol(%)'];
    }

    /**
     * @throws GqlException
     */
    public function testSkipTriviaMovesPastEverythingThatStandsBetweenPieces(): void
    {
        self::assertSame('LIMIT', Lexer::over('  -- a note'."\n".'LIMIT')->next()->value);
    }

    /**
     * @throws GqlException
     */
    public function testScanNameReadsANameAsFarAsANameCanGo(): void
    {
        self::assertSame('firstName', Lexer::over('firstName || x')->next()->value);
    }

    /**
     * @throws GqlException
     */
    #[DataProvider('providerNumbersAndWhetherTheyAreExact')]
    public function testScanNumberTellsAWholeNumberFromAnApproximateOne(string $written, TokenKind $kind): void
    {
        self::assertSame($kind, Lexer::over($written)->next()->kind);
    }

    /**
     * @return iterable<string, array{string, TokenKind}>
     */
    public static function providerNumbersAndWhetherTheyAreExact(): iterable
    {
        yield 'a whole number' => ['42', TokenKind::Integer];

        yield 'a number with a fractional part' => ['1.5', TokenKind::Decimal];

        yield 'a number with an exponent' => ['1e10', TokenKind::Decimal];

        yield 'a number with a negative exponent' => ['1.5e-3', TokenKind::Decimal];

        yield 'a number with the suffix that marks it approximate' => ['1.0d', TokenKind::Decimal];
    }

    /**
     * @throws GqlException
     */
    public function testScanSymbolReadsAnOperatorWrittenAsTwoCharactersAsOnePiece(): void
    {
        self::assertSame('<>', Lexer::over('<> 3')->next()->lexeme);
    }

    /**
     * @throws GqlException
     */
    public function testScanSymbolReportsACharacterAQueryCannotBeWrittenWith(): void
    {
        $this->expectException(GqlException::class);
        $this->expectExceptionMessage('a query cannot be written with this character');

        Lexer::over('@')->next();
    }

    public function testAnArrowArrivesInPiecesSoThatArithmeticStaysArithmetic(): void
    {
        self::assertSame('Integer(1) Symbol(<) Symbol(-) Integer(2)', TokenSpelling::of('1 < -2'));
    }
}
