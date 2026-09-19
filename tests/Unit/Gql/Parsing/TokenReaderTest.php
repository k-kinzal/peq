<?php

declare(strict_types=1);

namespace Tests\Unit\Gql\Parsing;

use App\Gql\GqlException;
use App\Gql\Lexing\Lexer;
use App\Gql\Lexing\QuotedScanner;
use App\Gql\Lexing\SourceCursor;
use App\Gql\Lexing\Token;
use App\Gql\Lexing\TokenKind;
use App\Gql\Lexing\TokenList;
use App\Gql\Parsing\TokenReader;
use App\Gql\StatusCode;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(TokenReader::class)]
#[UsesClass(Lexer::class)]
#[UsesClass(QuotedScanner::class)]
#[UsesClass(SourceCursor::class)]
#[UsesClass(Token::class)]
#[UsesClass(TokenKind::class)]
#[UsesClass(TokenList::class)]
#[UsesClass(GqlException::class)]
#[UsesClass(StatusCode::class)]
#[Small]
final class TokenReaderTest extends TestCase
{
    /**
     * @throws GqlException
     */
    public function testOfStandsAtTheFirstPieceOfTheQuery(): void
    {
        self::assertSame('MATCH', TokenReader::of('MATCH (p)')->current()->value);
    }

    /**
     * @throws GqlException
     */
    public function testCurrentIsThePieceTheReadingStandsAt(): void
    {
        self::assertSame('LIMIT', TokenReader::of('LIMIT 10')->current()->value);
    }

    /**
     * @throws GqlException
     */
    public function testPeekLooksAheadWithoutMovingTheReadingOn(): void
    {
        self::assertSame('=', TokenReader::of('p = (a)')->peek()->lexeme);
    }

    /**
     * @throws GqlException
     */
    public function testTakeReturnsThePieceThatWasCurrentAndMovesOn(): void
    {
        $reader = TokenReader::of('LIMIT 10');
        $reader->take();

        self::assertSame('10', $reader->current()->value);
    }

    /**
     * @throws GqlException
     */
    public function testPositionCountsHowManyPiecesHaveBeenRead(): void
    {
        self::assertSame(0, TokenReader::of('LIMIT 10')->position());
    }

    /**
     * @throws GqlException
     */
    public function testSeekPutsTheReadingBackWhereItWas(): void
    {
        $reader = TokenReader::of('LIMIT 10');
        $reader->take();
        $reader->seek(0);

        self::assertSame('LIMIT', $reader->current()->value);
    }

    /**
     * @throws GqlException
     */
    public function testTextSinceQuotesTheQueryTheWayTheReaderWroteIt(): void
    {
        $reader = TokenReader::of('RETURN p.firstName');
        $reader->take();
        $reader->take();
        $reader->take();
        $reader->take();

        self::assertSame('p.firstName', $reader->textSince(1));
    }

    /**
     * @throws GqlException
     */
    public function testAtSymbolReportsWhatTheReadingStandsAt(): void
    {
        self::assertTrue(TokenReader::of('(p)')->atSymbol('('));
    }

    /**
     * @throws GqlException
     */
    public function testAtKeywordRecognisesAKeywordHoweverItIsCased(): void
    {
        self::assertTrue(TokenReader::of('match (p)')->atKeyword('MATCH'));
    }

    /**
     * @throws GqlException
     */
    public function testAtNameReportsAVariableAsAName(): void
    {
        self::assertTrue(TokenReader::of('p.firstName')->atName());
    }

    /**
     * @throws GqlException
     */
    public function testAtNameDoesNotReportPunctuationAsAName(): void
    {
        self::assertFalse(TokenReader::of('(p)')->atName());
    }

    /**
     * @throws GqlException
     */
    public function testAcceptSymbolTakesASymbolThatIsThere(): void
    {
        self::assertTrue(TokenReader::of(', p')->acceptSymbol(','));
    }

    /**
     * @throws GqlException
     */
    public function testAcceptSymbolLeavesOneThatIsNot(): void
    {
        $reader = TokenReader::of('p');

        self::assertFalse($reader->acceptSymbol(','));
        self::assertSame(0, $reader->position());
    }

    /**
     * @throws GqlException
     */
    public function testAcceptKeywordTakesAKeywordThatIsThere(): void
    {
        self::assertTrue(TokenReader::of('DISTINCT p')->acceptKeyword('DISTINCT'));
    }

    /**
     * @throws GqlException
     */
    public function testExpectSymbolTakesASymbolTheGrammarRequires(): void
    {
        self::assertSame('(', TokenReader::of('(p)')->expectSymbol('(')->lexeme);
    }

    /**
     * @throws GqlException
     */
    public function testExpectSymbolReportsOneThatIsMissing(): void
    {
        $this->expectException(GqlException::class);
        $this->expectExceptionMessage('expected "("');

        TokenReader::of('p)')->expectSymbol('(');
    }

    /**
     * @throws GqlException
     */
    public function testExpectKeywordTakesAKeywordTheGrammarRequires(): void
    {
        self::assertSame('BY', TokenReader::of('BY x')->expectKeyword('BY')->value);
    }

    /**
     * @throws GqlException
     */
    public function testExpectKeywordReportsOneThatIsMissing(): void
    {
        $this->expectException(GqlException::class);
        $this->expectExceptionMessage('expected BY');

        TokenReader::of('x')->expectKeyword('BY');
    }

    /**
     * @throws GqlException
     */
    public function testExpectNameTakesANameTheGrammarRequires(): void
    {
        self::assertSame('firstName', TokenReader::of('firstName')->expectName());
    }

    /**
     * @throws GqlException
     */
    public function testExpectNameReadsANameInBackticksAsAName(): void
    {
        self::assertSame('return', TokenReader::of('`return`')->expectName());
    }

    /**
     * @throws GqlException
     */
    public function testExpectNameReportsSomethingThatIsNotOne(): void
    {
        $this->expectException(GqlException::class);
        $this->expectExceptionMessage('expected a name');

        TokenReader::of('(')->expectName();
    }

    /**
     * @throws GqlException
     */
    public function testFailSaysWhatWasWantedAndWhatWasWritten(): void
    {
        $this->expectException(GqlException::class);
        $this->expectExceptionMessage('expected an expression at line 1, column 1 (found "RETURN")');

        TokenReader::of('RETURN')->fail('an expression');
    }
}
