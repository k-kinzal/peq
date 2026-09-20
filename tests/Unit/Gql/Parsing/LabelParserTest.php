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
use App\Gql\Parsing\TokenReader;
use App\Gql\StatusCode;
use App\Gql\Syntax\Pattern\LabelOperator;
use App\Gql\Syntax\Pattern\LabelPattern;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Tests\Fixture\Gql\QuerySpelling;

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
#[UsesClass(LabelOperator::class)]
#[UsesClass(LabelPattern::class)]
#[Small]
final class LabelParserTest extends TestCase
{
    /**
     * @throws GqlException
     */
    #[DataProvider('providerLabelExpressions')]
    public function testParseReadsWhatAPatternRequiresOfTheLabels(string $written, string $shape): void
    {
        self::assertSame($shape, QuerySpelling::labels((new LabelParser(TokenReader::of($written)))->parse()));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function providerLabelExpressions(): iterable
    {
        yield 'one label' => ['Method', 'Method'];

        yield 'either of two' => ['Method|`Function`', '(Method|Function)'];

        yield 'both of two' => ['Member&Callable', '(Member&Callable)'];

        yield 'a refusal' => ['!Interface', '!Interface'];

        yield 'anything at all' => ['%', '%'];

        yield 'a refusal binds tighter than a conjunction' => ['!A&B', '(!A&B)'];

        yield 'a conjunction binds tighter than a disjunction' => ['!A&B|C', '((!A&B)|C)'];

        yield 'parentheses override the binding' => ['A&(B|C)', '(A&(B|C))'];

        yield 'a name in backticks is a label' => ['`match`', 'match'];
    }

    /**
     * @throws GqlException
     */
    public function testParseBothReadsARequirementThatStopsAtADisjunction(): void
    {
        self::assertSame('(A&B)', QuerySpelling::labels((new LabelParser(TokenReader::of('A&B|C')))->parseBoth()));
    }

    /**
     * @throws GqlException
     */
    public function testParseSingleReadsOneRequirementAndNoOperator(): void
    {
        self::assertSame('A', QuerySpelling::labels((new LabelParser(TokenReader::of('A&B')))->parseSingle()));
    }

    /**
     * @throws GqlException
     */
    public function testParseReportsSomethingThatIsNotARequirement(): void
    {
        $this->expectException(GqlException::class);
        $this->expectExceptionMessage('expected a name');

        (new LabelParser(TokenReader::of(',')))->parse();
    }
}
