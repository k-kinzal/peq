<?php

declare(strict_types=1);

namespace Tests\Unit\Gql\Execution;

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
use App\Gql\Argument\NumberArgument;
use App\Gql\Argument\TextArgument;
use App\Gql\Binding\BindingRow;
use App\Gql\Binding\BindingTable;
use App\Gql\Datum\BooleanDatum;
use App\Gql\Datum\DatumIdentity;
use App\Gql\Datum\DatumKind;
use App\Gql\Datum\DatumOrder;
use App\Gql\Datum\EdgeDatum;
use App\Gql\Datum\FloatDatum;
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
use App\Gql\Evaluation\Arithmetic;
use App\Gql\Evaluation\BinaryOperation;
use App\Gql\Evaluation\Comparison;
use App\Gql\Evaluation\ExpressionEvaluation;
use App\Gql\Evaluation\Logic;
use App\Gql\Evaluation\Membership;
use App\Gql\Evaluation\TextOperation;
use App\Gql\Evaluation\UnaryOperation;
use App\Gql\Execution\BlockExecution;
use App\Gql\Execution\MatchExecution;
use App\Gql\Execution\QueryExecution;
use App\Gql\Execution\ReturnExecution;
use App\Gql\Execution\RowExecution;
use App\Gql\Execution\SetOperation;
use App\Gql\GqlException;
use App\Gql\Invocation\Accumulator;
use App\Gql\Invocation\AggregateCatalog;
use App\Gql\Invocation\AverageAccumulator;
use App\Gql\Invocation\CollectAccumulator;
use App\Gql\Invocation\CountAccumulator;
use App\Gql\Invocation\DistinctAccumulator;
use App\Gql\Invocation\ExtremeAccumulator;
use App\Gql\Invocation\FunctionCatalog;
use App\Gql\Invocation\GeneralFunctions;
use App\Gql\Invocation\GraphFunctions;
use App\Gql\Invocation\ListFunctions;
use App\Gql\Invocation\SumAccumulator;
use App\Gql\Invocation\TextFunctions;
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
use App\Gql\Syntax\Clause\FilterClause;
use App\Gql\Syntax\Clause\LetClause;
use App\Gql\Syntax\Clause\MatchClause;
use App\Gql\Syntax\Clause\OrderByClause;
use App\Gql\Syntax\Clause\PageClause;
use App\Gql\Syntax\Clause\Projection;
use App\Gql\Syntax\Clause\ReturnClause;
use App\Gql\Syntax\Clause\SortDirection;
use App\Gql\Syntax\Clause\SortKey;
use App\Gql\Syntax\Clause\VariableBinding;
use App\Gql\Syntax\Expression\BinaryExpression;
use App\Gql\Syntax\Expression\BinaryOperator;
use App\Gql\Syntax\Expression\CallExpression;
use App\Gql\Syntax\Expression\CaseBranch;
use App\Gql\Syntax\Expression\CaseExpression;
use App\Gql\Syntax\Expression\IndexExpression;
use App\Gql\Syntax\Expression\ListExpression;
use App\Gql\Syntax\Expression\LiteralExpression;
use App\Gql\Syntax\Expression\PropertyExpression;
use App\Gql\Syntax\Expression\UnaryExpression;
use App\Gql\Syntax\Expression\UnaryOperator;
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
use App\Gql\Syntax\Query;
use App\Gql\Syntax\QueryBlock;
use App\Gql\Syntax\SetOperator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Tests\Fixture\Gql\AnsweredQuery;

/**
 * @internal
 */
#[CoversClass(BlockExecution::class)]
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
#[UsesClass(NumberArgument::class)]
#[UsesClass(TextArgument::class)]
#[UsesClass(BindingRow::class)]
#[UsesClass(BindingTable::class)]
#[UsesClass(BooleanDatum::class)]
#[UsesClass(DatumIdentity::class)]
#[UsesClass(DatumKind::class)]
#[UsesClass(DatumOrder::class)]
#[UsesClass(EdgeDatum::class)]
#[UsesClass(FloatDatum::class)]
#[UsesClass(IntegerDatum::class)]
#[UsesClass(ListDatum::class)]
#[UsesClass(NodeDatum::class)]
#[UsesClass(NullDatum::class)]
#[UsesClass(PathDatum::class)]
#[UsesClass(StringDatum::class)]
#[UsesClass(EdgeLabels::class)]
#[UsesClass(EdgeProperties::class)]
#[UsesClass(ElementGraph::class)]
#[UsesClass(GraphProjection::class)]
#[UsesClass(NodeLabels::class)]
#[UsesClass(NodeProperties::class)]
#[UsesClass(AggregateDetection::class)]
#[UsesClass(Arithmetic::class)]
#[UsesClass(BinaryOperation::class)]
#[UsesClass(Comparison::class)]
#[UsesClass(ExpressionEvaluation::class)]
#[UsesClass(Logic::class)]
#[UsesClass(Membership::class)]
#[UsesClass(TextOperation::class)]
#[UsesClass(UnaryOperation::class)]
#[UsesClass(GqlException::class)]
#[UsesClass(Accumulator::class)]
#[UsesClass(AggregateCatalog::class)]
#[UsesClass(AverageAccumulator::class)]
#[UsesClass(CollectAccumulator::class)]
#[UsesClass(CountAccumulator::class)]
#[UsesClass(DistinctAccumulator::class)]
#[UsesClass(ExtremeAccumulator::class)]
#[UsesClass(FunctionCatalog::class)]
#[UsesClass(GeneralFunctions::class)]
#[UsesClass(GraphFunctions::class)]
#[UsesClass(ListFunctions::class)]
#[UsesClass(SumAccumulator::class)]
#[UsesClass(TextFunctions::class)]
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
#[UsesClass(ResultColumn::class)]
#[UsesClass(ResultRow::class)]
#[UsesClass(ResultTable::class)]
#[UsesClass(StatusCode::class)]
#[UsesClass(FilterClause::class)]
#[UsesClass(LetClause::class)]
#[UsesClass(MatchClause::class)]
#[UsesClass(OrderByClause::class)]
#[UsesClass(PageClause::class)]
#[UsesClass(Projection::class)]
#[UsesClass(ReturnClause::class)]
#[UsesClass(SortDirection::class)]
#[UsesClass(SortKey::class)]
#[UsesClass(VariableBinding::class)]
#[UsesClass(BinaryExpression::class)]
#[UsesClass(BinaryOperator::class)]
#[UsesClass(CallExpression::class)]
#[UsesClass(CaseBranch::class)]
#[UsesClass(CaseExpression::class)]
#[UsesClass(IndexExpression::class)]
#[UsesClass(ListExpression::class)]
#[UsesClass(LiteralExpression::class)]
#[UsesClass(PropertyExpression::class)]
#[UsesClass(UnaryExpression::class)]
#[UsesClass(UnaryOperator::class)]
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
#[UsesClass(Query::class)]
#[UsesClass(QueryBlock::class)]
#[UsesClass(SetOperator::class)]
#[UsesClass(MatchExecution::class)]
#[UsesClass(QueryExecution::class)]
#[UsesClass(ReturnExecution::class)]
#[UsesClass(RowExecution::class)]
#[UsesClass(SetOperation::class)]
#[Small]
final class BlockExecutionTest extends TestCase
{
    /**
     * @throws GqlException
     */
    public function testRunShowsWhatTheRunOfClausesWasToldToShow(): void
    {
        self::assertSame('[n:INT64] 1', AnsweredQuery::of('RETURN 1 AS n'));
    }

    /**
     * @throws GqlException
     */
    public function testRunShowsEveryNameInScopeWhenTheRunAsksForAllOfThem(): void
    {
        self::assertSame('[n:INT64] 1', AnsweredQuery::of('LET n = 1 RETURN *'));
    }

    /**
     * @throws GqlException
     */
    public function testRunHandsEachClauseWhatTheOneBeforeItProduced(): void
    {
        self::assertSame(
            '[name:STRING] show; total',
            AnsweredQuery::of("MATCH (p:Method) FILTER p.line < 25 FILTER p.visibility = 'public' RETURN p.name AS name"),
        );
    }

    /**
     * @throws GqlException
     */
    #[DataProvider('providerClausesAndWhatTheyDoToTheRows')]
    public function testApplyRunsEveryClauseThatWorksOnRows(string $query, string $expected): void
    {
        self::assertSame($expected, AnsweredQuery::of($query));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function providerClausesAndWhatTheyDoToTheRows(): iterable
    {
        yield 'looking for a shape in the graph' => [
            "MATCH (p:Method WHERE p.name = 'get') RETURN p.name AS name",
            '[name:STRING] get',
        ];

        yield 'naming a computed value' => ['LET n = 1 + 1 RETURN n AS n', '[n:INT64] 2'];

        yield 'keeping only some rows' => [
            'MATCH (p:Method) FILTER p.line > 25 RETURN p.name AS name',
            '[name:STRING] store',
        ];

        yield 'putting the rows in an order' => [
            'MATCH (p:Class) ORDER BY p.name DESC RETURN p.name AS name LIMIT 1',
            '[name:STRING] Store',
        ];

        yield 'keeping a stretch of the rows' => [
            'MATCH (p:Class) ORDER BY p.name LIMIT 1 RETURN p.name AS name',
            '[name:STRING] Controller',
        ];
    }

    /**
     * @throws GqlException
     */
    public function testApplyKeepsNothingWhenAStretchOfNoRowsIsAskedFor(): void
    {
        $execution = new BlockExecution(new ElementGraph([], [], []));

        self::assertSame([], $execution->apply(new PageClause(0, 0), BindingTable::unit())->rows);
    }
}
