<?php

declare(strict_types=1);

namespace Tests\Unit\Reporter\Query;

use App\Analyzer\Graph\AuthoredEdge;
use App\Analyzer\Graph\Declaration\AttributeUsage;
use App\Analyzer\Graph\Declaration\Modifiers;
use App\Analyzer\Graph\Declaration\Parameter;
use App\Analyzer\Graph\Declaration\Signature;
use App\Analyzer\Graph\Declaration\SymbolDeclaration;
use App\Analyzer\Graph\Edge\Declaration\ExtendsEdge;
use App\Analyzer\Graph\Edge\Declaration\MethodEdge;
use App\Analyzer\Graph\Edge\Inverse\DeclaredInEdge;
use App\Analyzer\Graph\Edge\Inverse\UsedByEdge;
use App\Analyzer\Graph\Edge\Usage\MethodCallEdge;
use App\Analyzer\Graph\FileMeta;
use App\Analyzer\Graph\Graph;
use App\Analyzer\Graph\Node\ClassNode;
use App\Analyzer\Graph\Node\MethodNode;
use App\Analyzer\Graph\Node\UnknownNode;
use App\Analyzer\Graph\NodeId\ClassNodeId;
use App\Analyzer\Graph\NodeId\MethodNodeId;
use App\Analyzer\Graph\NodeId\UnknownNodeId;
use App\Analyzer\Graph\QualifiedName;
use App\Gql\Binding\BindingRow;
use App\Gql\Binding\BindingTable;
use App\Gql\Datum\BooleanDatum;
use App\Gql\Datum\DatumKind;
use App\Gql\Datum\DatumOrder;
use App\Gql\Datum\DecimalDatum;
use App\Gql\Datum\EdgeDatum;
use App\Gql\Datum\IntegerDatum;
use App\Gql\Datum\ListDatum;
use App\Gql\Datum\NodeDatum;
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
use App\Gql\Execution\QuantifierLimit;
use App\Gql\Execution\QueryExecution;
use App\Gql\Execution\ReturnExecution;
use App\Gql\Execution\RowExecution;
use App\Gql\GqlException;
use App\Gql\Lexing\Lexer;
use App\Gql\Lexing\QuotedScanner;
use App\Gql\Lexing\SourceCursor;
use App\Gql\Lexing\Token;
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
use App\Gql\Parsing\ElementParser;
use App\Gql\Parsing\ExpressionParser;
use App\Gql\Parsing\LabelParser;
use App\Gql\Parsing\NameReader;
use App\Gql\Parsing\OperandParser;
use App\Gql\Parsing\Parser;
use App\Gql\Parsing\PatternParser;
use App\Gql\Parsing\QuantifierParser;
use App\Gql\Parsing\ResultParser;
use App\Gql\Parsing\TokenReader;
use App\Gql\ReservedWords;
use App\Gql\Result\ResultColumn;
use App\Gql\Result\ResultRow;
use App\Gql\Result\ResultTable;
use App\Gql\Syntax\Clause\MatchClause;
use App\Gql\Syntax\Clause\Projection;
use App\Gql\Syntax\Clause\ReturnClause;
use App\Gql\Syntax\Expression\BinaryExpression;
use App\Gql\Syntax\Expression\LiteralExpression;
use App\Gql\Syntax\Expression\PropertyExpression;
use App\Gql\Syntax\Expression\VariableExpression;
use App\Gql\Syntax\Pattern\EdgePattern;
use App\Gql\Syntax\Pattern\ElementFilter;
use App\Gql\Syntax\Pattern\GraphPattern;
use App\Gql\Syntax\Pattern\LabelPattern;
use App\Gql\Syntax\Pattern\NodePattern;
use App\Gql\Syntax\Pattern\PathPattern;
use App\Gql\Syntax\Pattern\Quantifier;
use App\Gql\Syntax\Query;
use App\Gql\Syntax\QueryBlock;
use App\Reporter\Query\QueryOutputException;
use App\Reporter\Query\ResultElements;
use App\Reporter\Query\TreeStep;
use App\Reporter\Query\TreeWriter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Output\BufferedOutput;
use Tests\Fixture\Gql\SampleGraph;

/**
 * @internal
 */
#[UsesClass(\App\Analyzer\Graph\EdgeIdentity::class)]
#[CoversClass(TreeWriter::class)]
#[UsesClass(TreeStep::class)]
#[UsesClass(ResultElements::class)]
#[UsesClass(ResultColumn::class)]
#[UsesClass(ResultRow::class)]
#[UsesClass(ResultTable::class)]
#[UsesClass(EdgeDatum::class)]
#[UsesClass(ListDatum::class)]
#[UsesClass(NodeDatum::class)]
#[UsesClass(PathDatum::class)]
#[UsesClass(GraphProjection::class)]
#[UsesClass(QueryExecution::class)]
#[UsesClass(GqlException::class)]
#[UsesClass(AuthoredEdge::class)]
#[UsesClass(AttributeUsage::class)]
#[UsesClass(Modifiers::class)]
#[UsesClass(Parameter::class)]
#[UsesClass(Signature::class)]
#[UsesClass(SymbolDeclaration::class)]
#[UsesClass(ExtendsEdge::class)]
#[UsesClass(MethodEdge::class)]
#[UsesClass(DeclaredInEdge::class)]
#[UsesClass(UsedByEdge::class)]
#[UsesClass(MethodCallEdge::class)]
#[UsesClass(FileMeta::class)]
#[UsesClass(Graph::class)]
#[UsesClass(ClassNodeId::class)]
#[UsesClass(MethodNodeId::class)]
#[UsesClass(UnknownNodeId::class)]
#[UsesClass(ClassNode::class)]
#[UsesClass(MethodNode::class)]
#[UsesClass(UnknownNode::class)]
#[UsesClass(QualifiedName::class)]
#[UsesClass(BindingRow::class)]
#[UsesClass(BindingTable::class)]
#[UsesClass(BooleanDatum::class)]
#[UsesClass(DatumKind::class)]
#[UsesClass(DatumOrder::class)]
#[UsesClass(DecimalDatum::class)]
#[UsesClass(IntegerDatum::class)]
#[UsesClass(StringDatum::class)]
#[UsesClass(EdgeLabels::class)]
#[UsesClass(EdgeProperties::class)]
#[UsesClass(ElementGraph::class)]
#[UsesClass(NodeLabels::class)]
#[UsesClass(NodeProperties::class)]
#[UsesClass(AggregateDetection::class)]
#[UsesClass(BinaryOperation::class)]
#[UsesClass(Comparison::class)]
#[UsesClass(ExpressionEvaluation::class)]
#[UsesClass(Logic::class)]
#[UsesClass(BlockExecution::class)]
#[UsesClass(MatchExecution::class)]
#[UsesClass(QuantifierLimit::class)]
#[UsesClass(ReturnExecution::class)]
#[UsesClass(RowExecution::class)]
#[UsesClass(Lexer::class)]
#[UsesClass(QuotedScanner::class)]
#[UsesClass(SourceCursor::class)]
#[UsesClass(Token::class)]
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
#[UsesClass(ElementParser::class)]
#[UsesClass(ExpressionParser::class)]
#[UsesClass(LabelParser::class)]
#[UsesClass(NameReader::class)]
#[UsesClass(OperandParser::class)]
#[UsesClass(Parser::class)]
#[UsesClass(PatternParser::class)]
#[UsesClass(QuantifierParser::class)]
#[UsesClass(ResultParser::class)]
#[UsesClass(TokenReader::class)]
#[UsesClass(ReservedWords::class)]
#[UsesClass(MatchClause::class)]
#[UsesClass(Projection::class)]
#[UsesClass(ReturnClause::class)]
#[UsesClass(BinaryExpression::class)]
#[UsesClass(LiteralExpression::class)]
#[UsesClass(PropertyExpression::class)]
#[UsesClass(VariableExpression::class)]
#[UsesClass(EdgePattern::class)]
#[UsesClass(ElementFilter::class)]
#[UsesClass(GraphPattern::class)]
#[UsesClass(LabelPattern::class)]
#[UsesClass(NodePattern::class)]
#[UsesClass(PathPattern::class)]
#[UsesClass(Quantifier::class)]
#[UsesClass(Query::class)]
#[UsesClass(QueryBlock::class)]
#[Small]
final class TreeWriterTest extends TestCase
{
    /**
     * @throws GqlException
     */
    public function testReportDrawsThePathsAQueryBoundAsOneTreeWithEachSharedBeginningDrawnOnce(): void
    {
        $answered = (new QueryExecution(GraphProjection::of(SampleGraph::analysed()), 10))
            ->query("MATCH p = (c:Class WHERE c.name = 'Controller')-[:declaresMethod]->(m)-[:methodCall]->(t) RETURN p")
        ;
        $output = new BufferedOutput();

        (new TreeWriter())->report($answered, $output);

        self::assertSame(
            <<<'TREE'
                App\Http\Controller
                ├── declaresMethod ──> App\Http\Controller::show
                │   └── methodCall ──> App\Domain\Invoice::total
                └── declaresMethod ──> App\Http\Controller::store
                    └── methodCall ──> App\Domain\Invoice::total

                TREE,
            $output->fetch(),
        );
    }

    /**
     * @throws GqlException
     */
    public function testReportDrawsEveryLengthOfPathABoundedQuantifierBound(): void
    {
        $answered = (new QueryExecution(GraphProjection::of(SampleGraph::analysed()), 10))
            ->query('MATCH p = (a:Method)-[:methodCall]->{1,2}(b:Method) RETURN p')
        ;
        $output = new BufferedOutput();

        (new TreeWriter())->report($answered, $output);

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
    public function testReportDrawsARelationCrossedAgainstItsDirectionPointingBack(): void
    {
        $answered = (new QueryExecution(GraphProjection::of(SampleGraph::analysed()), 10))
            ->query("MATCH p = (g:Method WHERE g.name = 'get')<-[:methodCall]-(t) RETURN p")
        ;
        $output = new BufferedOutput();

        (new TreeWriter())->report($answered, $output);

        self::assertSame(
            <<<'TREE'
                App\Cache\Store::get
                └── methodCall <── App\Domain\Invoice::total

                TREE,
            $output->fetch(),
        );
    }

    /**
     * @throws GqlException
     */
    public function testReportDrawsAPathThatTurnsBackAlongARelationItCameBy(): void
    {
        $answered = (new QueryExecution(GraphProjection::of(SampleGraph::analysed()), 10))
            ->query("MATCH p = (s:Method WHERE s.name = 'show')-[:methodCall]->(t)<-[:methodCall]-(o) RETURN p")
        ;
        $output = new BufferedOutput();

        (new TreeWriter())->report($answered, $output);

        self::assertSame(
            <<<'TREE'
                App\Http\Controller::show
                └── methodCall ──> App\Domain\Invoice::total
                    ├── methodCall <── App\Http\Controller::show
                    └── methodCall <── App\Http\Controller::store

                TREE,
            $output->fetch(),
        );
    }

    public function testReportRejectsAnAnswerWithoutTheRequiredElements(): void
    {
        $answered = new ResultTable([new ResultColumn('p', 'NODE')], [new ResultRow([new NodeDatum('App\Http\Kernel')])]);
        $output = new BufferedOutput();

        $this->expectException(QueryOutputException::class);
        $this->expectExceptionMessage('Tree output requires paths, but the result contains none. Bind and return a path (for example, MATCH p = (a)-->(b) RETURN p), or use --output=table or --output=json.');

        (new TreeWriter())->report($answered, $output);
    }

    public function testReportWritesNothingForAnAnswerThatFoundNothing(): void
    {
        $output = new BufferedOutput();

        (new TreeWriter())->report(ResultTable::nothing(), $output);

        self::assertSame('', $output->fetch());
    }

    public function testPathsHoldsNothingForAnAnswerThatBoundNoPath(): void
    {
        self::assertSame([], TreeWriter::paths(ResultTable::nothing()));
    }

    public function testPathsHoldsAPathBoundTwiceOnce(): void
    {
        $path = PathDatum::at(new NodeDatum('a'))->continuedBy(new EdgeDatum('e', ['calls'], [], 'a', 'b'), new NodeDatum('b'));
        $answered = new ResultTable(
            [new ResultColumn('p', 'PATH'), new ResultColumn('again', 'PATH')],
            [new ResultRow([$path, $path])],
        );

        self::assertEquals([[new TreeStep('', 'a'), new TreeStep('calls', 'b')]], TreeWriter::paths($answered));
    }

    public function testPathsSortsThePathsSoThatThoseSharingABeginningStandTogether(): void
    {
        $fromB = PathDatum::at(new NodeDatum('b'))->continuedBy(new EdgeDatum('e1', ['calls'], [], 'b', 'c'), new NodeDatum('c'));
        $fromA = PathDatum::at(new NodeDatum('a'))->continuedBy(new EdgeDatum('e2', ['calls'], [], 'a', 'c'), new NodeDatum('c'));
        $atB = PathDatum::at(new NodeDatum('b'));
        $answered = new ResultTable(
            [new ResultColumn('p', 'PATH')],
            [new ResultRow([$fromB]), new ResultRow([$fromA]), new ResultRow([$atB])],
        );

        self::assertEquals(
            [
                [new TreeStep('', 'a'), new TreeStep('calls', 'c')],
                [new TreeStep('', 'b')],
                [new TreeStep('', 'b'), new TreeStep('calls', 'c')],
            ],
            TreeWriter::paths($answered),
        );
    }

    public function testPathsFindsAPathHeldInsideAList(): void
    {
        $answered = new ResultTable(
            [new ResultColumn('ps', 'LIST')],
            [new ResultRow([new ListDatum([PathDatum::at(new NodeDatum('a'))])])],
        );

        self::assertEquals([[new TreeStep('', 'a')]], TreeWriter::paths($answered));
    }

    public function testStepsReadsAPathThatCrossesNothingAsTheSymbolItStartsAt(): void
    {
        self::assertEquals([new TreeStep('', 'a')], TreeWriter::steps(PathDatum::at(new NodeDatum('a'))));
    }

    public function testStepsReadsARelationAsAStepToTheSymbolItReaches(): void
    {
        $path = PathDatum::at(new NodeDatum('a'))->continuedBy(
            new EdgeDatum('e', ['methodCall', 'call', 'usage'], [], 'a', 'b'),
            new NodeDatum('b'),
        );

        self::assertEquals([new TreeStep('', 'a'), new TreeStep('methodCall', 'b')], TreeWriter::steps($path));
    }

    public function testStepsReadsARelationCrossedAgainstItsDirectionAsAStepTakenBackwards(): void
    {
        $path = PathDatum::at(new NodeDatum('b'))->continuedBy(new EdgeDatum('e', ['calls'], [], 'a', 'b'), new NodeDatum('a'));

        self::assertEquals([new TreeStep('', 'b'), new TreeStep('calls', 'a', true)], TreeWriter::steps($path));
    }

    public function testStepsReadsARelationFromASymbolToItselfAsAStepTakenForwards(): void
    {
        $path = PathDatum::at(new NodeDatum('a'))->continuedBy(new EdgeDatum('e', ['calls'], [], 'a', 'a'), new NodeDatum('a'));

        self::assertEquals([new TreeStep('', 'a'), new TreeStep('calls', 'a')], TreeWriter::steps($path));
    }

    public function testSignatureTellsNothingApartWhenThereAreNoStepsToTellApart(): void
    {
        self::assertSame('', TreeWriter::signature([]));
    }

    public function testSignatureJoinsTheKeysOfTheStepsInOrder(): void
    {
        self::assertSame(
            "\0>\0a\1calls\0>\0b",
            TreeWriter::signature([new TreeStep('', 'a'), new TreeStep('calls', 'b')]),
        );
    }

    public function testSignatureTellsAStepTakenBackwardsApart(): void
    {
        self::assertSame(
            "\0>\0b\1calls\0<\0a",
            TreeWriter::signature([new TreeStep('', 'b'), new TreeStep('calls', 'a', true)]),
        );
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

    public function testSharedLengthSharesEveryStepOfTheSamePath(): void
    {
        $left = [new TreeStep('', 'a'), new TreeStep('calls', 'b')];
        $right = [new TreeStep('', 'a'), new TreeStep('calls', 'b')];

        self::assertSame(2, TreeWriter::sharedLength($left, $right));
    }

    public function testSharedLengthDoesNotShareAStepTakenTheOtherWayRound(): void
    {
        $left = [new TreeStep('', 'a'), new TreeStep('calls', 'b')];
        $right = [new TreeStep('', 'a'), new TreeStep('calls', 'b', true)];

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

    public function testHasSiblingLooksPastPathsThatBranchOffDeeper(): void
    {
        $first = [new TreeStep('', 'a'), new TreeStep('calls', 'b'), new TreeStep('calls', 'd')];
        $second = [new TreeStep('', 'a'), new TreeStep('calls', 'b'), new TreeStep('calls', 'e')];
        $third = [new TreeStep('', 'a'), new TreeStep('calls', 'c')];

        self::assertTrue(TreeWriter::hasSibling([$first, $second, $third], 0, 1));
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

    public function testLineWritesTheLastStepAtADepthAsAClosedBranch(): void
    {
        $path = [new TreeStep('', 'a'), new TreeStep('calls', 'b')];

        self::assertSame('└── calls ──> b', TreeWriter::line($path, 1, [1 => false]));
    }

    public function testLineKeepsTheBranchOpenWhenAnotherStepStandsBesideIt(): void
    {
        $path = [new TreeStep('', 'a'), new TreeStep('calls', 'b')];

        self::assertSame('├── calls ──> b', TreeWriter::line($path, 1, [1 => true]));
    }

    public function testLineDrawsAStepTakenBackwardsPointingBack(): void
    {
        $path = [new TreeStep('', 'a'), new TreeStep('calls', 'b', true)];

        self::assertSame('└── calls <── b', TreeWriter::line($path, 1, [1 => false]));
    }

    public function testLineCarriesAnOpenBranchAboveDownPastADeeperStep(): void
    {
        $path = [new TreeStep('', 'a'), new TreeStep('calls', 'b'), new TreeStep('calls', 'd')];

        self::assertSame('│   └── calls ──> d', TreeWriter::line($path, 2, [1 => true, 2 => false]));
    }

    public function testLineIndentsADeeperStepUnderAClosedBranchWithSpace(): void
    {
        $path = [new TreeStep('', 'a'), new TreeStep('calls', 'b'), new TreeStep('calls', 'd')];

        self::assertSame('    └── calls ──> d', TreeWriter::line($path, 2, [1 => false, 2 => false]));
    }
}
