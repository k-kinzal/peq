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
use App\Gql\Parsing\StatementRefusal;
use App\Gql\Parsing\TokenReader;
use App\Gql\StatusCode;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(StatementRefusal::class)]
#[UsesClass(GqlException::class)]
#[UsesClass(StatusCode::class)]
#[UsesClass(Lexer::class)]
#[UsesClass(QuotedScanner::class)]
#[UsesClass(SourceCursor::class)]
#[UsesClass(Token::class)]
#[UsesClass(TokenKind::class)]
#[UsesClass(TokenList::class)]
#[UsesClass(TokenReader::class)]
#[Small]
final class StatementRefusalTest extends TestCase
{
    /**
     * @throws GqlException
     */
    public function testRejectReportsAStatementThatWouldChangeTheGraph(): void
    {
        $this->expectException(GqlException::class);
        $this->expectExceptionMessage('adds nodes and edges to a graph');

        StatementRefusal::reject(TokenReader::of('INSERT (p:Person)'));
    }

    /**
     * @throws GqlException
     */
    public function testRejectReportsItUnderTheStatusForAQueryPeqWillNotRun(): void
    {
        $this->expectExceptionMessage('42000');

        StatementRefusal::reject(TokenReader::of('DELETE (p)'));
    }

    /**
     * @throws GqlException
     */
    public function testRejectRecognisesTheStatementHoweverItIsCased(): void
    {
        $this->expectException(GqlException::class);
        $this->expectExceptionMessage('invokes a procedure');

        StatementRefusal::reject(TokenReader::of('call foo()'));
    }

    /**
     * @throws GqlException
     */
    public function testRejectLeavesAWordThatBeginsNoSuchStatement(): void
    {
        $tokens = TokenReader::of('MATCH (p)');
        StatementRefusal::reject($tokens);

        self::assertSame('MATCH', $tokens->current()->value);
    }

    public function testAllNamesEveryStatementPeqRefuses(): void
    {
        self::assertArrayHasKey('INSERT', StatementRefusal::all());
    }

    public function testAllSaysWhatEachRefusedStatementWouldHaveDone(): void
    {
        self::assertSame('removes nodes and edges from a graph', StatementRefusal::all()['DELETE']);
    }
}
