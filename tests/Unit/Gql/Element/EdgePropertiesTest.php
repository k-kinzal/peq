<?php

declare(strict_types=1);

namespace Tests\Unit\Gql\Element;

use App\Analyzer\Graph\AuthoredEdge;
use App\Analyzer\Graph\Edge;
use App\Analyzer\Graph\Edge\Declaration\AttributeEdge;
use App\Analyzer\Graph\Edge\Declaration\ExtendsEdge;
use App\Analyzer\Graph\Edge\Declaration\MethodEdge;
use App\Analyzer\Graph\Edge\Usage\MethodCallEdge;
use App\Analyzer\Graph\FileMeta;
use App\Analyzer\Graph\Node\ClassNode;
use App\Analyzer\Graph\Node\MethodNode;
use App\Analyzer\Graph\NodeId\ClassNodeId;
use App\Analyzer\Graph\NodeId\MethodNodeId;
use App\Gql\Datum\Datum;
use App\Gql\Datum\IntegerDatum;
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
#[UsesClass(AttributeEdge::class)]
#[UsesClass(FileMeta::class)]
#[UsesClass(ClassNodeId::class)]
#[UsesClass(MethodNodeId::class)]
#[UsesClass(ClassNode::class)]
#[UsesClass(MethodNode::class)]
#[UsesClass(\App\Analyzer\Graph\QualifiedName::class)]
#[UsesClass(\App\Gql\Datum\ListDatum::class)]
#[CoversClass(EdgeProperties::class)]
#[UsesClass(AuthoredEdge::class)]
#[UsesClass(ExtendsEdge::class)]
#[UsesClass(MethodEdge::class)]
#[UsesClass(MethodCallEdge::class)]
#[UsesClass(IntegerDatum::class)]
#[UsesClass(StringDatum::class)]
#[Small]
final class EdgePropertiesTest extends TestCase
{
    /**
     * @param array<string, Datum> $expected
     */
    #[DataProvider('providerRelationsAndWhatAQueryCanAskThem')]
    public function testOfOffersEverythingAQueryCanAskARelation(Edge $edge, array $expected): void
    {
        self::assertEquals($expected, EdgeProperties::of($edge));
    }

    /**
     * @return iterable<string, array{Edge, array<string, Datum>}>
     */
    public static function providerRelationsAndWhatAQueryCanAskThem(): iterable
    {
        yield 'a call' => [
            new MethodCallEdge(SampleGraph::show(), SampleGraph::total(), new FileMeta('/project/src/Http/Controller.php', 22, 5)),
            [
                'kind' => new StringDatum('method-call'),
                'file' => new StringDatum('/project/src/Http/Controller.php'),
                'fileName' => new StringDatum('Controller.php'),
                'line' => new IntegerDatum(22),
                'column' => new IntegerDatum(5),
                'resolution' => new StringDatum('declared'),
                'declaredTarget' => new StringDatum('App\Domain\Invoice::total'),
            ],
        ];

        yield 'a method a class declares' => [
            new MethodEdge(SampleGraph::invoice(), SampleGraph::total(), new FileMeta('/project/src/Domain/Invoice.php', 12, 5)),
            [
                'kind' => new StringDatum('declaration-method'),
                'file' => new StringDatum('/project/src/Domain/Invoice.php'),
                'fileName' => new StringDatum('Invoice.php'),
                'line' => new IntegerDatum(12),
                'column' => new IntegerDatum(5),
            ],
        ];

        yield 'a class extending another' => [
            new ExtendsEdge(SampleGraph::controller(), SampleGraph::kernel(), new FileMeta('/project/src/Http/Controller.php', 10, 9)),
            [
                'kind' => new StringDatum('declaration-extends'),
                'file' => new StringDatum('/project/src/Http/Controller.php'),
                'fileName' => new StringDatum('Controller.php'),
                'line' => new IntegerDatum(10),
                'column' => new IntegerDatum(9),
            ],
        ];
    }

    public function testAllNamesEveryPropertyARelationCarriesAndWhatKindOfValueItHolds(): void
    {
        self::assertSame(
            ['kind' => 'STRING', 'file' => 'STRING', 'fileName' => 'STRING', 'line' => 'INT64', 'column' => 'INT64', 'offset' => 'INT64', 'resolution' => 'STRING', 'receiverType' => 'STRING', 'declaredTarget' => 'STRING', 'basis' => 'STRING', 'implementationType' => 'STRING', 'expression' => 'STRING', 'arguments' => 'LIST<STRING>', 'parameter' => 'STRING'],
            EdgeProperties::all(),
        );
    }

    public function testEvidenceExposesAttributeArgumentsAndTheirParameterTarget(): void
    {
        $method = new MethodNode(MethodNodeId::of('Service', 'run'));
        $attribute = new ClassNode(ClassNodeId::of('Trace'));
        $edge = new AttributeEdge($method, $attribute, new FileMeta('/source.php', 4, 1, 40), ["'query'"], 'port');

        self::assertSame("['query']", EdgeProperties::evidence($edge)['arguments']->toText());
        self::assertSame('port', EdgeProperties::evidence($edge)['parameter']->toText());
    }
}
