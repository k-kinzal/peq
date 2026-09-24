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
use App\Gql\Evaluation\ExpressionEvaluation;
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
use App\Gql\Syntax\Expression\LiteralExpression;
use App\Gql\Syntax\Expression\PropertyExpression;
use App\Gql\Syntax\Expression\VariableExpression;
use App\Gql\Syntax\Pattern\ElementFilter;
use App\Gql\Syntax\Pattern\GraphPattern;
use App\Gql\Syntax\Pattern\LabelPattern;
use App\Gql\Syntax\Pattern\NodePattern;
use App\Gql\Syntax\Pattern\PathPattern;
use App\Gql\Syntax\Query;
use App\Gql\Syntax\QueryBlock;
use App\Reporter\Query\TableWriter;
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
#[CoversClass(TableWriter::class)]
#[UsesClass(ResultColumn::class)]
#[UsesClass(ResultRow::class)]
#[UsesClass(ResultTable::class)]
#[UsesClass(BooleanDatum::class)]
#[UsesClass(DecimalDatum::class)]
#[UsesClass(IntegerDatum::class)]
#[UsesClass(NullDatum::class)]
#[UsesClass(StringDatum::class)]
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
#[UsesClass(DatumKind::class)]
#[UsesClass(DatumOrder::class)]
#[UsesClass(EdgeDatum::class)]
#[UsesClass(ListDatum::class)]
#[UsesClass(NodeDatum::class)]
#[UsesClass(PathDatum::class)]
#[UsesClass(EdgeLabels::class)]
#[UsesClass(EdgeProperties::class)]
#[UsesClass(ElementGraph::class)]
#[UsesClass(NodeLabels::class)]
#[UsesClass(NodeProperties::class)]
#[UsesClass(AggregateDetection::class)]
#[UsesClass(ExpressionEvaluation::class)]
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
#[UsesClass(LiteralExpression::class)]
#[UsesClass(PropertyExpression::class)]
#[UsesClass(VariableExpression::class)]
#[UsesClass(ElementFilter::class)]
#[UsesClass(GraphPattern::class)]
#[UsesClass(LabelPattern::class)]
#[UsesClass(NodePattern::class)]
#[UsesClass(PathPattern::class)]
#[UsesClass(Query::class)]
#[UsesClass(QueryBlock::class)]
#[Small]
final class TableWriterTest extends TestCase
{
    /**
     * @throws GqlException
     */
    public function testReportWritesTheAnswerToAQueryAsATable(): void
    {
        $answered = (new QueryExecution(GraphProjection::of(SampleGraph::analysed()), 10))
            ->query('MATCH (p:Class) RETURN p.name AS name ORDER BY name')
        ;
        $output = new BufferedOutput();

        (new TableWriter())->report($answered, $output);

        self::assertSame(
            <<<'TABLE'
                +---------------+
                | name (STRING) |
                +---------------+
                | Controller    |
                | Invoice       |
                | Kernel        |
                | Store         |
                +---------------+

                TABLE,
            $output->fetch(),
        );
    }

    public function testReportWritesEveryColumnWithWhatItHoldsBesideItsName(): void
    {
        $answered = new ResultTable(
            [new ResultColumn('id', 'STRING'), new ResultColumn('line', 'INT64'), new ResultColumn('deprecated', 'BOOL')],
            [
                new ResultRow([new StringDatum('App\Domain\Invoice::total'), new IntegerDatum(12), new BooleanDatum(true)]),
                new ResultRow([new StringDatum('App\Cache\Store::get'), new IntegerDatum(8), new BooleanDatum(false)]),
            ],
        );
        $output = new BufferedOutput();

        (new TableWriter())->report($answered, $output);

        self::assertSame(
            <<<'TABLE'
                +---------------------------+--------------+-------------------+
                | id (STRING)               | line (INT64) | deprecated (BOOL) |
                +---------------------------+--------------+-------------------+
                | App\Domain\Invoice::total | 12           | TRUE              |
                | App\Cache\Store::get      | 8            | FALSE             |
                +---------------------------+--------------+-------------------+

                TABLE,
            $output->fetch(),
        );
    }

    public function testReportWritesAValueThatLooksLikeConsoleMarkupAsItIs(): void
    {
        $answered = new ResultTable(
            [new ResultColumn('s', 'STRING')],
            [new ResultRow([new StringDatum('<info>x</info>')]), new ResultRow([new StringDatum('</>')])],
        );
        $output = new BufferedOutput();

        (new TableWriter())->report($answered, $output);

        self::assertSame(
            <<<'TABLE'
                +----------------+
                | s (STRING)     |
                +----------------+
                | <info>x</info> |
                | </>            |
                +----------------+

                TABLE,
            $output->fetch(),
        );
    }

    /**
     * @throws GqlException
     */
    public function testReportWritesAHeadingThatLooksLikeConsoleMarkupAsItIs(): void
    {
        $answered = (new QueryExecution(GraphProjection::of(SampleGraph::analysed()), 10))
            ->query("RETURN '<info>x</info>'")
        ;
        $output = new BufferedOutput();

        (new TableWriter())->report($answered, $output);

        self::assertSame(
            <<<'TABLE'
                +---------------------------+
                | '<info>x</info>' (STRING) |
                +---------------------------+
                | <info>x</info>            |
                +---------------------------+

                TABLE,
            $output->fetch(),
        );
    }

    public function testReportWritesAValueEndingInABackslashAsItIs(): void
    {
        $answered = new ResultTable([new ResultColumn('namespace', 'STRING')], [new ResultRow([new StringDatum('App\\')])]);
        $output = new BufferedOutput();

        (new TableWriter())->report($answered, $output);

        self::assertSame(
            <<<'TABLE'
                +--------------------+
                | namespace (STRING) |
                +--------------------+
                | App\              |
                +--------------------+

                TABLE,
            $output->fetch(),
        );
    }

    public function testReportWritesNothingAtAllForAnAnswerThatFoundNothing(): void
    {
        $output = new BufferedOutput();

        (new TableWriter())->report(ResultTable::nothing(), $output);

        self::assertSame('', $output->fetch());
    }

    public function testReportWritesNotEvenTheHeadingsOfAnAnswerWithColumnsButNoRows(): void
    {
        $output = new BufferedOutput();

        (new TableWriter())->report(new ResultTable([new ResultColumn('name', 'NULL')], []), $output);

        self::assertSame('', $output->fetch());
    }

    public function testHeadingSaysWhatTheColumnIsCalledAndWhatItHolds(): void
    {
        self::assertSame('n (INT64)', TableWriter::heading(new ResultColumn('n', 'INT64')));
    }

    public function testHeadingEscapesTheAngleBracketsOfAParameterisedType(): void
    {
        self::assertSame('parameters (LIST\<STRING\>)', TableWriter::heading(new ResultColumn('parameters', 'LIST<STRING>')));
    }

    public function testHeadingEscapesANameTheConsoleWouldOtherwiseReadAsMarkup(): void
    {
        self::assertSame('\<info\>n\</info\> (INT64)', TableWriter::heading(new ResultColumn('<info>n</info>', 'INT64')));
    }

    public function testCellsHoldTheValuesTheWayAResultShowsThem(): void
    {
        self::assertSame(['1'], TableWriter::cells(new ResultRow([new IntegerDatum(1)])));
    }

    public function testCellsWriteEveryKindOfValueAsTheWordOrNumberGqlWritesForIt(): void
    {
        $row = new ResultRow([
            new StringDatum('App\Invoice'),
            new IntegerDatum(12),
            new NullDatum(),
            new BooleanDatum(false),
            new DecimalDatum(175, 1),
        ]);

        self::assertSame(['App\Invoice', '12', 'NULL', 'FALSE', '17.5'], TableWriter::cells($row));
    }

    public function testCellsEscapeTextTheConsoleWouldOtherwiseReadAsMarkup(): void
    {
        self::assertSame(['\<info\>x\</info\>'], TableWriter::cells(new ResultRow([new StringDatum('<info>x</info>')])));
    }

    public function testCellsOfARowOfNoColumnsAreNone(): void
    {
        self::assertSame([], TableWriter::cells(new ResultRow([])));
    }
}
