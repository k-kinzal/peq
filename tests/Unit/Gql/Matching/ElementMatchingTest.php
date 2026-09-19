<?php

declare(strict_types=1);

namespace Tests\Unit\Gql\Matching;

use App\Gql\Binding\BindingRow;
use App\Gql\Datum\BooleanDatum;
use App\Gql\Datum\DatumKind;
use App\Gql\Datum\DatumOrder;
use App\Gql\Datum\EdgeDatum;
use App\Gql\Datum\IntegerDatum;
use App\Gql\Datum\ListDatum;
use App\Gql\Datum\NodeDatum;
use App\Gql\Datum\NullDatum;
use App\Gql\Datum\PathDatum;
use App\Gql\Datum\StringDatum;
use App\Gql\Evaluation\BinaryOperation;
use App\Gql\Evaluation\Comparison;
use App\Gql\Evaluation\ExpressionEvaluation;
use App\Gql\Evaluation\Logic;
use App\Gql\GqlException;
use App\Gql\Lexing\Lexer;
use App\Gql\Lexing\QuotedScanner;
use App\Gql\Lexing\SourceCursor;
use App\Gql\Lexing\Token;
use App\Gql\Lexing\TokenKind;
use App\Gql\Lexing\TokenList;
use App\Gql\Matching\ElementMatching;
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
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Tests\Fixture\Gql\MatchedPattern;

/**
 * @internal
 */
#[CoversClass(ElementMatching::class)]
#[UsesClass(BooleanDatum::class)]
#[UsesClass(DatumKind::class)]
#[UsesClass(DatumOrder::class)]
#[UsesClass(EdgeDatum::class)]
#[UsesClass(IntegerDatum::class)]
#[UsesClass(ListDatum::class)]
#[UsesClass(NodeDatum::class)]
#[UsesClass(NullDatum::class)]
#[UsesClass(PathDatum::class)]
#[UsesClass(StringDatum::class)]
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
#[UsesClass(BindingRow::class)]
#[UsesClass(ExpressionEvaluation::class)]
#[UsesClass(BinaryOperation::class)]
#[UsesClass(Comparison::class)]
#[UsesClass(Logic::class)]
#[Small]
final class ElementMatchingTest extends TestCase
{
    /**
     * @throws GqlException
     */
    public function testSatisfiesAcceptsASymbolCarryingThePropertyAPatternAskedFor(): void
    {
        self::assertSame('p=Store::get', MatchedPattern::of("(p:Method {visibility: 'protected'})"));
    }

    /**
     * @throws GqlException
     */
    public function testSatisfiesRefusesASymbolCarryingADifferentValueForIt(): void
    {
        self::assertSame('', MatchedPattern::of("(p:Method {visibility: 'private'})"));
    }

    /**
     * @throws GqlException
     */
    public function testSatisfiesAcceptsASymbolThePredicateHoldsOf(): void
    {
        self::assertSame('p=Controller::store', MatchedPattern::of('(p:Method WHERE p.line > 20)'));
    }

    /**
     * @throws GqlException
     */
    public function testSatisfiesRefusesASymbolThePredicateCannotBeDecidedFor(): void
    {
        self::assertSame('', MatchedPattern::of('(p:Unresolved WHERE p.line > 0)'));
    }

    /**
     * @throws GqlException
     */
    public function testSatisfiesAcceptsAnyElementWhenThePatternRequiresNothingBeyondItsLabels(): void
    {
        self::assertSame('p=Missing', MatchedPattern::of('(p:Unresolved)'));
    }

    /**
     * @throws GqlException
     */
    public function testSatisfiesNarrowsARelationByThePredicateWrittenOnIt(): void
    {
        self::assertSame(
            'a=Invoice::total e=Invoice::total -[methodCall]-> Store::get b=Store::get',
            MatchedPattern::of('(a)-[e:methodCall WHERE e.line < 20]->(b)'),
        );
    }

    /**
     * @throws GqlException
     */
    public function testAgreesLetsANameWrittenTwiceMeanTheSameElement(): void
    {
        self::assertSame('', MatchedPattern::of('(p:Method)-[:methodCall]->(p)'));
    }

    /**
     * @throws GqlException
     */
    public function testAgreesLetsAPatternJoinToWhatAnEarlierClauseBound(): void
    {
        $bound = ['a' => MatchedPattern::graph()->node('App\Http\Controller::show')];
        assert($bound['a'] !== null);

        self::assertSame('a=Controller::show b=Invoice::total', MatchedPattern::of('(a)-[:methodCall]->(b)', $bound));
    }

    public function testAgreesAcceptsAnythingForANameNothingBound(): void
    {
        self::assertSame(true, ElementMatching::agrees('p', new NodeDatum('a'), BindingRow::unit()));
    }

    public function testAgreesRefusesAnElementThatIsNotWhatTheNameIsBoundTo(): void
    {
        $row = BindingRow::unit()->with('p', new NodeDatum('a'));

        self::assertFalse(ElementMatching::agrees('p', new NodeDatum('b'), $row));
    }
}
