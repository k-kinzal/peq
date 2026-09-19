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
    public function testTextReadsTheCharactersBetweenTheQuotes(string $written, string $value): void
    {
        self::assertSame($value, QuotedScanner::text(new SourceCursor($written))->value);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function providerStringsAndWhatTheyStandFor(): iterable
    {
        yield 'in single quotes' => ["'/users'", '/users'];

        yield 'in double quotes' => ['"/users"', '/users'];

        yield 'with a doubled quote inside it' => ["'it''s'", "it's"];

        yield 'with an escaped quote inside it' => ["'it\\'s'", "it's"];

        yield 'with an escaped tab' => ['"a\tb"', "a\tb"];

        yield 'with an escaped newline' => ['"a\nb"', "a\nb"];

        yield 'with an escaped backslash' => ['"a\\\b"', 'a\b'];

        yield 'with an escape that names nothing' => ['"a\qb"', 'aqb'];

        yield 'holding nothing at all' => ["''", ''];
    }

    /**
     * @throws GqlException
     */
    public function testTextKeepsWhatTheQueryWroteSoThatAnErrorCanQuoteIt(): void
    {
        self::assertSame("'/users'", QuotedScanner::text(new SourceCursor("'/users'"))->lexeme);
    }

    /**
     * @throws GqlException
     */
    public function testTextReportsAStringThatIsNeverClosed(): void
    {
        $this->expectException(GqlException::class);
        $this->expectExceptionMessage('a character string is never closed');

        QuotedScanner::text(new SourceCursor("'unfinished"));
    }

    /**
     * @throws GqlException
     */
    public function testNameReadsTheTextBetweenTheBackticks(): void
    {
        self::assertSame('return', QuotedScanner::name(new SourceCursor('`return`'))->value);
    }

    /**
     * @throws GqlException
     */
    public function testNameReadsADoubledBacktickAsOneOfItself(): void
    {
        self::assertSame('a`b', QuotedScanner::name(new SourceCursor('`a``b`'))->value);
    }

    /**
     * @throws GqlException
     */
    public function testNameIsAKindOfItsOwnSoThatItIsNeverReadAsAKeyword(): void
    {
        self::assertSame(TokenKind::QuotedName, QuotedScanner::name(new SourceCursor('`match`'))->kind);
    }

    /**
     * @throws GqlException
     */
    public function testNameReportsANameThatIsNeverClosed(): void
    {
        $this->expectException(GqlException::class);
        $this->expectExceptionMessage('a quoted name is never closed');

        QuotedScanner::name(new SourceCursor('`unfinished'));
    }
}
