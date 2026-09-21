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
use App\Gql\Parsing\LabelParser;
use App\Gql\Parsing\NameReader;
use App\Gql\Parsing\TokenReader;
use App\Gql\ReservedWords;
use App\Gql\StatusCode;
use App\Gql\Syntax\Pattern\LabelOperator;
use App\Gql\Syntax\Pattern\LabelPattern;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(LabelParser::class)]
#[UsesClass(GqlException::class)]
#[UsesClass(StatusCode::class)]
#[UsesClass(Lexer::class)]
#[UsesClass(QuotedScanner::class)]
#[UsesClass(SourceCursor::class)]
#[UsesClass(Token::class)]
#[UsesClass(TokenKind::class)]
#[UsesClass(TokenList::class)]
#[UsesClass(TokenReader::class)]
#[UsesClass(NameReader::class)]
#[UsesClass(ReservedWords::class)]
#[UsesClass(LabelOperator::class)]
#[UsesClass(LabelPattern::class)]
#[Small]
final class LabelParserTest extends TestCase
{
    /**
     * @throws GqlException
     */
    #[DataProvider('providerLabelExpressions')]
    public function testParseReadsWhatAPatternRequiresOfTheLabels(string $written, LabelPattern $expected): void
    {
        self::assertEquals($expected, (new LabelParser(TokenReader::of($written)))->parse());
    }

    /**
     * @return iterable<string, array{string, LabelPattern}>
     */
    public static function providerLabelExpressions(): iterable
    {
        yield 'one label' => ['Method', LabelPattern::named('Method')];

        yield 'either of two' => ['Method|`Function`', LabelPattern::either(LabelPattern::named('Method'), LabelPattern::named('Function'))];

        yield 'either of three, read left to right' => [
            'A|B|C',
            LabelPattern::either(LabelPattern::either(LabelPattern::named('A'), LabelPattern::named('B')), LabelPattern::named('C')),
        ];

        yield 'both of two' => ['Member&Callable', LabelPattern::both(LabelPattern::named('Member'), LabelPattern::named('Callable'))];

        yield 'a refusal' => ['!Interface', LabelPattern::neither(LabelPattern::named('Interface'))];

        yield 'anything at all' => ['%', LabelPattern::anything()];

        yield 'a refusal binds tighter than a conjunction' => [
            '!A&B',
            LabelPattern::both(LabelPattern::neither(LabelPattern::named('A')), LabelPattern::named('B')),
        ];

        yield 'a conjunction binds tighter than a disjunction' => [
            '!A&B|C',
            LabelPattern::either(
                LabelPattern::both(LabelPattern::neither(LabelPattern::named('A')), LabelPattern::named('B')),
                LabelPattern::named('C'),
            ),
        ];

        yield 'parentheses override the binding' => [
            'A&(B|C)',
            LabelPattern::both(LabelPattern::named('A'), LabelPattern::either(LabelPattern::named('B'), LabelPattern::named('C'))),
        ];

        yield 'a name in back quotes is a label' => ['`match`', LabelPattern::named('match')];

        yield 'a name in double quotes is a label' => ['"Function"', LabelPattern::named('Function')];
    }

    /**
     * @throws GqlException
     */
    public function testParseReportsSomethingThatIsNotARequirement(): void
    {
        $this->expectException(GqlException::class);
        $this->expectExceptionMessage('expected a name at line 1, column 1 (found ",")');

        (new LabelParser(TokenReader::of(',')))->parse();
    }

    /**
     * @throws GqlException
     */
    public function testParseRefusesALabelNamedByAWordGqlReserves(): void
    {
        $this->expectException(GqlException::class);
        $this->expectExceptionMessage('GQL reserves "FUNCTION", so it is not a name here: write it in back quotes to use it as a name');

        (new LabelParser(TokenReader::of('Function')))->parse();
    }

    /**
     * @throws GqlException
     */
    public function testParseReportsAParenthesisThatIsNeverClosed(): void
    {
        $this->expectException(GqlException::class);
        $this->expectExceptionMessage('expected ")" at line 1, column 5 (found the end of the query)');

        (new LabelParser(TokenReader::of('(A|B')))->parse();
    }

    /**
     * @throws GqlException
     */
    public function testParseBothReadsARequirementThatStopsAtADisjunction(): void
    {
        $tokens = TokenReader::of('A&B|C');

        self::assertEquals(LabelPattern::both(LabelPattern::named('A'), LabelPattern::named('B')), (new LabelParser($tokens))->parseBoth());
        self::assertEquals(new Token(TokenKind::Symbol, '|', '|', 1, 4, 3), $tokens->current());
    }

    /**
     * @throws GqlException
     */
    public function testParseSingleReadsOneRequirementAndNoOperator(): void
    {
        $tokens = TokenReader::of('A&B');

        self::assertEquals(LabelPattern::named('A'), (new LabelParser($tokens))->parseSingle());
        self::assertEquals(new Token(TokenKind::Symbol, '&', '&', 1, 2, 1), $tokens->current());
    }

    /**
     * @throws GqlException
     */
    public function testParseSingleReadsAParenthesisedRequirementAsOne(): void
    {
        self::assertEquals(
            LabelPattern::either(LabelPattern::named('A'), LabelPattern::named('B')),
            (new LabelParser(TokenReader::of('(A|B)&C')))->parseSingle(),
        );
    }

    /**
     * @throws GqlException
     */
    public function testParseSingleRefusesANegationOfANegationWrittenWithoutParentheses(): void
    {
        $this->expectException(GqlException::class);
        $this->expectExceptionMessage('since GQL negates a <label primary> and a negation is not one');

        (new LabelParser(TokenReader::of('!!Interface')))->parseSingle();
    }

    /**
     * @throws GqlException
     */
    public function testParseSingleReadsANegationOfANegationWrittenInParentheses(): void
    {
        self::assertEquals(
            LabelPattern::neither(LabelPattern::neither(LabelPattern::named('Interface'))),
            (new LabelParser(TokenReader::of('!(!Interface)')))->parseSingle(),
        );
    }
}
