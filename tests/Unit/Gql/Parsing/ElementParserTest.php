<?php

declare(strict_types=1);

namespace Tests\Unit\Gql\Parsing;

use App\Gql\Datum\DatumKind;
use App\Gql\Datum\DecimalDatum;
use App\Gql\Datum\IntegerDatum;
use App\Gql\Datum\StringDatum;
use App\Gql\GqlException;
use App\Gql\Lexing\Lexer;
use App\Gql\Lexing\QuotedScanner;
use App\Gql\Lexing\SourceCursor;
use App\Gql\Lexing\Token;
use App\Gql\Lexing\TokenKind;
use App\Gql\Lexing\TokenList;
use App\Gql\Parsing\ElementParser;
use App\Gql\Parsing\ExpressionParser;
use App\Gql\Parsing\LabelParser;
use App\Gql\Parsing\NameReader;
use App\Gql\Parsing\OperandParser;
use App\Gql\Parsing\TokenReader;
use App\Gql\ReservedWords;
use App\Gql\StatusCode;
use App\Gql\Syntax\Expression\BinaryExpression;
use App\Gql\Syntax\Expression\BinaryOperator;
use App\Gql\Syntax\Expression\LiteralExpression;
use App\Gql\Syntax\Expression\PropertyExpression;
use App\Gql\Syntax\Expression\VariableExpression;
use App\Gql\Syntax\Pattern\ElementFilter;
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
#[CoversClass(ElementParser::class)]
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
#[UsesClass(ExpressionParser::class)]
#[UsesClass(OperandParser::class)]
#[UsesClass(LabelParser::class)]
#[UsesClass(ReservedWords::class)]
#[UsesClass(DatumKind::class)]
#[UsesClass(DecimalDatum::class)]
#[UsesClass(IntegerDatum::class)]
#[UsesClass(StringDatum::class)]
#[UsesClass(BinaryExpression::class)]
#[UsesClass(BinaryOperator::class)]
#[UsesClass(LiteralExpression::class)]
#[UsesClass(PropertyExpression::class)]
#[UsesClass(VariableExpression::class)]
#[UsesClass(ElementFilter::class)]
#[UsesClass(LabelOperator::class)]
#[UsesClass(LabelPattern::class)]
#[Small]
final class ElementParserTest extends TestCase
{
    /**
     * @throws GqlException
     */
    public function testParseNameReadsTheNameAMatchedElementIsBoundTo(): void
    {
        $tokens = TokenReader::of('p:Method)');

        self::assertSame('p', (new ElementParser($tokens, new ExpressionParser($tokens)))->parseName());
        self::assertEquals(new Token(TokenKind::Symbol, ':', ':', 1, 2, 1), $tokens->current());
    }

    /**
     * @throws GqlException
     */
    #[DataProvider('providerElementsThatBindNothing')]
    public function testParseNameBindsNothingWhenNoNameIsWrittenFirst(string $written): void
    {
        $tokens = TokenReader::of($written);

        self::assertNull((new ElementParser($tokens, new ExpressionParser($tokens)))->parseName());
        self::assertSame(0, $tokens->position());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function providerElementsThatBindNothing(): iterable
    {
        yield 'a pattern that starts with a requirement on the labels' => [':Method)'];

        yield 'a pattern that starts with a predicate, since WHERE is reserved' => ['WHERE p.line > 10)'];

        yield 'a pattern that starts with the word that introduces labels' => ['IS Method)'];

        yield 'a name in back quotes, which is never a variable' => ['`p`)'];

        yield 'a pattern that requires nothing' => [')'];
    }

    /**
     * @throws GqlException
     */
    #[DataProvider('providerLabelRequirements')]
    public function testParseLabelsReadsTheRequirementWrittenAfterAColonOrIs(string $written, LabelPattern $expected): void
    {
        $tokens = TokenReader::of($written);

        self::assertEquals($expected, (new ElementParser($tokens, new ExpressionParser($tokens)))->parseLabels());
    }

    /**
     * @return iterable<string, array{string, LabelPattern}>
     */
    public static function providerLabelRequirements(): iterable
    {
        yield 'after a colon' => [':Method)', LabelPattern::named('Method')];

        yield 'after IS' => ['IS Method)', LabelPattern::named('Method')];

        yield 'after IS in lower case' => ['is Method)', LabelPattern::named('Method')];

        yield 'a label expression' => [':Method|`Function`)', LabelPattern::either(LabelPattern::named('Method'), LabelPattern::named('Function'))];
    }

    /**
     * @throws GqlException
     */
    public function testParseLabelsRequiresNothingWhenNeitherAColonNorIsIsWritten(): void
    {
        $tokens = TokenReader::of(')');

        self::assertNull((new ElementParser($tokens, new ExpressionParser($tokens)))->parseLabels());
    }

    /**
     * @throws GqlException
     */
    #[DataProvider('providerRequirementsBeyondTheLabels')]
    public function testParseFilterReadsWhatAPatternRequiresBeyondTheLabels(string $written, ElementFilter $expected): void
    {
        $tokens = TokenReader::of($written);

        self::assertEquals($expected, (new ElementParser($tokens, new ExpressionParser($tokens)))->parseFilter());
    }

    /**
     * @return iterable<string, array{string, ElementFilter}>
     */
    public static function providerRequirementsBeyondTheLabels(): iterable
    {
        yield 'properties' => ["{name: 'Invoice'})", new ElementFilter(['name' => new LiteralExpression(new StringDatum('Invoice'))])];

        yield 'a predicate' => [
            'WHERE p.line > 10)',
            new ElementFilter([], new BinaryExpression(
                BinaryOperator::Greater,
                new PropertyExpression(new VariableExpression('p'), 'line'),
                new LiteralExpression(new IntegerDatum(10)),
            )),
        ];

        yield 'nothing' => [')', new ElementFilter()];
    }

    /**
     * @throws GqlException
     */
    public function testParseFilterReadsPropertiesAndLeavesAPredicateWrittenAfterThem(): void
    {
        $tokens = TokenReader::of("{name: 'x'} WHERE p.line > 1)");

        self::assertEquals(
            new ElementFilter(['name' => new LiteralExpression(new StringDatum('x'))]),
            (new ElementParser($tokens, new ExpressionParser($tokens)))->parseFilter(),
        );
        self::assertEquals(new Token(TokenKind::Name, 'WHERE', 'WHERE', 1, 13, 12), $tokens->current());
    }

    /**
     * @throws GqlException
     */
    public function testParsePropertiesReadsEveryPropertyWrittenByName(): void
    {
        $tokens = TokenReader::of("{kind: 'method', visibility: 'public'}");

        self::assertEquals(
            [
                'kind' => new LiteralExpression(new StringDatum('method')),
                'visibility' => new LiteralExpression(new StringDatum('public')),
            ],
            (new ElementParser($tokens, new ExpressionParser($tokens)))->parseProperties(),
        );
    }

    /**
     * @throws GqlException
     */
    public function testParsePropertiesReadsAPropertyGqlReservesTheNameOfInBackQuotes(): void
    {
        $tokens = TokenReader::of('{`value`: 1}');

        self::assertEquals(
            ['value' => new LiteralExpression(new IntegerDatum(1))],
            (new ElementParser($tokens, new ExpressionParser($tokens)))->parseProperties(),
        );
    }

    /**
     * @throws GqlException
     */
    public function testParsePropertiesReadsBracesThatWriteNone(): void
    {
        $tokens = TokenReader::of('{}');

        self::assertSame([], (new ElementParser($tokens, new ExpressionParser($tokens)))->parseProperties());
    }

    /**
     * @throws GqlException
     */
    public function testParsePropertiesRefusesAPropertyGqlReservesTheNameOf(): void
    {
        $tokens = TokenReader::of('{value: 1}');

        $this->expectException(GqlException::class);
        $this->expectExceptionMessage('GQL reserves "VALUE", so it is not a name here: write it in back quotes to use it as a name');

        (new ElementParser($tokens, new ExpressionParser($tokens)))->parseProperties();
    }

    /**
     * @throws GqlException
     */
    public function testParsePropertiesReportsBracesThatAreNeverClosed(): void
    {
        $tokens = TokenReader::of('{a: 1');

        $this->expectException(GqlException::class);
        $this->expectExceptionMessage('expected "}" at line 1, column 6 (found the end of the query)');

        (new ElementParser($tokens, new ExpressionParser($tokens)))->parseProperties();
    }

    /**
     * @throws GqlException
     */
    public function testParseNameRefusesAWordGqlReservesWrittenWhereANameStands(): void
    {
        $tokens = TokenReader::of('function)');

        $this->expectException(GqlException::class);
        $this->expectExceptionMessage('GQL reserves "FUNCTION", so it is not a name here: a query binds a name to a word GQL leaves free');

        (new ElementParser($tokens, new ExpressionParser($tokens)))->parseName();
    }

    /**
     * @throws GqlException
     */
    public function testParseNameLeavesAKeywordThatBeginsWhatFollowsAlone(): void
    {
        $tokens = TokenReader::of('WHERE p.line > 1)');

        self::assertNull((new ElementParser($tokens, new ExpressionParser($tokens)))->parseName());
    }
}
