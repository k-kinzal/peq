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
use App\Gql\Syntax\Clause\OrderByClause;
use App\Gql\Syntax\Clause\Projection;
use App\Gql\Syntax\Clause\ReturnClause;
use App\Gql\Syntax\Clause\SortDirection;
use App\Gql\Syntax\Clause\SortKey;
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
use App\Gql\Syntax\Query;
use App\Gql\Syntax\QueryBlock;
use App\Reporter\Diagram\Diagram;
use App\Reporter\Diagram\DiagramEdge;
use App\Reporter\Diagram\DiagramNode;
use App\Reporter\Query\DotWriter;
use App\Reporter\Query\QueryOutputException;
use App\Reporter\Query\ResultElements;
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
#[CoversClass(DotWriter::class)]
#[UsesClass(ResultElements::class)]
#[UsesClass(Diagram::class)]
#[UsesClass(DiagramEdge::class)]
#[UsesClass(DiagramNode::class)]
#[UsesClass(ResultColumn::class)]
#[UsesClass(ResultRow::class)]
#[UsesClass(ResultTable::class)]
#[UsesClass(EdgeDatum::class)]
#[UsesClass(IntegerDatum::class)]
#[UsesClass(NodeDatum::class)]
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
#[UsesClass(ListDatum::class)]
#[UsesClass(NullDatum::class)]
#[UsesClass(PathDatum::class)]
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
#[UsesClass(OrderByClause::class)]
#[UsesClass(Projection::class)]
#[UsesClass(ReturnClause::class)]
#[UsesClass(SortDirection::class)]
#[UsesClass(SortKey::class)]
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
#[UsesClass(Query::class)]
#[UsesClass(QueryBlock::class)]
#[UsesClass(\App\Reporter\Query\EdgeCaption::class)]
#[Small]
final class DotWriterTest extends TestCase
{
    /**
     * @throws GqlException
     */
    public function testReportWritesThePieceOfGraphAQueryFoundAsADigraph(): void
    {
        $graph = GraphProjection::of(SampleGraph::analysed());
        $answered = (new QueryExecution($graph, 10))
            ->query("MATCH (c:Class WHERE c.name = 'Store')-[:declaresMethod]->(m) RETURN c, m")
        ;
        $output = new BufferedOutput();

        (new DotWriter($graph))->report($answered, $output);

        self::assertSame(
            <<<'DOT'
                digraph peq {
                    rankdir=LR;
                    "App\\Cache\\Store" [label="App\\Cache\\Store\nclass"];
                    "App\\Cache\\Store::get" [label="App\\Cache\\Store::get\nmethod"];
                    "App\\Cache\\Store" -> "App\\Cache\\Store::get" [label="declaresMethod"];
                }

                DOT,
            $output->fetch(),
        );
    }

    /**
     * @throws GqlException
     */
    public function testReportWritesEveryRelationBetweenTheSymbolsFoundOnceWhateverOrderTheyWereFoundIn(): void
    {
        $graph = GraphProjection::of(SampleGraph::analysed());
        $answered = (new QueryExecution($graph, 10))
            ->query("MATCH (m:Method)-[:methodCall]->(t:Method WHERE t.name = 'total') RETURN t, m ORDER BY m.name")
        ;
        $output = new BufferedOutput();

        (new DotWriter($graph))->report($answered, $output);

        self::assertSame(
            <<<'DOT'
                digraph peq {
                    rankdir=LR;
                    "App\\Domain\\Invoice::total" [label="App\\Domain\\Invoice::total\nmethod"];
                    "App\\Http\\Controller::show" [label="App\\Http\\Controller::show\nmethod"];
                    "App\\Http\\Controller::store" [label="App\\Http\\Controller::store\nmethod"];
                    "App\\Http\\Controller::show" -> "App\\Domain\\Invoice::total" [label="methodCall"];
                    "App\\Http\\Controller::store" -> "App\\Domain\\Invoice::total" [label="methodCall"];
                }

                DOT,
            $output->fetch(),
        );
    }

    public function testReportWritesNoArrowBetweenSymbolsWhenThereIsNoGraphToReadItFrom(): void
    {
        $answered = new ResultTable(
            [new ResultColumn('c', 'NODE'), new ResultColumn('m', 'NODE')],
            [new ResultRow([new NodeDatum('App\Cache\Store'), new NodeDatum('App\Cache\Store::get')])],
        );
        $output = new BufferedOutput();

        (new DotWriter())->report($answered, $output);

        self::assertSame(
            <<<'DOT'
                digraph peq {
                    rankdir=LR;
                    "App\\Cache\\Store" [label="App\\Cache\\Store"];
                    "App\\Cache\\Store::get" [label="App\\Cache\\Store::get"];
                }

                DOT,
            $output->fetch(),
        );
    }

    public function testReportWritesARelationTheAnswerHoldsWithoutNeedingTheGraph(): void
    {
        $call = new EdgeDatum('e', ['methodCall', 'call', 'usage'], [], 'App\Domain\Invoice::total', 'App\Cache\Store::get');
        $answered = new ResultTable([new ResultColumn('e', 'EDGE')], [new ResultRow([$call])]);
        $output = new BufferedOutput();

        (new DotWriter())->report($answered, $output);

        self::assertSame(
            <<<'DOT'
                digraph peq {
                    rankdir=LR;
                    "App\\Domain\\Invoice::total" [label="App\\Domain\\Invoice::total"];
                    "App\\Cache\\Store::get" [label="App\\Cache\\Store::get"];
                    "App\\Domain\\Invoice::total" -> "App\\Cache\\Store::get" [label="methodCall"];
                }

                DOT,
            $output->fetch(),
        );
    }

    public function testReportRejectsAnAnswerWithoutTheRequiredElements(): void
    {
        $answered = new ResultTable([new ResultColumn('n', 'INT64')], [new ResultRow([new IntegerDatum(1)])]);
        $output = new BufferedOutput();

        $this->expectException(QueryOutputException::class);
        $this->expectExceptionMessage('Graph output requires nodes, edges or paths, but the result contains none. Return elements (for example, RETURN n instead of RETURN n.id), or use --output=table or --output=json.');

        (new DotWriter())->report($answered, $output);
    }

    public function testReportWritesNothingForAnAnswerThatFoundNothing(): void
    {
        $output = new BufferedOutput();

        (new DotWriter())->report(ResultTable::nothing(), $output);

        self::assertSame('', $output->fetch());
    }

    public function testVertexWritesASymbolAsABoxLabelledWithItsNameAboveWhatItIs(): void
    {
        self::assertSame(
            '    "App\\\Invoice" [label="App\\\Invoice\nclass"];',
            DotWriter::vertex(new DiagramNode('App\Invoice', 'class')),
        );
    }

    public function testVertexLabelsASymbolNothingIsKnownAboutWithItsNameAlone(): void
    {
        self::assertSame('    "a" [label="a"];', DotWriter::vertex(new DiagramNode('a')));
    }

    public function testVertexEscapesAQuotationMarkInTheNameWithoutEscapingTheLineBreak(): void
    {
        self::assertSame(
            '    "say \"hi\"" [label="say \"hi\"\nclass"];',
            DotWriter::vertex(new DiagramNode('say "hi"', 'class')),
        );
    }

    public function testArrowWritesARelationAsAnArrowLabelledWithWhatItIs(): void
    {
        self::assertSame('    "a" -> "b" [label="calls"];', DotWriter::arrow(new DiagramEdge('a', 'b', 'calls')));
    }

    public function testArrowEscapesTheNamesOfTheSymbolsItJoins(): void
    {
        self::assertSame(
            '    "App\\\A" -> "App\\\B" [label="methodCall"];',
            DotWriter::arrow(new DiagramEdge('App\A', 'App\B', 'methodCall')),
        );
    }

    public function testArrowOfARelationWithNoNameHasAnEmptyLabel(): void
    {
        self::assertSame('    "a" -> "b" [label=""];', DotWriter::arrow(new DiagramEdge('a', 'b')));
    }

    public function testQuotedLetsANamespaceSeparatorSurviveBeingWritten(): void
    {
        self::assertSame('App\\\Invoice', DotWriter::quoted('App\Invoice'));
    }

    public function testQuotedLetsAQuotationMarkSurviveBeingWritten(): void
    {
        self::assertSame('say \"hi\"', DotWriter::quoted('say "hi"'));
    }

    public function testQuotedLeavesTextWithNothingToEscapeAsItIs(): void
    {
        self::assertSame('declaresMethod', DotWriter::quoted('declaresMethod'));
    }
}
