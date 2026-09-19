<?php

declare(strict_types=1);

namespace Tests\Unit\Gql\Lexing;

use App\Gql\Lexing\Lexer;
use App\Gql\Lexing\SourceCursor;
use App\Gql\Lexing\Token;
use App\Gql\Lexing\TokenKind;
use App\Gql\Lexing\TokenList;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(TokenList::class)]
#[UsesClass(Lexer::class)]
#[UsesClass(SourceCursor::class)]
#[UsesClass(Token::class)]
#[UsesClass(TokenKind::class)]
#[Small]
final class TokenListTest extends TestCase
{
    /**
     * @throws \App\Gql\GqlException
     */
    public function testCurrentIsTheFirstPieceBeforeAnythingIsRead(): void
    {
        self::assertSame('MATCH', Lexer::over('MATCH (p)')->tokenize()->current()->value);
    }

    /**
     * @throws \App\Gql\GqlException
     */
    public function testPeekLooksAheadWithoutMovingTheReadingOn(): void
    {
        $pieces = Lexer::over('p = (a)')->tokenize();

        self::assertSame('=', $pieces->peek()->lexeme);
        self::assertSame('p', $pieces->current()->value);
    }

    /**
     * @throws \App\Gql\GqlException
     */
    public function testAtCountsFromTheStartOfTheQuery(): void
    {
        self::assertSame('10', Lexer::over('LIMIT 10')->tokenize()->at(1)->value);
    }

    /**
     * @throws \App\Gql\GqlException
     */
    public function testAtAnswersPastTheEndWithTheEnd(): void
    {
        self::assertSame(TokenKind::End, Lexer::over('LIMIT 10')->tokenize()->at(99)->kind);
    }

    /**
     * @throws \App\Gql\GqlException
     */
    public function testTakeReturnsThePieceThatWasCurrentAndMovesOn(): void
    {
        $pieces = Lexer::over('MATCH (p)')->tokenize();

        self::assertSame('MATCH', $pieces->take()->value);
        self::assertSame('(', $pieces->current()->lexeme);
    }

    /**
     * @throws \App\Gql\GqlException
     */
    public function testTakeNeverMovesPastTheEndOfTheQuery(): void
    {
        $pieces = Lexer::over('')->tokenize();
        $pieces->take();

        self::assertSame(TokenKind::End, $pieces->current()->kind);
        self::assertSame(0, $pieces->position());
    }

    /**
     * @throws \App\Gql\GqlException
     */
    public function testPositionCountsHowManyPiecesHaveBeenRead(): void
    {
        self::assertSame(0, Lexer::over('MATCH (p)')->tokenize()->position());
    }

    /**
     * @throws \App\Gql\GqlException
     */
    public function testSeekPutsTheReadingBackWhereItWas(): void
    {
        $pieces = Lexer::over('MATCH (p)')->tokenize();
        $pieces->take();
        $pieces->seek(0);

        self::assertSame('MATCH', $pieces->current()->value);
    }

    /**
     * @throws \App\Gql\GqlException
     */
    public function testSeekWillNotGoPastEitherEndOfTheQuery(): void
    {
        $pieces = Lexer::over('LIMIT 10')->tokenize();
        $pieces->seek(-5);

        self::assertSame(0, $pieces->position());
    }

    /**
     * @throws \App\Gql\GqlException
     */
    public function testTextBetweenQuotesTheQueryTheWayTheReaderWroteIt(): void
    {
        $pieces = Lexer::over("RETURN p.firstName || ' '")->tokenize();

        self::assertSame("p.firstName || ' '", $pieces->textBetween(1, 6));
    }

    /**
     * @throws \App\Gql\GqlException
     */
    public function testTextBetweenTwoPlacesThatAreTheSamePlaceIsNothing(): void
    {
        self::assertSame('', Lexer::over('RETURN p')->tokenize()->textBetween(1, 1));
    }
}
