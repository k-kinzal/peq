<?php

declare(strict_types=1);

namespace Tests\Unit\Gql\Execution;

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
use App\Gql\Execution\SetOperation;
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
use App\Gql\Parsing\StatementRefusal;
use App\Gql\Parsing\TokenReader;
use App\Gql\ReservedWords;
use App\Gql\Result\ResultColumn;
use App\Gql\Result\ResultRow;
use App\Gql\Result\ResultTable;
use App\Gql\StatusCode;
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
use App\Gql\Syntax\Pattern\EdgeDirection;
use App\Gql\Syntax\Pattern\EdgePattern;
use App\Gql\Syntax\Pattern\ElementFilter;
use App\Gql\Syntax\Pattern\GraphPattern;
use App\Gql\Syntax\Pattern\LabelPattern;
use App\Gql\Syntax\Pattern\NodePattern;
use App\Gql\Syntax\Pattern\PathMode;
use App\Gql\Syntax\Pattern\PathPattern;
use App\Gql\Syntax\Pattern\Quantifier;
use App\Gql\Syntax\Query;
use App\Gql\Syntax\QueryBlock;
use App\Gql\Syntax\SetOperator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Tests\Fixture\Gql\SampleGraph;

/**
 * @internal
 */
#[CoversClass(QueryExecution::class)]
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
#[UsesClass(EdgeDatum::class)]
#[UsesClass(IntegerDatum::class)]
#[UsesClass(ListDatum::class)]
#[UsesClass(NodeDatum::class)]
#[UsesClass(PathDatum::class)]
#[UsesClass(StringDatum::class)]
#[UsesClass(EdgeLabels::class)]
#[UsesClass(EdgeProperties::class)]
#[UsesClass(ElementGraph::class)]
#[UsesClass(GraphProjection::class)]
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
#[UsesClass(SetOperation::class)]
#[UsesClass(GqlException::class)]
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
#[UsesClass(StatementRefusal::class)]
#[UsesClass(TokenReader::class)]
#[UsesClass(ReservedWords::class)]
#[UsesClass(ResultColumn::class)]
#[UsesClass(ResultRow::class)]
#[UsesClass(ResultTable::class)]
#[UsesClass(StatusCode::class)]
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
#[UsesClass(Quantifier::class)]
#[UsesClass(Query::class)]
#[UsesClass(QueryBlock::class)]
#[Small]
final class QueryExecutionTest extends TestCase
{
    /**
     * @throws GqlException
     */
    public function testAgainstQueriesTheGraphAnalysisProduced(): void
    {
        $answered = QueryExecution::against(SampleGraph::analysed())->query('MATCH (p:Class) RETURN p.name AS name ORDER BY name');

        self::assertEquals(
            new ResultTable(
                [new ResultColumn('name', 'STRING')],
                [
                    new ResultRow([new StringDatum('Controller')]),
                    new ResultRow([new StringDatum('Invoice')]),
                    new ResultRow([new StringDatum('Kernel')]),
                    new ResultRow([new StringDatum('Store')]),
                ],
            ),
            $answered,
        );
    }

    /**
     * @throws GqlException
     */
    public function testAgainstLimitsQuantifiersToTheHopLimitItWasGiven(): void
    {
        $execution = QueryExecution::against(SampleGraph::analysed(), 1);

        $this->expectException(GqlException::class);
        $this->expectExceptionMessage('a quantifier may be written with an upper bound of at most 1 here, and one is written with 2: raise --hops to allow more');

        $execution->query("MATCH (a:Method WHERE a.name = 'show')-[:methodCall]->{1,2}(b:Method) RETURN b.name AS name");
    }

    /**
     * @throws GqlException
     */
    public function testQueryReadsAQueryAndRunsIt(): void
    {
        $answered = (new QueryExecution(GraphProjection::of(SampleGraph::analysed()), 10))->query('RETURN 1 AS n');

        self::assertEquals(new ResultTable([new ResultColumn('n', 'INT64')], [new ResultRow([new IntegerDatum(1)])]), $answered);
    }

    /**
     * @throws GqlException
     */
    public function testQueryAnswersWithNoRowsForAQueryThatFoundNothing(): void
    {
        $answered = (new QueryExecution(GraphProjection::of(SampleGraph::analysed()), 10))->query('MATCH (p:Interface) RETURN p.name AS name');

        self::assertEquals(new ResultTable([new ResultColumn('name', 'NULL')], []), $answered);
    }

    /**
     * @throws GqlException
     */
    public function testQuerySaysThatItFoundNothingRatherThanFailing(): void
    {
        $answered = (new QueryExecution(GraphProjection::of(SampleGraph::analysed()), 10))->query('MATCH (p:Interface) RETURN p.name AS name');

        self::assertSame(StatusCode::NoData, $answered->status());
    }

    /**
     * @throws GqlException
     */
    public function testQueryReportsAQueryThatCannotBeRead(): void
    {
        $execution = new QueryExecution(GraphProjection::of(SampleGraph::analysed()), 10);

        $this->expectException(GqlException::class);
        $this->expectExceptionMessage('[42001] error: syntax error or access rule violation - invalid syntax: expected a RETURN statement');

        $execution->query('MATCH (p) RETRUN p');
    }

    /**
     * @throws GqlException
     */
    public function testQueryRefusesAQuantifierWithNoUpperBoundOnAPathThatNamesNoRestrictor(): void
    {
        $execution = new QueryExecution(GraphProjection::of(SampleGraph::analysed()), 10);

        $this->expectException(GqlException::class);
        $this->expectExceptionMessage('write TRAIL, SIMPLE or ACYCLIC');

        $execution->query("MATCH (a:Method WHERE a.name = 'show')-[:methodCall]->*(b:Method) RETURN b.name AS name");
    }

    /**
     * @throws GqlException
     */
    public function testQueryFollowsAQuantifierWithNoUpperBoundUnderARestrictorWhateverTheHopLimit(): void
    {
        $execution = new QueryExecution(GraphProjection::of(SampleGraph::analysed()), 1);

        $answered = $execution->query("MATCH TRAIL (a:Method WHERE a.name = 'show')-[:methodCall]->{1,}(b:Method) RETURN b.name AS name");

        self::assertEquals(
            new ResultTable(
                [new ResultColumn('name', 'STRING')],
                [new ResultRow([new StringDatum('total')]), new ResultRow([new StringDatum('get')])],
            ),
            $answered,
        );
    }

    /**
     * @throws GqlException
     */
    #[DataProvider('providerEdgePatternsAndWhatTheyReachFromTheDomainMethod')]
    public function testQueryCrossesACallTheWayTheEdgePatternIsDrawn(string $query, ResultTable $expected): void
    {
        self::assertEquals($expected, (new QueryExecution(GraphProjection::of(SampleGraph::analysed()), 10))->query($query));
    }

    /**
     * @return iterable<string, array{string, ResultTable}>
     */
    public static function providerEdgePatternsAndWhatTheyReachFromTheDomainMethod(): iterable
    {
        yield 'pointing right, to what it calls' => [
            "MATCH (a WHERE a.name = 'total')-[:methodCall]->(b) RETURN b.name AS name ORDER BY name",
            new ResultTable([new ResultColumn('name', 'STRING')], [new ResultRow([new StringDatum('get')])]),
        ];

        yield 'pointing left, to what calls it' => [
            "MATCH (a WHERE a.name = 'total')<-[:methodCall]-(b) RETURN b.name AS name ORDER BY name",
            new ResultTable(
                [new ResultColumn('name', 'STRING')],
                [new ResultRow([new StringDatum('show')]), new ResultRow([new StringDatum('store')])],
            ),
        ];

        yield 'left or right, to both' => [
            "MATCH (a WHERE a.name = 'total')<-[:methodCall]->(b) RETURN b.name AS name ORDER BY name",
            new ResultTable(
                [new ResultColumn('name', 'STRING')],
                [new ResultRow([new StringDatum('get')]), new ResultRow([new StringDatum('show')]), new ResultRow([new StringDatum('store')])],
            ),
        ];

        yield 'any direction, to both' => [
            "MATCH (a WHERE a.name = 'total')-[:methodCall]-(b) RETURN b.name AS name ORDER BY name",
            new ResultTable(
                [new ResultColumn('name', 'STRING')],
                [new ResultRow([new StringDatum('get')]), new ResultRow([new StringDatum('show')]), new ResultRow([new StringDatum('store')])],
            ),
        ];

        yield 'undirected, to nothing, since source code writes no call that points neither way' => [
            "MATCH (a WHERE a.name = 'total')~[:methodCall]~(b) RETURN b.name AS name ORDER BY name",
            new ResultTable([new ResultColumn('name', 'NULL')], []),
        ];

        yield 'left or undirected, to what calls it' => [
            "MATCH (a WHERE a.name = 'total')<~[:methodCall]~(b) RETURN b.name AS name ORDER BY name",
            new ResultTable(
                [new ResultColumn('name', 'STRING')],
                [new ResultRow([new StringDatum('show')]), new ResultRow([new StringDatum('store')])],
            ),
        ];

        yield 'undirected or right, to what it calls' => [
            "MATCH (a WHERE a.name = 'total')~[:methodCall]~>(b) RETURN b.name AS name ORDER BY name",
            new ResultTable([new ResultColumn('name', 'STRING')], [new ResultRow([new StringDatum('get')])]),
        ];
    }

    /**
     * @throws GqlException
     */
    public function testRunAnswersWithBothSidesOfAQueryThatCombinesTwoRuns(): void
    {
        $query = new Query(
            [
                new QueryBlock([new ReturnClause([new Projection(new LiteralExpression(new IntegerDatum(1)), 'n')])]),
                new QueryBlock([new ReturnClause([new Projection(new LiteralExpression(new IntegerDatum(2)), 'n')])]),
            ],
            [SetOperator::UnionAll],
        );

        self::assertEquals(
            new ResultTable(
                [new ResultColumn('n', 'INT64')],
                [new ResultRow([new IntegerDatum(1)]), new ResultRow([new IntegerDatum(2)])],
            ),
            (new QueryExecution(new ElementGraph([], [], []), 10))->run($query),
        );
    }

    /**
     * @throws GqlException
     */
    public function testRunAnswersAQuantifierWrittenUpToTheHopLimit(): void
    {
        $query = Query::of(new QueryBlock([
            new MatchClause(new GraphPattern([
                new PathPattern(
                    [
                        new NodePattern('a', null, new ElementFilter(['name' => new LiteralExpression(new StringDatum('show'))])),
                        new EdgePattern(EdgeDirection::Along, null, null, new ElementFilter(), new Quantifier(1, 3)),
                        new NodePattern('b'),
                    ],
                    PathMode::Walk,
                ),
            ])),
            new ReturnClause([new Projection(new PropertyExpression(new VariableExpression('b'), 'name'), 'name')]),
        ]));

        self::assertEquals(
            new ResultTable(
                [new ResultColumn('name', 'STRING')],
                [new ResultRow([new StringDatum('total')]), new ResultRow([new StringDatum('get')])],
            ),
            (new QueryExecution(GraphProjection::of(SampleGraph::analysed()), 3))->run($query),
        );
    }

    /**
     * @throws GqlException
     */
    public function testRunRefusesAQuantifierWrittenBeyondTheHopLimitBeforeLookingAtTheGraph(): void
    {
        $query = Query::of(new QueryBlock([
            new MatchClause(new GraphPattern([
                new PathPattern(
                    [new NodePattern('a'), new EdgePattern(EdgeDirection::Along, null, null, new ElementFilter(), new Quantifier(1, 4)), new NodePattern('b')],
                    PathMode::Walk,
                ),
            ])),
            new ReturnClause(),
        ]));
        $execution = new QueryExecution(new ElementGraph([], [], []), 3);

        $this->expectException(GqlException::class);
        $this->expectExceptionMessage('a quantifier may be written with an upper bound of at most 3 here, and one is written with 4: raise --hops to allow more');

        $execution->run($query);
    }

    /**
     * @throws GqlException
     */
    public function testQueryBindsANameInARepeatedGroupAfreshOnEveryRepetition(): void
    {
        $answered = (new QueryExecution(GraphProjection::of(SampleGraph::analysed()), 10))->query('MATCH ((a)-[:methodCall]->(b)){2} RETURN count(*) AS n');

        self::assertEquals(new ResultTable([new ResultColumn('n', 'INT64')], [new ResultRow([new IntegerDatum(2)])]), $answered);
    }

    /**
     * @throws GqlException
     */
    public function testQueryReadsANodePatternFollowedByARepeatedGroupAsOnePath(): void
    {
        $answered = (new QueryExecution(GraphProjection::of(SampleGraph::analysed()), 10))
            ->query("MATCH (s WHERE s.name = 'show')((a)-[:methodCall]->(b)){2} RETURN s.name AS s")
        ;

        self::assertEquals(new ResultTable([new ResultColumn('s', 'STRING')], [new ResultRow([new StringDatum('show')])]), $answered);
    }
}
