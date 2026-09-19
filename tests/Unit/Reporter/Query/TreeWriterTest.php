<?php

declare(strict_types=1);

namespace Tests\Unit\Reporter\Query;

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
use App\Gql\Binding\BindingTable;
use App\Gql\Datum\DatumJson;
use App\Gql\Datum\DatumKind;
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
use App\Gql\Evaluation\AggregateDetection;
use App\Gql\Evaluation\BinaryOperation;
use App\Gql\Evaluation\Comparison;
use App\Gql\Evaluation\ExpressionEvaluation;
use App\Gql\Evaluation\Logic;
use App\Gql\Execution\BlockExecution;
use App\Gql\Execution\MatchExecution;
use App\Gql\Execution\QueryExecution;
use App\Gql\Execution\ReturnExecution;
use App\Gql\Execution\RowExecution;
use App\Gql\Execution\SetOperation;
use App\Gql\GqlException;
use App\Gql\Invocation\AggregateCatalog;
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
use App\Gql\Matching\PatternVariables;
use App\Gql\Parsing\ExpressionParser;
use App\Gql\Parsing\LabelParser;
use App\Gql\Parsing\OperandParser;
use App\Gql\Parsing\Parser;
use App\Gql\Parsing\PatternParser;
use App\Gql\Parsing\ResultParser;
use App\Gql\Parsing\TokenReader;
use App\Gql\Result\ResultColumn;
use App\Gql\Result\ResultRow;
use App\Gql\Result\ResultTable;
use App\Gql\StatusCode;
use App\Gql\Syntax\Clause\MatchClause;
use App\Gql\Syntax\Clause\Projection;
use App\Gql\Syntax\Clause\ReturnClause;
use App\Gql\Syntax\Expression\BinaryExpression;
use App\Gql\Syntax\Expression\BinaryOperator;
use App\Gql\Syntax\Expression\LiteralExpression;
use App\Gql\Syntax\Expression\PropertyExpression;
use App\Gql\Syntax\Expression\VariableExpression;
use App\Gql\Syntax\Pattern\EdgeDirection;
use App\Gql\Syntax\Pattern\EdgePattern;
use App\Gql\Syntax\Pattern\ElementFilter;
use App\Gql\Syntax\Pattern\GraphPattern;
use App\Gql\Syntax\Pattern\LabelOperator;
use App\Gql\Syntax\Pattern\LabelPattern;
use App\Gql\Syntax\Pattern\NodePattern;
use App\Gql\Syntax\Pattern\PathMode;
use App\Gql\Syntax\Pattern\PathPattern;
use App\Gql\Syntax\Pattern\Quantifier;
use App\Gql\Syntax\Query;
use App\Gql\Syntax\QueryBlock;
use App\Reporter\Diagram\Diagram;
use App\Reporter\Diagram\DiagramEdge;
use App\Reporter\Diagram\DiagramNode;
use App\Reporter\Query\ResultElements;
use App\Reporter\Query\TreeStep;
use App\Reporter\Query\TreeWriter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Output\BufferedOutput;
use Tests\Fixture\Gql\AnsweredQuery;

/**
 * @internal
 */
#[CoversClass(TreeWriter::class)]
#[UsesClass(BindingRow::class)]
#[UsesClass(BindingTable::class)]
#[UsesClass(DatumJson::class)]
#[UsesClass(DatumKind::class)]
#[UsesClass(EdgeDatum::class)]
#[UsesClass(IntegerDatum::class)]
#[UsesClass(ListDatum::class)]
#[UsesClass(NodeDatum::class)]
#[UsesClass(NullDatum::class)]
#[UsesClass(PathDatum::class)]
#[UsesClass(StringDatum::class)]
#[UsesClass(ResultColumn::class)]
#[UsesClass(ResultRow::class)]
#[UsesClass(ResultTable::class)]
#[UsesClass(StatusCode::class)]
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
#[UsesClass(ExpressionEvaluation::class)]
#[UsesClass(BlockExecution::class)]
#[UsesClass(MatchExecution::class)]
#[UsesClass(QueryExecution::class)]
#[UsesClass(ReturnExecution::class)]
#[UsesClass(RowExecution::class)]
#[UsesClass(SetOperation::class)]
#[UsesClass(Lexer::class)]
#[UsesClass(QuotedScanner::class)]
#[UsesClass(SourceCursor::class)]
#[UsesClass(Token::class)]
#[UsesClass(TokenKind::class)]
#[UsesClass(TokenList::class)]
#[UsesClass(EdgeMatching::class)]
#[UsesClass(EdgeTraversal::class)]
#[UsesClass(ElementMatching::class)]
#[UsesClass(LabelMatching::class)]
#[UsesClass(MatchState::class)]
#[UsesClass(PathMatching::class)]
#[UsesClass(PathModeRule::class)]
#[UsesClass(PatternMatching::class)]
#[UsesClass(PatternVariables::class)]
#[UsesClass(ExpressionParser::class)]
#[UsesClass(LabelParser::class)]
#[UsesClass(OperandParser::class)]
#[UsesClass(Parser::class)]
#[UsesClass(PatternParser::class)]
#[UsesClass(ResultParser::class)]
#[UsesClass(TokenReader::class)]
#[UsesClass(AggregateDetection::class)]
#[UsesClass(BinaryOperation::class)]
#[UsesClass(Comparison::class)]
#[UsesClass(Logic::class)]
#[UsesClass(MatchClause::class)]
#[UsesClass(Projection::class)]
#[UsesClass(ReturnClause::class)]
#[UsesClass(BinaryExpression::class)]
#[UsesClass(BinaryOperator::class)]
#[UsesClass(LiteralExpression::class)]
#[UsesClass(PropertyExpression::class)]
#[UsesClass(VariableExpression::class)]
#[UsesClass(EdgeDirection::class)]
#[UsesClass(EdgePattern::class)]
#[UsesClass(ElementFilter::class)]
#[UsesClass(GraphPattern::class)]
#[UsesClass(LabelOperator::class)]
#[UsesClass(LabelPattern::class)]
#[UsesClass(NodePattern::class)]
#[UsesClass(PathMode::class)]
#[UsesClass(PathPattern::class)]
#[UsesClass(Query::class)]
#[UsesClass(QueryBlock::class)]
#[UsesClass(GqlException::class)]
#[UsesClass(Diagram::class)]
#[UsesClass(DiagramEdge::class)]
#[UsesClass(DiagramNode::class)]
#[UsesClass(ResultElements::class)]
#[UsesClass(TreeStep::class)]
#[UsesClass(Quantifier::class)]
#[UsesClass(AggregateCatalog::class)]
#[Small]
final class TreeWriterTest extends TestCase
{
    /**
     * @throws GqlException
     */
    public function testReportDrawsThePathsAQueryBoundAsOneTree(): void
    {
        $output = new BufferedOutput();
        (new TreeWriter())->report(AnsweredQuery::table('MATCH p = (a:Method)-[:methodCall]->{1,2}(b:Method) RETURN p'), $output);

        self::assertSame(
            <<<'TREE'
                App\Domain\Invoice::total
                └── methodCall ──> App\Cache\Store::get
                App\Http\Controller::show
                └── methodCall ──> App\Domain\Invoice::total
                    └── methodCall ──> App\Cache\Store::get
                App\Http\Controller::store
                └── methodCall ──> App\Domain\Invoice::total
                    └── methodCall ──> App\Cache\Store::get

                TREE,
            $output->fetch(),
        );
    }

    /**
     * @throws GqlException
     */
    public function testReportWritesNothingForAQueryThatBoundNoPath(): void
    {
        $output = new BufferedOutput();
        (new TreeWriter())->report(AnsweredQuery::table('MATCH (p:Class) RETURN p.name AS name'), $output);

        self::assertSame('', $output->fetch());
    }

    public function testPathsHoldsNothingForAnAnswerThatBoundNoPath(): void
    {
        self::assertSame([], TreeWriter::paths(ResultTable::nothing()));
    }

    /**
     * @throws GqlException
     */
    public function testPathsShowsAPathBoundTwiceOnce(): void
    {
        $answered = AnsweredQuery::table('MATCH p = (a:Method)-[:methodCall]->(b) RETURN p, p AS again');

        self::assertCount(3, TreeWriter::paths($answered));
    }

    public function testStepsReadsAPathThatCrossesNothingAsOneStep(): void
    {
        self::assertCount(1, TreeWriter::steps(PathDatum::at(new NodeDatum('a'))));
    }

    public function testStepsReadsAPathThatCrossesOneRelationAsTwo(): void
    {
        $path = PathDatum::at(new NodeDatum('a'))
            ->continuedBy(new EdgeDatum('e', ['calls'], [], 'a', 'b'), new NodeDatum('b'))
        ;

        self::assertCount(2, TreeWriter::steps($path));
    }

    public function testSignatureTellsNothingApartWhenThereAreNoStepsToTellApart(): void
    {
        self::assertSame('', TreeWriter::signature([]));
    }

    public function testSharedLengthSharesNothingWithNothing(): void
    {
        self::assertSame(0, TreeWriter::sharedLength([], [new TreeStep('', 'a')]));
    }

    public function testSharedLengthSharesTheFirstStepOfTwoPathsFromTheSameSymbol(): void
    {
        $left = [new TreeStep('', 'a'), new TreeStep('calls', 'b')];
        $right = [new TreeStep('', 'a'), new TreeStep('calls', 'c')];

        self::assertSame(1, TreeWriter::sharedLength($left, $right));
    }

    public function testHasSiblingFindsNothingBesideTheOnlyPathThereIs(): void
    {
        self::assertFalse(TreeWriter::hasSibling([[new TreeStep('', 'a')]], 0, 0));
    }

    public function testHasSiblingFindsAPathThatBranchesOffAtTheSameDepth(): void
    {
        $first = [new TreeStep('', 'a'), new TreeStep('calls', 'b')];
        $second = [new TreeStep('', 'a'), new TreeStep('calls', 'c')];

        self::assertTrue(TreeWriter::hasSibling([$first, $second], 0, 1));
    }

    public function testHasSiblingFindsNothingBesideAPathThatSharesLessThanTheDepth(): void
    {
        $first = [new TreeStep('', 'a'), new TreeStep('calls', 'b')];
        $second = [new TreeStep('', 'z')];

        self::assertFalse(TreeWriter::hasSibling([$first, $second], 0, 1));
    }

    public function testLineWritesTheStartOfAPathWithNoBranchDrawing(): void
    {
        self::assertSame('a', TreeWriter::line([new TreeStep('', 'a')], 0, []));
    }

    public function testLineWritesAStepAsTheRelationThatReachedIt(): void
    {
        $path = [new TreeStep('', 'a'), new TreeStep('calls', 'b')];

        self::assertSame('└── calls ──> b', TreeWriter::line($path, 1, [1 => false]));
    }

    public function testLineKeepsTheBranchOpenWhenAnotherStepStandsBesideIt(): void
    {
        $path = [new TreeStep('', 'a'), new TreeStep('calls', 'b')];

        self::assertSame('├── calls ──> b', TreeWriter::line($path, 1, [1 => true]));
    }
}
