<?php

declare(strict_types=1);

namespace Tests\Unit\Gql\Matching;

use App\Analyzer\Graph\Declaration\AttributeUsage;
use App\Analyzer\Graph\Declaration\Modifiers;
use App\Analyzer\Graph\Declaration\Parameter;
use App\Analyzer\Graph\Declaration\Signature;
use App\Analyzer\Graph\Declaration\SymbolDeclaration;
use App\Analyzer\Graph\Declaration\Visibility;
use App\Analyzer\Graph\Edge\Declaration\ExtendsEdge;
use App\Analyzer\Graph\Edge\Declaration\MethodEdge;
use App\Analyzer\Graph\Edge\Usage\MethodCallEdge;
use App\Analyzer\Graph\FileMeta;
use App\Analyzer\Graph\Graph;
use App\Analyzer\Graph\Node\ClassNode;
use App\Analyzer\Graph\Node\MethodNode;
use App\Analyzer\Graph\Node\UnknownNode;
use App\Analyzer\Graph\NodeId\ClassNodeId;
use App\Analyzer\Graph\NodeId\MethodNodeId;
use App\Analyzer\Graph\NodeId\UnknownNodeId;
use App\Analyzer\Graph\NodePrecedence;
use App\Analyzer\Graph\QualifiedName;
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
use App\Gql\Element\EdgeLabels;
use App\Gql\Element\EdgeProperties;
use App\Gql\Element\ElementGraph;
use App\Gql\Element\GraphProjection;
use App\Gql\Element\NodeLabels;
use App\Gql\Element\NodeProperties;
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
use App\Gql\Matching\EdgeMatching;
use App\Gql\Matching\EdgeTraversal;
use App\Gql\Matching\ElementMatching;
use App\Gql\Matching\LabelMatching;
use App\Gql\Matching\MatchState;
use App\Gql\Matching\PathMatching;
use App\Gql\Matching\PathModeRule;
use App\Gql\Matching\PatternMatching;
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
#[CoversClass(EdgeMatching::class)]
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
#[UsesClass(AttributeUsage::class)]
#[UsesClass(Modifiers::class)]
#[UsesClass(Parameter::class)]
#[UsesClass(Signature::class)]
#[UsesClass(SymbolDeclaration::class)]
#[UsesClass(Visibility::class)]
#[UsesClass(ExtendsEdge::class)]
#[UsesClass(MethodEdge::class)]
#[UsesClass(MethodCallEdge::class)]
#[UsesClass(FileMeta::class)]
#[UsesClass(Graph::class)]
#[UsesClass(ClassNode::class)]
#[UsesClass(MethodNode::class)]
#[UsesClass(UnknownNode::class)]
#[UsesClass(ClassNodeId::class)]
#[UsesClass(MethodNodeId::class)]
#[UsesClass(UnknownNodeId::class)]
#[UsesClass(NodePrecedence::class)]
#[UsesClass(QualifiedName::class)]
#[UsesClass(EdgeLabels::class)]
#[UsesClass(EdgeProperties::class)]
#[UsesClass(ElementGraph::class)]
#[UsesClass(GraphProjection::class)]
#[UsesClass(NodeLabels::class)]
#[UsesClass(NodeProperties::class)]
#[UsesClass(EdgeTraversal::class)]
#[UsesClass(ElementMatching::class)]
#[UsesClass(LabelMatching::class)]
#[UsesClass(MatchState::class)]
#[UsesClass(PathMatching::class)]
#[UsesClass(PathModeRule::class)]
#[UsesClass(PatternMatching::class)]
#[Small]
final class EdgeMatchingTest extends TestCase
{
    /**
     * @throws GqlException
     */
    public function testMatchesCrossesOneRelationWhenNoRepetitionIsWritten(): void
    {
        self::assertSame(
            'a=Controller::show b=Invoice::total; a=Controller::store b=Invoice::total; a=Invoice::total b=Store::get',
            MatchedPattern::of('(a:Method)-[:methodCall]->(b)'),
        );
    }

    /**
     * @throws GqlException
     */
    public function testMatchesBindsTheNameToOneRelationWhenNoRepetitionIsWritten(): void
    {
        self::assertSame(
            'a=Invoice::total e=Invoice::total -[methodCall]-> Store::get b=Store::get',
            MatchedPattern::of('(a:Method)-[e:methodCall]->(b:Method WHERE b.visibility = \'protected\')'),
        );
    }

    /**
     * @throws GqlException
     */
    public function testMatchesBindsTheNameToTheWholeChainWhenARepetitionIsWritten(): void
    {
        self::assertSame(
            'a=Controller::show e=[Controller::show -[methodCall]-> Invoice::total, Invoice::total -[methodCall]-> Store::get] b=Store::get',
            MatchedPattern::of("(a:Method WHERE a.name = 'show')-[e:methodCall]->{2}(b:Method)"),
        );
    }

    /**
     * @throws GqlException
     */
    public function testMatchesReachesEverythingWithinTheRepetitionItIsAllowed(): void
    {
        self::assertSame(
            'a=Controller::show b=Invoice::total; a=Controller::show b=Store::get',
            MatchedPattern::of("(a:Method WHERE a.name = 'show')-[:methodCall]->{1,2}(b:Method)"),
        );
    }

    /**
     * @throws GqlException
     */
    public function testMatchesStandsStillWhenARepetitionIsAllowedToHappenNoTimes(): void
    {
        self::assertSame(
            'a=Store::get e=[] b=Store::get',
            MatchedPattern::of("(a:Method WHERE a.name = 'get')-[e:methodCall]->{0,1}(b:Method)"),
        );
    }

    /**
     * @throws GqlException
     */
    public function testMatchesGoesNoFurtherThanTheHopLimitWhenNoUpperBoundIsWritten(): void
    {
        self::assertSame(
            'a=Controller::show b=Invoice::total',
            MatchedPattern::of("(a:Method WHERE a.name = 'show')-[:methodCall]->{1,}(b:Method)", [], 1),
        );
    }

    /**
     * @throws GqlException
     */
    public function testMatchesNarrowsAChainRelationByRelation(): void
    {
        self::assertSame(
            'a=Invoice::total e=[Invoice::total -[methodCall]-> Store::get] b=Store::get',
            MatchedPattern::of('(a:Method)-[e:methodCall WHERE e.line < 20]->{1,3}(b:Method)'),
        );
    }

    /**
     * @throws GqlException
     */
    public function testMatchesCrossesNothingFromAnAttemptStandingNowhere(): void
    {
        $matching = new EdgeMatching(new ElementGraph([], [], []), new ExpressionEvaluation(), 10);
        $pattern = new EdgePattern(EdgeDirection::Along);

        self::assertSame([], $matching->matches($pattern, MatchState::before(BindingRow::unit()), PathMode::Trail));
    }

    /**
     * @throws GqlException
     */
    public function testCrossOnceCrossesNothingFromAnAttemptStandingNowhere(): void
    {
        $matching = new EdgeMatching(new ElementGraph([], [], []), new ExpressionEvaluation(), 10);
        $pattern = new EdgePattern(EdgeDirection::Along);

        self::assertSame([], $matching->crossOnce($pattern, MatchState::before(BindingRow::unit()), PathMode::Trail, false));
    }

    /**
     * @throws GqlException
     */
    public function testArrivalCrossesNothingLeadingWhereTheGraphKnowsOfNoSymbol(): void
    {
        $edge = new EdgeDatum('e', [], [], 'a', 'b');
        $matching = new EdgeMatching(new ElementGraph([], [], []), new ExpressionEvaluation(), 10);
        $state = MatchState::before(BindingRow::unit())->startingAt(new NodeDatum('a'));
        $pattern = new EdgePattern(EdgeDirection::Along);

        self::assertNull($matching->arrival($pattern, $state, PathMode::Trail, new EdgeTraversal($edge, 'b'), false));
    }

    public function testExtendedGrowsAChainByOneRelationAtATime(): void
    {
        $chain = new ListDatum([new EdgeDatum('e', [], [], 'a', 'b')]);

        self::assertCount(2, EdgeMatching::extended($chain, new EdgeDatum('f', [], [], 'b', 'c'))->items);
    }

    public function testExtendedStartsAChainFromSomethingThatIsNotOne(): void
    {
        self::assertCount(1, EdgeMatching::extended(new NullDatum(), new EdgeDatum('e', [], [], 'a', 'b'))->items);
    }
}
