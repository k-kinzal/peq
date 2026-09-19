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
use App\Gql\Matching\LabelMatching;
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

/**
 * @internal
 */
#[CoversClass(LabelMatching::class)]
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
final class LabelMatchingTest extends TestCase
{
    /**
     * @param list<string> $labels
     *
     * @throws GqlException
     */
    #[DataProvider('providerRequirementsAndWhetherLabelsSatisfyThem')]
    public function testSatisfiesReportsWhetherLabelsSatisfyARequirement(string $written, array $labels, bool $satisfied): void
    {
        $required = (new LabelParser(TokenReader::of($written)))->parse();

        self::assertSame($satisfied, LabelMatching::satisfies($required, $labels));
    }

    /**
     * @return iterable<string, array{string, list<string>, bool}>
     */
    public static function providerRequirementsAndWhetherLabelsSatisfyThem(): iterable
    {
        yield 'a label the element carries' => ['Method', ['Method', 'Callable'], true];

        yield 'a label it does not' => ['Class', ['Method', 'Callable'], false];

        yield 'a family it belongs to' => ['Callable', ['Method', 'Member', 'Callable'], true];

        yield 'either of two, one of which it carries' => ['Class|Method', ['Method'], true];

        yield 'either of two, neither of which it carries' => ['Class|Interface', ['Method'], false];

        yield 'both of two, both of which it carries' => ['Member&Callable', ['Method', 'Member', 'Callable'], true];

        yield 'both of two, one of which it does not' => ['Member&Callable', ['Function', 'Callable'], false];

        yield 'a refusal of a label it does not carry' => ['!Interface', ['Class', 'ClassLike'], true];

        yield 'a refusal of one it does' => ['!Interface', ['Interface', 'ClassLike'], false];

        yield 'anything at all, of an element that carries something' => ['%', ['Class'], true];

        yield 'anything at all, of an element that carries nothing' => ['%', [], false];

        yield 'a family without one of its members' => ['ClassLike&!Interface', ['Class', 'ClassLike'], true];
    }

    public function testSatisfiesIsSatisfiedByAnythingWhenNothingIsRequired(): void
    {
        self::assertTrue(LabelMatching::satisfies(null, []));
    }
}
