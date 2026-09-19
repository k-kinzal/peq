<?php

declare(strict_types=1);

namespace Tests\Unit\Gql\Element;

use App\Analyzer\Graph\Edge;
use App\Analyzer\Graph\Edge\Declaration\ExtendsEdge;
use App\Analyzer\Graph\Edge\Declaration\MethodEdge;
use App\Analyzer\Graph\Edge\Usage\MethodCallEdge;
use App\Analyzer\Graph\FileMeta;
use App\Analyzer\Graph\Graph;
use App\Analyzer\Graph\Node;
use App\Analyzer\Graph\Node\ClassNode;
use App\Analyzer\Graph\Node\MethodNode;
use App\Analyzer\Graph\Node\UnknownNode;
use App\Analyzer\Graph\NodeId\ClassNodeId;
use App\Analyzer\Graph\NodeId\MethodNodeId;
use App\Analyzer\Graph\NodeId\UnknownNodeId;
use App\Analyzer\Graph\NodePrecedence;
use App\Analyzer\Graph\QualifiedName;
use App\Gql\Datum\BooleanDatum;
use App\Gql\Datum\DatumKind;
use App\Gql\Datum\EdgeDatum;
use App\Gql\Datum\IntegerDatum;
use App\Gql\Datum\ListDatum;
use App\Gql\Datum\NodeDatum;
use App\Gql\Datum\StringDatum;
use App\Gql\Element\EdgeProperties;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Tests\Fixture\Gql\SampleGraph;

/**
 * @internal
 */
#[CoversClass(EdgeProperties::class)]
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
#[UsesClass(Edge::class)]
#[UsesClass(Node::class)]
#[UsesClass(BooleanDatum::class)]
#[UsesClass(DatumKind::class)]
#[UsesClass(EdgeDatum::class)]
#[UsesClass(IntegerDatum::class)]
#[UsesClass(ListDatum::class)]
#[UsesClass(NodeDatum::class)]
#[UsesClass(StringDatum::class)]
#[Small]
final class EdgePropertiesTest extends TestCase
{
    #[DataProvider('providerPropertiesOfACall')]
    public function testOfOffersEverythingAQueryCanAskARelation(string $property, string $expected): void
    {
        $written = new MethodCallEdge(SampleGraph::show(), SampleGraph::total(), SampleGraph::at('Http/Controller.php', 22));

        self::assertSame($expected, EdgeProperties::of($written)[$property]->toText());
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function providerPropertiesOfACall(): iterable
    {
        yield 'what kind of relation it is' => ['kind', 'method-call'];

        yield 'which file it is written in' => ['file', '/project/src/Http/Controller.php'];

        yield 'the name of that file' => ['fileName', 'Controller.php'];

        yield 'which line of it' => ['line', '22'];

        yield 'which column of that line' => ['column', '5'];
    }

    public function testAllNamesEveryPropertyARelationCarries(): void
    {
        self::assertSame(['kind', 'file', 'fileName', 'line', 'column'], array_keys(EdgeProperties::all()));
    }

    public function testAllSaysWhatKindOfValueEachPropertyHolds(): void
    {
        self::assertSame('INT64', EdgeProperties::all()['line']);
    }

    #[DataProvider('providerEveryRelationOfTheSampleCodebase')]
    public function testAllCoversEveryPropertyARelationOfTheSampleCodebaseCarries(Edge $edge): void
    {
        self::assertSame(array_keys(EdgeProperties::all()), array_keys(EdgeProperties::of($edge)));
    }

    /**
     * @return iterable<string, array{Edge}>
     */
    public static function providerEveryRelationOfTheSampleCodebase(): iterable
    {
        foreach (SampleGraph::analysed()->authoredEdges() as $place => $edge) {
            yield $place.' '.$edge->kind()->value => [$edge];
        }
    }
}
