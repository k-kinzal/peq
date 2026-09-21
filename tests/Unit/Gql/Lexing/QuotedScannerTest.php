<?php

declare(strict_types=1);

namespace Tests\Unit\Gql\Lexing;

use App\Gql\GqlException;
use App\Gql\Lexing\QuotedScanner;
use App\Gql\Lexing\SourceCursor;
use App\Gql\Lexing\Token;
use App\Gql\Lexing\TokenKind;
use App\Gql\StatusCode;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(QuotedScanner::class)]
#[UsesClass(GqlException::class)]
#[UsesClass(SourceCursor::class)]
#[UsesClass(StatusCode::class)]
#[UsesClass(Token::class)]
#[UsesClass(TokenKind::class)]
#[Small]
final class QuotedScannerTest extends TestCase
{
    /**
     * @throws GqlException
     */
    #[DataProvider('providerStringsAndWhatTheyStandFor')]
    public function testTextReadsTheCharactersBetweenTheQuotes(string $written, Token $expected): void
    {
        self::assertEquals($expected, QuotedScanner::text(new SourceCursor($written)));
    }

    /**
     * @return iterable<string, array{string, Token}>
     */
    public static function providerStringsAndWhatTheyStandFor(): iterable
    {
        yield 'in single quotes' => ["'/users'", new Token(TokenKind::Text, "'/users'", '/users', 1, 1, 0)];

        yield 'in double quotes' => ['"/users"', new Token(TokenKind::Text, '"/users"', '/users', 1, 1, 0)];

        yield 'holding nothing at all' => ["''", new Token(TokenKind::Text, "''", '', 1, 1, 0)];

        yield 'up to its closing quote and no further' => ["'a' || 'b'", new Token(TokenKind::Text, "'a'", 'a', 1, 1, 0)];

        yield 'with a doubled single quote inside it' => ["'it''s'", new Token(TokenKind::Text, "'it''s'", "it's", 1, 1, 0)];

        yield 'with a doubled double quote inside it' => ['"say ""hi"""', new Token(TokenKind::Text, '"say ""hi"""', 'say "hi"', 1, 1, 0)];

        yield 'with an escaped single quote' => ["'it\\'s'", new Token(TokenKind::Text, "'it\\'s'", "it's", 1, 1, 0)];

        yield 'with an escaped double quote' => ["'a\\\"b'", new Token(TokenKind::Text, "'a\\\"b'", 'a"b', 1, 1, 0)];

        yield 'with an escaped back quote' => ["'a\\`b'", new Token(TokenKind::Text, "'a\\`b'", 'a`b', 1, 1, 0)];

        yield 'with an escaped backslash' => ["'a\\\\b'", new Token(TokenKind::Text, "'a\\\\b'", 'a\b', 1, 1, 0)];

        yield 'with an escaped tab' => ["'a\\tb'", new Token(TokenKind::Text, "'a\\tb'", "a\tb", 1, 1, 0)];

        yield 'with an escaped backspace' => ["'a\\bb'", new Token(TokenKind::Text, "'a\\bb'", "a\x08b", 1, 1, 0)];

        yield 'with an escaped newline' => ["'a\\nb'", new Token(TokenKind::Text, "'a\\nb'", "a\nb", 1, 1, 0)];

        yield 'with an escaped carriage return' => ["'a\\rb'", new Token(TokenKind::Text, "'a\\rb'", "a\rb", 1, 1, 0)];

        yield 'with an escaped form feed' => ["'a\\fb'", new Token(TokenKind::Text, "'a\\fb'", "a\fb", 1, 1, 0)];

        yield 'with a character named by four hexadecimal digits' => ["'caf\\u00e9'", new Token(TokenKind::Text, "'caf\\u00e9'", 'café', 1, 1, 0)];

        yield 'with a character named by six hexadecimal digits' => ["'\\U004E2D'", new Token(TokenKind::Text, "'\\U004E2D'", '中', 1, 1, 0)];

        yield 'after an at sign, where a backslash escapes nothing' => ["@'a\\tb'", new Token(TokenKind::Text, "@'a\\tb'", 'a\tb', 1, 1, 0)];

        yield 'after an at sign, in double quotes' => ['@"a\qb"', new Token(TokenKind::Text, '@"a\qb"', 'a\qb', 1, 1, 0)];

        yield 'after an at sign, with a doubled quote inside it' => ["@'it''s'", new Token(TokenKind::Text, "@'it''s'", "it's", 1, 1, 0)];
    }

    /**
     * @throws GqlException
     */
    public function testTextReportsAStringThatIsNeverClosed(): void
    {
        $this->expectException(GqlException::class);
        $this->expectExceptionMessage('a character string is never closed at line 1, column 1 (found "\'unfinished")');

        QuotedScanner::text(new SourceCursor("'unfinished"));
    }

    /**
     * @throws GqlException
     */
    #[DataProvider('providerEscapesGqlDoesNotWrite')]
    public function testTextRefusesABackslashBeforeAnythingGqlDoesNotEscape(string $written): void
    {
        $this->expectException(GqlException::class);
        $this->expectExceptionMessage('[42001] error: syntax error or access rule violation - invalid syntax: an escape GQL writes after a backslash');

        QuotedScanner::text(new SourceCursor($written));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function providerEscapesGqlDoesNotWrite(): iterable
    {
        yield 'a letter that names nothing' => ["'a\\qb'"];

        yield 'a zero' => ["'a\\0b'"];

        yield 'a hexadecimal escape in the C form' => ["'\\x41'"];
    }

    /**
     * @param array{string, string} $expected
     *
     * @throws GqlException
     */
    #[DataProvider('providerEscapesAndWhatTheyStandFor')]
    public function testEscapeReadsOneEscapeFromItsBackslashOn(string $written, array $expected): void
    {
        self::assertSame($expected, QuotedScanner::escape(new SourceCursor($written)));
    }

    /**
     * @return iterable<string, array{string, array{string, string}}>
     */
    public static function providerEscapesAndWhatTheyStandFor(): iterable
    {
        yield 'a newline' => ['\n', ['\n', "\n"]];

        yield 'a tab' => ['\t', ['\t', "\t"]];

        yield 'a backslash' => ['\\\\', ['\\\\', '\\']];

        yield 'a single quote' => ["\\'", ["\\'", "'"]];

        yield 'a character by four hexadecimal digits' => ['\u00e9', ['\u00e9', 'é']];

        yield 'a character by four hexadecimal digits, and nothing after them' => ['\u00e9a', ['\u00e9', 'é']];

        yield 'a character by six hexadecimal digits' => ['\U004E2D', ['\U004E2D', '中']];
    }

    /**
     * @throws GqlException
     */
    public function testEscapeMovesPastWhatItRead(): void
    {
        $cursor = new SourceCursor('\u00e9a');
        QuotedScanner::escape($cursor);

        self::assertSame('a', $cursor->peek());
    }

    /**
     * @throws GqlException
     */
    #[DataProvider('providerCodePointsThatNameNoCharacter')]
    public function testEscapeRefusesACodePointThatNamesNoCharacter(string $written, string $message): void
    {
        $this->expectException(GqlException::class);
        $this->expectExceptionMessage($message);

        QuotedScanner::escape(new SourceCursor($written));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function providerCodePointsThatNameNoCharacter(): iterable
    {
        yield 'too few digits after \u' => ['\u00e', 'a \u escape is followed by 4 hexadecimal digits naming a character at line 1, column 1 (found "\u")'];

        yield 'a digit that is not hexadecimal' => ['\u00g9', 'a \u escape is followed by 4 hexadecimal digits naming a character'];

        yield 'half of a surrogate pair' => ['\uD800', 'a \u escape is followed by 4 hexadecimal digits naming a character'];

        yield 'past the last code point' => ['\UFFFFFF', 'a \U escape is followed by 6 hexadecimal digits naming a character'];
    }

    /**
     * @throws GqlException
     */
    public function testEscapeRefusesALetterGqlDoesNotEscape(): void
    {
        $this->expectException(GqlException::class);
        $this->expectExceptionMessage('an escape GQL writes after a backslash — \\\, \\\', \", \`, \t, \b, \n, \r, \f, \u or \U at line 1, column 1 (found "\q")');

        QuotedScanner::escape(new SourceCursor('\q'));
    }

    /**
     * @throws GqlException
     */
    #[DataProvider('providerQuotedNamesAndWhatTheyStandFor')]
    public function testNameReadsTheTextBetweenTheBackQuotes(string $written, Token $expected): void
    {
        self::assertEquals($expected, QuotedScanner::name(new SourceCursor($written)));
    }

    /**
     * @return iterable<string, array{string, Token}>
     */
    public static function providerQuotedNamesAndWhatTheyStandFor(): iterable
    {
        yield 'a word GQL reserves' => ['`return`', new Token(TokenKind::QuotedName, '`return`', 'return', 1, 1, 0)];

        yield 'with a doubled back quote inside it' => ['`a``b`', new Token(TokenKind::QuotedName, '`a``b`', 'a`b', 1, 1, 0)];

        yield 'with a backslash, which escapes nothing in a name' => ['`a\nb`', new Token(TokenKind::QuotedName, '`a\nb`', 'a\nb', 1, 1, 0)];
    }

    /**
     * @throws GqlException
     */
    public function testNameReportsANameThatIsNeverClosed(): void
    {
        $this->expectException(GqlException::class);
        $this->expectExceptionMessage('a quoted name is never closed at line 1, column 1 (found "`unfinished")');

        QuotedScanner::name(new SourceCursor('`unfinished'));
    }
}
