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
use App\Gql\Parsing\NameReader;
use App\Gql\Parsing\TokenReader;
use App\Gql\ReservedWords;
use App\Gql\StatusCode;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(NameReader::class)]
#[UsesClass(Lexer::class)]
#[UsesClass(QuotedScanner::class)]
#[UsesClass(SourceCursor::class)]
#[UsesClass(Token::class)]
#[UsesClass(TokenKind::class)]
#[UsesClass(TokenList::class)]
#[UsesClass(TokenReader::class)]
#[UsesClass(GqlException::class)]
#[UsesClass(StatusCode::class)]
#[UsesClass(ReservedWords::class)]
#[Small]
final class NameReaderTest extends TestCase
{
    /**
     * @throws GqlException
     */
    public function testAtWordReportsAWordWrittenPlainlyAsOne(): void
    {
        self::assertTrue(NameReader::atWord(TokenReader::of('upper(x)')));
    }

    /**
     * @throws GqlException
     */
    public function testAtWordDoesNotReportANameInBackQuotesAsAWord(): void
    {
        self::assertFalse(NameReader::atWord(TokenReader::of('`upper`(x)')));
    }

    /**
     * @throws GqlException
     */
    public function testAtVariableReportsANameGqlLeavesFreeAsOneAQueryMayBind(): void
    {
        self::assertTrue(NameReader::atVariable(TokenReader::of('p.firstName')));
    }

    /**
     * @throws GqlException
     */
    public function testAtVariableDoesNotReportAWordGqlReservesAsOne(): void
    {
        self::assertFalse(NameReader::atVariable(TokenReader::of('WHERE p.line > 1')));
    }

    /**
     * @throws GqlException
     */
    public function testAtVariableDoesNotReportANameInBackQuotesAsOne(): void
    {
        self::assertFalse(NameReader::atVariable(TokenReader::of('`value`')));
    }

    /**
     * @throws GqlException
     */
    public function testIdentifierTakesANameGqlLeavesFree(): void
    {
        self::assertSame('firstName', NameReader::identifier(TokenReader::of('firstName')));
    }

    /**
     * @throws GqlException
     */
    public function testIdentifierTakesAReservedWordWrittenInBackQuotes(): void
    {
        self::assertSame('value', NameReader::identifier(TokenReader::of('`value`')));
    }

    /**
     * @throws GqlException
     */
    public function testIdentifierTakesAReservedWordWrittenInDoubleQuotes(): void
    {
        self::assertSame('value', NameReader::identifier(TokenReader::of('"value"')));
    }

    /**
     * @throws GqlException
     */
    public function testIdentifierMovesPastTheNameItTakes(): void
    {
        $tokens = TokenReader::of('"value".x');
        NameReader::identifier($tokens);

        self::assertSame(1, $tokens->position());
    }

    /**
     * @throws GqlException
     */
    public function testIdentifierDoesNotTakeAStringInSingleQuotes(): void
    {
        $this->expectException(GqlException::class);
        $this->expectExceptionMessage('expected a name at line 1, column 1 (found "\'value\'")');

        NameReader::identifier(TokenReader::of("'value'"));
    }

    /**
     * @throws GqlException
     */
    public function testIdentifierRefusesAReservedWordWrittenPlainly(): void
    {
        $this->expectException(GqlException::class);
        $this->expectExceptionMessage('GQL reserves "VALUE"');

        NameReader::identifier(TokenReader::of('value'));
    }

    /**
     * @throws GqlException
     */
    public function testIdentifierReportsSomethingThatIsNotAName(): void
    {
        $this->expectException(GqlException::class);
        $this->expectExceptionMessage('expected a name');

        NameReader::identifier(TokenReader::of('(p)'));
    }

    /**
     * @throws GqlException
     */
    public function testVariableTakesANameAQueryMayBind(): void
    {
        self::assertSame('person', NameReader::variable(TokenReader::of('person')));
    }

    /**
     * @throws GqlException
     */
    public function testVariableRefusesAReservedWord(): void
    {
        $this->expectException(GqlException::class);
        $this->expectExceptionMessage('GQL reserves "VALUE"');

        NameReader::variable(TokenReader::of('value'));
    }

    /**
     * @throws GqlException
     */
    public function testVariableRefusesAReservedWordWrittenInBackQuotes(): void
    {
        $this->expectException(GqlException::class);
        $this->expectExceptionMessage('expected a name');

        NameReader::variable(TokenReader::of('`value`'));
    }

    /**
     * @throws GqlException
     */
    public function testVariableReportsSomethingThatIsNotAName(): void
    {
        $this->expectException(GqlException::class);
        $this->expectExceptionMessage('expected a name');

        NameReader::variable(TokenReader::of('(p)'));
    }

    /**
     * @throws GqlException
     */
    public function testRefuseReservedSaysWhichWordIsReservedAndWhatToWriteInstead(): void
    {
        $this->expectException(GqlException::class);
        $this->expectExceptionMessage('GQL reserves "VALUE", so it is not a name here: write it in back quotes');

        NameReader::refuseReserved(TokenReader::of('value')->current(), 'write it in back quotes');
    }
}
