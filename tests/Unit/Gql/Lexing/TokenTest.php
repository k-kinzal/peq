<?php

declare(strict_types=1);

namespace Tests\Unit\Gql\Lexing;

use App\Gql\Lexing\Token;
use App\Gql\Lexing\TokenKind;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(Token::class)]
#[UsesClass(TokenKind::class)]
#[Small]
final class TokenTest extends TestCase
{
    public function testAPieceCarriesWhatItSaidAndWhatItStandsFor(): void
    {
        $piece = new Token(TokenKind::Text, "'a'", 'a', 2, 5, 11);

        self::assertSame(TokenKind::Text, $piece->kind);
        self::assertSame("'a'", $piece->lexeme);
        self::assertSame('a', $piece->value);
        self::assertSame(2, $piece->line);
        self::assertSame(5, $piece->column);
        self::assertSame(11, $piece->offset);
    }

    public function testIsSymbolRecognisesAPieceOfPunctuationByWhatItIsWrittenAs(): void
    {
        self::assertTrue((new Token(TokenKind::Symbol, '(', '(', 1, 1))->isSymbol('('));
    }

    public function testIsSymbolDoesNotRecogniseANameThatReadsLikeOne(): void
    {
        self::assertFalse((new Token(TokenKind::Name, 'p', 'p', 1, 1))->isSymbol('p'));
    }

    #[DataProvider('providerKeywordsHoweverTheyAreCased')]
    public function testIsKeywordRecognisesAKeywordHoweverItIsCased(string $written): void
    {
        self::assertTrue((new Token(TokenKind::Name, $written, $written, 1, 1))->isKeyword('MATCH'));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function providerKeywordsHoweverTheyAreCased(): iterable
    {
        yield 'as the grammar writes it' => ['MATCH'];

        yield 'in lower case' => ['match'];

        yield 'in mixed case' => ['Match'];
    }

    public function testIsKeywordReadsANameInBackticksAsAName(): void
    {
        self::assertFalse((new Token(TokenKind::QuotedName, '`match`', 'match', 1, 1))->isKeyword('MATCH'));
    }

    public function testDescribeQuotesAPieceTheWayTheReaderWroteIt(): void
    {
        self::assertSame('"RETRUN"', (new Token(TokenKind::Name, 'RETRUN', 'RETRUN', 1, 1))->describe());
    }

    public function testDescribeSaysTheEndOfAQueryInWordsBecauseItHasNothingToQuote(): void
    {
        self::assertSame('the end of the query', (new Token(TokenKind::End, '', '', 1, 1))->describe());
    }
}
