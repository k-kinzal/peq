<?php

declare(strict_types=1);

namespace Tests\Unit\Gql\Parsing;

use App\Gql\GqlException;
use App\Gql\Lexing\Lexer;
use App\Gql\Lexing\SourceCursor;
use App\Gql\Lexing\Token;
use App\Gql\Lexing\TokenKind;
use App\Gql\Lexing\TokenList;
use App\Gql\Parsing\QuantifierParser;
use App\Gql\Parsing\TokenReader;
use App\Gql\ReservedWords;
use App\Gql\StatusCode;
use App\Gql\Syntax\Pattern\EdgeDirection;
use App\Gql\Syntax\Pattern\EdgePattern;
use App\Gql\Syntax\Pattern\ElementFilter;
use App\Gql\Syntax\Pattern\GroupPattern;
use App\Gql\Syntax\Pattern\NodePattern;
use App\Gql\Syntax\Pattern\PathTerm;
use App\Gql\Syntax\Pattern\Quantifier;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(QuantifierParser::class)]
#[UsesClass(GqlException::class)]
#[UsesClass(StatusCode::class)]
#[UsesClass(Lexer::class)]
#[UsesClass(SourceCursor::class)]
#[UsesClass(Token::class)]
#[UsesClass(TokenKind::class)]
#[UsesClass(TokenList::class)]
#[UsesClass(TokenReader::class)]
#[UsesClass(ReservedWords::class)]
#[UsesClass(EdgeDirection::class)]
#[UsesClass(EdgePattern::class)]
#[UsesClass(ElementFilter::class)]
#[UsesClass(GroupPattern::class)]
#[UsesClass(NodePattern::class)]
#[UsesClass(Quantifier::class)]
#[Small]
final class QuantifierParserTest extends TestCase
{
    /**
     * @throws GqlException
     */
    #[DataProvider('providerBoundedRepetitions')]
    public function testParseReadsABoundedRepetitionInAWalk(string $written, Quantifier $expected): void
    {
        self::assertEquals($expected, (new QuantifierParser(TokenReader::of($written)))->parse(false));
    }

    /**
     * @return iterable<string, array{string, Quantifier}>
     */
    public static function providerBoundedRepetitions(): iterable
    {
        yield 'an exact number of times' => ['{3}', new Quantifier(3, 3)];

        yield 'between two bounds' => ['{1,3}', new Quantifier(1, 3)];

        yield 'with no lower bound written' => ['{,3}', new Quantifier(0, 3)];

        yield 'not at all' => ['{0}', new Quantifier(0, 0)];
    }

    /**
     * @throws GqlException
     */
    #[DataProvider('providerUnboundedRepetitions')]
    public function testParseReadsAnUnboundedRepetitionUnderARestrictor(string $written, Quantifier $expected): void
    {
        self::assertEquals($expected, (new QuantifierParser(TokenReader::of($written)))->parse(true));
    }

    /**
     * @return iterable<string, array{string, Quantifier}>
     */
    public static function providerUnboundedRepetitions(): iterable
    {
        yield 'zero or more times' => ['*', new Quantifier(0, null)];

        yield 'one or more times' => ['+', new Quantifier(1, null)];

        yield 'at least a number of times' => ['{1,}', new Quantifier(1, null)];

        yield 'with neither bound written' => ['{,}', new Quantifier(0, null)];
    }

    /**
     * @throws GqlException
     */
    #[DataProvider('providerUnboundedRepetitionsAndWhereTheyAreWritten')]
    public function testParseRefusesAnUnboundedRepetitionInAWalk(string $written, string $found): void
    {
        $this->expectException(GqlException::class);
        $this->expectExceptionMessage('[42001] error: syntax error or access rule violation - invalid syntax: expected a restrictor before the path, since a quantifier with no upper bound repeats without end in a walk: write TRAIL, SIMPLE or ACYCLIC at line 1, column 1 (found '.$found.')');

        (new QuantifierParser(TokenReader::of($written)))->parse(false);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function providerUnboundedRepetitionsAndWhereTheyAreWritten(): iterable
    {
        yield 'zero or more times' => ['*', '"*"'];

        yield 'one or more times' => ['+', '"+"'];

        yield 'at least a number of times' => ['{1,}', '"{"'];
    }

    /**
     * @throws GqlException
     */
    public function testParseReadsNoRepetitionWhereNoneIsWritten(): void
    {
        $tokens = TokenReader::of('(b)');

        self::assertNull((new QuantifierParser($tokens))->parse(false));
        self::assertSame(0, $tokens->position());
    }

    /**
     * @throws GqlException
     */
    #[DataProvider('providerRepetitionsInBraces')]
    public function testParseBracesReadsAQuantifierWrittenInBraces(string $written, Quantifier $expected): void
    {
        self::assertEquals($expected, (new QuantifierParser(TokenReader::of($written)))->parseBraces());
    }

    /**
     * @return iterable<string, array{string, Quantifier}>
     */
    public static function providerRepetitionsInBraces(): iterable
    {
        yield 'both bounds' => ['{1,3}', new Quantifier(1, 3)];

        yield 'one bound for both ends' => ['{3}', new Quantifier(3, 3)];

        yield 'the same bound twice' => ['{2,2}', new Quantifier(2, 2)];

        yield 'only the lower bound' => ['{2,}', new Quantifier(2, null)];

        yield 'only the upper bound' => ['{,2}', new Quantifier(0, 2)];
    }

    /**
     * @throws GqlException
     */
    #[DataProvider('providerBracesThatAreNotAQuantifier')]
    public function testParseBracesReportsBracesThatAreNotAQuantifier(string $written, string $message): void
    {
        $this->expectException(GqlException::class);
        $this->expectExceptionMessage($message);

        (new QuantifierParser(TokenReader::of($written)))->parseBraces();
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function providerBracesThatAreNotAQuantifier(): iterable
    {
        yield 'a lower bound above the upper one' => ['{3,1}', 'expected an upper bound no lower than the lower one at line 1, column 5 (found "}")'];

        yield 'no bound at all' => ['{}', 'expected a number of repetitions at line 1, column 2 (found "}")'];

        yield 'a bound that is not a whole number' => ['{1.5}', 'expected a number of repetitions at line 1, column 2 (found "1.5")'];

        yield 'a brace that is never closed' => ['{1', 'expected "}" at line 1, column 3 (found the end of the query)'];
    }

    /**
     * @param list<PathTerm> $terms
     */
    #[DataProvider('providerStretchesOfPath')]
    public function testCrossesAnEdgeReportsWhetherAStretchOfPathCrossesAnEdge(array $terms, bool $expected): void
    {
        self::assertSame($expected, QuantifierParser::crossesAnEdge($terms));
    }

    /**
     * @return iterable<string, array{list<PathTerm>, bool}>
     */
    public static function providerStretchesOfPath(): iterable
    {
        yield 'a single node' => [[new NodePattern()], false];

        yield 'two nodes and the edge between them' => [[new NodePattern(), new EdgePattern(EdgeDirection::Along), new NodePattern()], true];

        yield 'a group holding an edge' => [[new GroupPattern([new NodePattern(), new EdgePattern(EdgeDirection::Either), new NodePattern()])], true];

        yield 'a group holding only a node' => [[new GroupPattern([new NodePattern()])], false];

        yield 'a group of a group holding an edge' => [[new GroupPattern([new GroupPattern([new NodePattern(), new EdgePattern(EdgeDirection::Against), new NodePattern()])])], true];

        yield 'nothing at all' => [[], false];
    }

    /**
     * @throws GqlException
     */
    public function testParseBracesRefusesABoundTooLargeToHold(): void
    {
        $this->expectException(GqlException::class);
        $this->expectExceptionMessage('[22003] error: data exception - numeric value out of range');

        (new QuantifierParser(TokenReader::of('{1,99999999999999999999}')))->parseBraces();
    }

    /**
     * @throws GqlException
     */
    public function testParseBracesReadsABoundWrittenWithGroupedDigits(): void
    {
        self::assertEquals(new Quantifier(1, 1000), (new QuantifierParser(TokenReader::of('{1,1_000}')))->parseBraces());
    }
}
