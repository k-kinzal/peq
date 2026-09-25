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
use App\Gql\Syntax\Query;
use App\Gql\Syntax\QueryBlock;
use App\Reporter\Diagram\Diagram;
use App\Reporter\Diagram\DiagramEdge;
use App\Reporter\Diagram\DiagramNode;
use App\Reporter\Query\ResultElements;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Tests\Fixture\Gql\SampleGraph;

/**
 * @internal
 */
#[UsesClass(\App\Analyzer\Graph\EdgeIdentity::class)]
#[CoversClass(ResultElements::class)]
#[UsesClass(Diagram::class)]
#[UsesClass(DiagramEdge::class)]
#[UsesClass(DiagramNode::class)]
#[UsesClass(ResultColumn::class)]
#[UsesClass(ResultRow::class)]
#[UsesClass(ResultTable::class)]
#[UsesClass(EdgeDatum::class)]
#[UsesClass(IntegerDatum::class)]
#[UsesClass(ListDatum::class)]
#[UsesClass(NodeDatum::class)]
#[UsesClass(PathDatum::class)]
#[UsesClass(StringDatum::class)]
#[UsesClass(ElementGraph::class)]
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
#[UsesClass(NullDatum::class)]
#[UsesClass(EdgeLabels::class)]
#[UsesClass(EdgeProperties::class)]
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
#[UsesClass(Query::class)]
#[UsesClass(QueryBlock::class)]
#[UsesClass(NullDatum::class)]
#[UsesClass(\App\Reporter\Query\EdgeCaption::class)]
#[Small]
final class ResultElementsTest extends TestCase
{
    public function testOfDrawsNothingForAnAnswerHoldingNothing(): void
    {
        self::assertTrue(ResultElements::of(ResultTable::nothing())->empty());
    }

    /**
     * @throws GqlException
     */
    public function testOfDrawsTheSymbolsAQueryFoundWithWhatTheyAreAndWhereTheyAreWritten(): void
    {
        $graph = GraphProjection::of(SampleGraph::analysed());
        $answered = (new QueryExecution($graph, 10))
            ->query("MATCH (c:Class WHERE c.name = 'Store')-[:declaresMethod]->(m) RETURN c, m")
        ;

        self::assertEquals(
            [
                new DiagramNode('App\Cache\Store', 'class', '/project/src/Cache/Store.php:3'),
                new DiagramNode('App\Cache\Store::get', 'method', '/project/src/Cache/Store.php:8'),
            ],
            ResultElements::of($answered, $graph)->nodes(),
        );
    }

    /**
     * @throws GqlException
     */
    public function testOfReadsBackEveryRelationTheGraphHoldsBetweenTheSymbolsAQueryFound(): void
    {
        $graph = GraphProjection::of(SampleGraph::analysed());
        $answered = (new QueryExecution($graph, 10))->query('MATCH (c:Class)-[:declaresMethod]->(m) RETURN c, m');

        self::assertEquals(
            [
                new DiagramEdge('App\Http\Controller', 'App\Http\Controller::show', 'declaresMethod'),
                new DiagramEdge('App\Http\Controller', 'App\Http\Controller::store', 'declaresMethod'),
                new DiagramEdge('App\Http\Controller::show', 'App\Domain\Invoice::total', 'methodCall'),
                new DiagramEdge('App\Http\Controller::store', 'App\Domain\Invoice::total', 'methodCall'),
                new DiagramEdge('App\Domain\Invoice', 'App\Domain\Invoice::total', 'declaresMethod'),
                new DiagramEdge('App\Domain\Invoice::total', 'App\Cache\Store::get', 'methodCall'),
                new DiagramEdge('App\Cache\Store', 'App\Cache\Store::get', 'declaresMethod'),
            ],
            ResultElements::of($answered, $graph)->edges(),
        );
    }

    /**
     * @throws GqlException
     */
    public function testOfDrawsNoArrowsWhenThereIsNoGraphToReadThemFrom(): void
    {
        $answered = (new QueryExecution(GraphProjection::of(SampleGraph::analysed()), 10))
            ->query('MATCH (c:Class)-[:declaresMethod]->(m) RETURN c, m')
        ;

        self::assertSame([], ResultElements::of($answered)->edges());
    }

    public function testOfDrawsEverySymbolAndRelationAPathHolds(): void
    {
        $path = PathDatum::at(new NodeDatum('App\Domain\Invoice::total'))->continuedBy(
            new EdgeDatum('e', ['methodCall', 'call', 'usage'], [], 'App\Domain\Invoice::total', 'App\Cache\Store::get'),
            new NodeDatum('App\Cache\Store::get'),
        );
        $diagram = ResultElements::of(new ResultTable([new ResultColumn('p', 'PATH')], [new ResultRow([$path])]));

        self::assertEquals(
            [[new DiagramNode('App\Domain\Invoice::total'), new DiagramNode('App\Cache\Store::get')], [new DiagramEdge('App\Domain\Invoice::total', 'App\Cache\Store::get', 'methodCall')]],
            [$diagram->nodes(), $diagram->edges()],
        );
    }

    public function testOfDrawsASymbolTheAnswerDescribesInFullEvenWhenARelationNamesItFirst(): void
    {
        $call = new EdgeDatum('e', ['methodCall'], [], 'App\Domain\Invoice::total', 'App\Cache\Store::get');
        $get = new NodeDatum('App\Cache\Store::get', ['Method'], ['kind' => new StringDatum('method')]);
        $answered = new ResultTable([new ResultColumn('e', 'EDGE'), new ResultColumn('t', 'NODE')], [new ResultRow([$call, $get])]);

        self::assertEquals(
            [new DiagramNode('App\Cache\Store::get', 'method'), new DiagramNode('App\Domain\Invoice::total')],
            ResultElements::of($answered)->nodes(),
        );
    }

    public function testConnectAddsNothingToADrawingWithNothingInIt(): void
    {
        $diagram = new Diagram();

        ResultElements::connect($diagram, new ElementGraph([], [], []));

        self::assertSame([], $diagram->edges());
    }

    public function testConnectAddsTheRelationTheGraphHoldsBetweenTwoSymbolsDrawn(): void
    {
        $call = new EdgeDatum('a|method-call|b', ['methodCall', 'call', 'usage'], [], 'a', 'b');
        $graph = new ElementGraph(['a' => new NodeDatum('a'), 'b' => new NodeDatum('b')], ['a' => [$call]], ['b' => [$call]]);
        $diagram = new Diagram();
        $diagram->add(new DiagramNode('a'));
        $diagram->add(new DiagramNode('b'));

        ResultElements::connect($diagram, $graph);

        self::assertEquals([new DiagramEdge('a', 'b', 'methodCall')], $diagram->edges());
    }

    public function testConnectLeavesOutARelationToASymbolThatWasNotDrawn(): void
    {
        $call = new EdgeDatum('a|method-call|b', ['methodCall', 'call', 'usage'], [], 'a', 'b');
        $graph = new ElementGraph(['a' => new NodeDatum('a'), 'b' => new NodeDatum('b')], ['a' => [$call]], ['b' => [$call]]);
        $diagram = new Diagram();
        $diagram->add(new DiagramNode('a'));

        ResultElements::connect($diagram, $graph);

        self::assertSame([], $diagram->edges());
    }

    public function testCollectNodesAddsNothingForARowHoldingNoSymbol(): void
    {
        $diagram = new Diagram();

        ResultElements::collectNodes($diagram, new ResultRow([new IntegerDatum(1)]));

        self::assertTrue($diagram->empty());
    }

    public function testCollectNodesDrawsASymbolHeldInsideAList(): void
    {
        $diagram = new Diagram();

        ResultElements::collectNodes($diagram, new ResultRow([new ListDatum([new NodeDatum('App\Invoice')])]));

        self::assertEquals([new DiagramNode('App\Invoice')], $diagram->nodes());
    }

    public function testCollectNodesLeavesTheRelationsOfAPathToBeCollectedSeparately(): void
    {
        $path = PathDatum::at(new NodeDatum('a'))->continuedBy(new EdgeDatum('e', ['calls'], [], 'a', 'b'), new NodeDatum('b'));
        $diagram = new Diagram();

        ResultElements::collectNodes($diagram, new ResultRow([$path]));

        self::assertEquals([[new DiagramNode('a'), new DiagramNode('b')], []], [$diagram->nodes(), $diagram->edges()]);
    }

    public function testCollectRelationsBringsTheSymbolsARelationJoinsIntoTheDrawing(): void
    {
        $diagram = new Diagram();

        ResultElements::collectRelations($diagram, new ResultRow([new EdgeDatum('e', ['calls'], [], 'a', 'b')]));

        self::assertEquals([new DiagramNode('a'), new DiagramNode('b')], $diagram->nodes());
    }

    public function testCollectRelationsDrawsTheRelationUnderTheFirstOfItsLabels(): void
    {
        $diagram = new Diagram();

        ResultElements::collectRelations($diagram, new ResultRow([new EdgeDatum('e', ['methodCall', 'call', 'usage'], [], 'a', 'b')]));

        self::assertEquals([new DiagramEdge('a', 'b', 'methodCall')], $diagram->edges());
    }

    public function testCollectRelationsAddsNothingForARowHoldingNoRelation(): void
    {
        $diagram = new Diagram();

        ResultElements::collectRelations($diagram, new ResultRow([new NodeDatum('a')]));

        self::assertTrue($diagram->empty());
    }

    public function testFlattenedReadsAPathAsItselfAndEverythingItIsMadeOfInOrder(): void
    {
        $start = new NodeDatum('a');
        $crossed = new EdgeDatum('e', [], [], 'a', 'b');
        $end = new NodeDatum('b');
        $path = PathDatum::at($start)->continuedBy($crossed, $end);

        self::assertSame([$path, $start, $crossed, $end], ResultElements::flattened([$path]));
    }

    public function testFlattenedReadsAListAsItselfAndTheValuesInsideIt(): void
    {
        $inner = new IntegerDatum(1);
        $nested = new ListDatum([$inner]);
        $outer = new ListDatum([$nested]);

        self::assertSame([$outer, $nested, $inner], ResultElements::flattened([$outer]));
    }

    public function testFlattenedLeavesAValueWithNothingInsideItAsItIs(): void
    {
        $value = new StringDatum('App\Invoice');

        self::assertSame([$value], ResultElements::flattened([$value]));
    }

    public function testDrawnDrawsASymbolWithWhatItIsAndWhereItIsWritten(): void
    {
        $node = new NodeDatum('App\Invoice', ['Class'], [
            'kind' => new StringDatum('class'),
            'file' => new StringDatum('src/Invoice.php'),
            'line' => new IntegerDatum(12),
        ]);

        self::assertEquals(new DiagramNode('App\Invoice', 'class', 'src/Invoice.php:12'), ResultElements::drawn($node));
    }

    public function testDrawnDrawsASymbolNothingIsKnownAboutByItsNameAlone(): void
    {
        self::assertEquals(new DiagramNode('App\Invoice'), ResultElements::drawn(new NodeDatum('App\Invoice')));
    }

    public function testDrawnGivesNoLocationToASymbolWhoseFileIsKnownButNotItsLine(): void
    {
        $node = new NodeDatum('App\Invoice', ['Class'], [
            'kind' => new StringDatum('class'),
            'file' => new StringDatum('src/Invoice.php'),
        ]);

        self::assertEquals(new DiagramNode('App\Invoice', 'class'), ResultElements::drawn($node));
    }

    public function testDrawnIgnoresAKindOrLineThatIsNotTheKindOfValueItShouldBe(): void
    {
        $node = new NodeDatum('App\Invoice', ['Class'], [
            'kind' => new IntegerDatum(1),
            'file' => new StringDatum('src/Invoice.php'),
            'line' => new StringDatum('12'),
        ]);

        self::assertEquals(new DiagramNode('App\Invoice'), ResultElements::drawn($node));
    }
}
