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
use App\Gql\Argument\ExactArithmetic;
use App\Gql\Argument\NumberArgument;
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
use App\Gql\Invocation\AggregateCatalog;
use App\Gql\Invocation\AverageAccumulator;
use App\Gql\Invocation\SumAccumulator;
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
use App\Gql\StatusCode;
use App\Gql\Syntax\Clause\MatchClause;
use App\Gql\Syntax\Clause\Projection;
use App\Gql\Syntax\Clause\ReturnClause;
use App\Gql\Syntax\Expression\BinaryExpression;
use App\Gql\Syntax\Expression\CallExpression;
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
use App\Reporter\Query\DatumJson;
use App\Reporter\Query\JsonWriter;
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
#[CoversClass(JsonWriter::class)]
#[UsesClass(DatumJson::class)]
#[UsesClass(ResultColumn::class)]
#[UsesClass(ResultRow::class)]
#[UsesClass(ResultTable::class)]
#[UsesClass(StatusCode::class)]
#[UsesClass(BindingRow::class)]
#[UsesClass(BindingTable::class)]
#[UsesClass(BooleanDatum::class)]
#[UsesClass(DatumKind::class)]
#[UsesClass(DecimalDatum::class)]
#[UsesClass(EdgeDatum::class)]
#[UsesClass(IntegerDatum::class)]
#[UsesClass(ListDatum::class)]
#[UsesClass(NodeDatum::class)]
#[UsesClass(NullDatum::class)]
#[UsesClass(StringDatum::class)]
#[UsesClass(AttributeUsage::class)]
#[UsesClass(Modifiers::class)]
#[UsesClass(Parameter::class)]
#[UsesClass(Signature::class)]
#[UsesClass(SymbolDeclaration::class)]
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
#[UsesClass(QualifiedName::class)]
#[UsesClass(EdgeLabels::class)]
#[UsesClass(EdgeProperties::class)]
#[UsesClass(ElementGraph::class)]
#[UsesClass(GraphProjection::class)]
#[UsesClass(NodeLabels::class)]
#[UsesClass(NodeProperties::class)]
#[UsesClass(QueryExecution::class)]
#[UsesClass(GqlException::class)]
#[UsesClass(AuthoredEdge::class)]
#[UsesClass(DeclaredInEdge::class)]
#[UsesClass(UsedByEdge::class)]
#[UsesClass(DatumOrder::class)]
#[UsesClass(PathDatum::class)]
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
#[UsesClass(ElementFilter::class)]
#[UsesClass(GraphPattern::class)]
#[UsesClass(LabelPattern::class)]
#[UsesClass(NodePattern::class)]
#[UsesClass(PathPattern::class)]
#[UsesClass(Query::class)]
#[UsesClass(QueryBlock::class)]
#[UsesClass(ExactArithmetic::class)]
#[UsesClass(NumberArgument::class)]
#[UsesClass(AggregateCatalog::class)]
#[UsesClass(AverageAccumulator::class)]
#[UsesClass(SumAccumulator::class)]
#[UsesClass(CallExpression::class)]
#[Small]
final class JsonWriterTest extends TestCase
{
    public function testReportWritesTheAnswerAsOneJsonDocumentOnOneLine(): void
    {
        $answered = new ResultTable(
            [new ResultColumn('name', 'STRING'), new ResultColumn('line', 'INT64')],
            [new ResultRow([new StringDatum('get'), new IntegerDatum(8)])],
        );
        $output = new BufferedOutput();

        (new JsonWriter())->report($answered, $output);

        self::assertSame(
            <<<'JSON'
                {"status":"00000","condition":"note: successful completion","columns":[{"name":"name","type":"STRING"},{"name":"line","type":"INT64"}],"rows":[["get",8]]}

                JSON,
            $output->fetch(),
        );
    }

    public function testReportWritesTheStatusOfAnAnswerThatFoundNothing(): void
    {
        $output = new BufferedOutput();

        (new JsonWriter())->report(ResultTable::nothing(), $output);

        self::assertSame(
            <<<'JSON'
                {"status":"02000","condition":"note: no data","columns":[],"rows":[]}

                JSON,
            $output->fetch(),
        );
    }

    /**
     * @throws GqlException
     */
    public function testReportWritesASymbolAQueryReturnedWithEverythingAPatternCouldSelectItBy(): void
    {
        $answered = (new QueryExecution(GraphProjection::of(SampleGraph::analysed()), 10))
            ->query("MATCH (p:Class WHERE p.name = 'Kernel') RETURN p")
        ;
        $output = new BufferedOutput();

        (new JsonWriter())->report($answered, $output);

        self::assertSame(
            <<<'JSON'
                {"status":"00000","condition":"note: successful completion","columns":[{"name":"p","type":"NODE"}],"rows":[[{"id":"App\\Http\\Kernel","labels":["Class","ClassLike","Resolved"],"properties":{"id":"App\\Http\\Kernel","kind":"class","resolved":true,"name":"Kernel","namespace":"App\\Http","file":"/project/src/Http/Kernel.php","fileName":"Kernel.php","line":7,"column":5}}]]}

                JSON,
            $output->fetch(),
        );
    }

    /**
     * @throws GqlException
     */
    public function testReportWritesAnAverageAsTheExactNumberItIs(): void
    {
        $answered = (new QueryExecution(GraphProjection::of(SampleGraph::analysed()), 10))
            ->query('MATCH (m:Method) RETURN AVG(m.line) AS mean')
        ;
        $output = new BufferedOutput();

        (new JsonWriter())->report($answered, $output);

        self::assertSame(
            <<<'JSON'
                {"status":"00000","condition":"note: successful completion","columns":[{"name":"mean","type":"DECIMAL"}],"rows":[[17.500000]]}

                JSON,
            $output->fetch(),
        );
    }

    public function testDocumentCarriesTheStatusTheColumnsAndTheRows(): void
    {
        $answered = new ResultTable([new ResultColumn('n', 'INT64')], [new ResultRow([new IntegerDatum(1)])]);

        self::assertSame(
            '{"status":"00000","condition":"note: successful completion","columns":[{"name":"n","type":"INT64"}],"rows":[[1]]}',
            JsonWriter::document($answered),
        );
    }

    public function testDocumentWritesTheRowsOfABindingTableInTheOrderTheyWereBound(): void
    {
        $answered = ResultTable::of(['n'], new BindingTable([
            BindingRow::unit()->with('n', new IntegerDatum(2)),
            BindingRow::unit()->with('n', new IntegerDatum(1)),
        ]));

        self::assertSame(
            '{"status":"00000","condition":"note: successful completion","columns":[{"name":"n","type":"INT64"}],"rows":[[2],[1]]}',
            JsonWriter::document($answered),
        );
    }

    public function testDocumentSaysThatAnAnswerFoundNothingRatherThanFailing(): void
    {
        self::assertSame(
            '{"status":"02000","condition":"note: no data","columns":[],"rows":[]}',
            JsonWriter::document(ResultTable::nothing()),
        );
    }

    public function testDocumentSaysThatAnAnswerWithColumnsAndNoRowsFoundNothing(): void
    {
        self::assertSame(
            '{"status":"02000","condition":"note: no data","columns":[{"name":"name","type":"NULL"}],"rows":[]}',
            JsonWriter::document(new ResultTable([new ResultColumn('name', 'NULL')], [])),
        );
    }

    public function testColumnSaysWhatItIsCalledAndWhatItHolds(): void
    {
        self::assertSame('{"name":"n","type":"INT64"}', JsonWriter::column(new ResultColumn('n', 'INT64')));
    }

    public function testColumnEscapesAHeadingTheWayJsonRequires(): void
    {
        self::assertSame(
            '{"name":"say \"hi\"","type":"LIST<STRING>"}',
            JsonWriter::column(new ResultColumn('say "hi"', 'LIST<STRING>')),
        );
    }

    public function testRowIsTheValuesOfItsColumnsInOrder(): void
    {
        self::assertSame('[1]', JsonWriter::row(new ResultRow([new IntegerDatum(1)])));
    }

    public function testRowWritesEachValueAsTheJsonOfItsKind(): void
    {
        $row = new ResultRow([
            new StringDatum('App\Invoice'),
            new BooleanDatum(true),
            new NullDatum(),
            new DecimalDatum(175, 1),
            new ListDatum([new IntegerDatum(1), new IntegerDatum(2)]),
        ]);

        self::assertSame('["App\\\Invoice",true,null,17.5,[1,2]]', JsonWriter::row($row));
    }

    public function testRowOfNoColumnsIsAnEmptyArray(): void
    {
        self::assertSame('[]', JsonWriter::row(new ResultRow([])));
    }
}
