<?php

declare(strict_types=1);

namespace Tests\Unit\Gql\Matching;

use App\Gql\GqlException;
use App\Gql\Lexing\Lexer;
use App\Gql\Lexing\QuotedScanner;
use App\Gql\Lexing\SourceCursor;
use App\Gql\Lexing\Token;
use App\Gql\Lexing\TokenKind;
use App\Gql\Lexing\TokenList;
use App\Gql\Matching\PatternVariables;
use App\Gql\Parsing\ExpressionParser;
use App\Gql\Parsing\LabelParser;
use App\Gql\Parsing\OperandParser;
use App\Gql\Parsing\PatternParser;
use App\Gql\Parsing\TokenReader;
use App\Gql\StatusCode;
use App\Gql\Syntax\Expression\BinaryExpression;
use App\Gql\Syntax\Expression\BinaryOperator;
use App\Gql\Syntax\Expression\LiteralExpression;
use App\Gql\Syntax\Expression\PropertyExpression;
use App\Gql\Syntax\Expression\VariableExpression;
use App\Gql\Syntax\Pattern\EdgeDirection;
use App\Gql\Syntax\Pattern\EdgePattern;
use App\Gql\Syntax\Pattern\ElementFilter;
use App\Gql\Syntax\Pattern\GraphPattern;
use App\Gql\Syntax\Pattern\GroupPattern;
use App\Gql\Syntax\Pattern\LabelOperator;
use App\Gql\Syntax\Pattern\LabelPattern;
use App\Gql\Syntax\Pattern\NodePattern;
use App\Gql\Syntax\Pattern\PathMode;
use App\Gql\Syntax\Pattern\PathPattern;
use App\Gql\Syntax\Pattern\Quantifier;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Tests\Fixture\Gql\MatchedPattern;

/**
 * @internal
 */
#[CoversClass(PatternVariables::class)]
#[UsesClass(GqlException::class)]
#[UsesClass(StatusCode::class)]
#[UsesClass(Lexer::class)]
#[UsesClass(QuotedScanner::class)]
#[UsesClass(SourceCursor::class)]
#[UsesClass(Token::class)]
#[UsesClass(TokenKind::class)]
#[UsesClass(TokenList::class)]
#[UsesClass(ExpressionParser::class)]
#[UsesClass(LabelParser::class)]
#[UsesClass(OperandParser::class)]
#[UsesClass(PatternParser::class)]
#[UsesClass(TokenReader::class)]
#[UsesClass(BinaryExpression::class)]
#[UsesClass(BinaryOperator::class)]
#[UsesClass(LiteralExpression::class)]
#[UsesClass(PropertyExpression::class)]
#[UsesClass(VariableExpression::class)]
#[UsesClass(EdgeDirection::class)]
#[UsesClass(EdgePattern::class)]
#[UsesClass(ElementFilter::class)]
#[UsesClass(GraphPattern::class)]
#[UsesClass(GroupPattern::class)]
#[UsesClass(LabelOperator::class)]
#[UsesClass(LabelPattern::class)]
#[UsesClass(NodePattern::class)]
#[UsesClass(PathMode::class)]
#[UsesClass(PathPattern::class)]
#[UsesClass(Quantifier::class)]
#[Small]
final class PatternVariablesTest extends TestCase
{
    /**
     * @param list<string> $names
     *
     * @throws GqlException
     */
    #[DataProvider('providerPatternsAndTheNamesTheyBind')]
    public function testOfReturnsEveryNameAPatternWouldBind(string $written, array $names): void
    {
        self::assertSame($names, PatternVariables::of(MatchedPattern::pattern($written)));
    }

    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function providerPatternsAndTheNamesTheyBind(): iterable
    {
        yield 'the names written in a path, the path itself first' => [
            'p = (a)-[e]->(b)',
            ['p', 'a', 'e', 'b'],
        ];

        yield 'a pattern that binds nothing' => ['()-[]->()', []];

        yield 'a name written twice, bound once' => ['(a)-[]->(a)', ['a']];

        yield 'the names of every path matched together' => ['(a), (b)', ['a', 'b']];

        yield 'the names inside a parenthesised stretch of pattern' => ['((a)-[e]->(b)){1,3}', ['a', 'e', 'b']];
    }

    public function testInTermsReturnsTheNamesInsideAParenthesisedStretchOfPattern(): void
    {
        $group = new GroupPattern([new NodePattern('a')]);

        self::assertSame(['a'], PatternVariables::inTerms([$group]));
    }

    public function testInTermsReturnsNothingForAPatternWithNoPiecesAtAll(): void
    {
        self::assertSame([], PatternVariables::inTerms([]));
    }
}
