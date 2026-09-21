<?php

declare(strict_types=1);

namespace Tests\Unit\Gql\Element;

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
use App\Gql\Datum\BooleanDatum;
use App\Gql\Datum\EdgeDatum;
use App\Gql\Datum\IntegerDatum;
use App\Gql\Datum\ListDatum;
use App\Gql\Datum\NodeDatum;
use App\Gql\Datum\StringDatum;
use App\Gql\Element\EdgeLabels;
use App\Gql\Element\EdgeProperties;
use App\Gql\Element\ElementGraph;
use App\Gql\Element\GraphProjection;
use App\Gql\Element\NodeLabels;
use App\Gql\Element\NodeProperties;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Tests\Fixture\Gql\SampleGraph;

/**
 * @internal
 */
#[CoversClass(GraphProjection::class)]
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
#[UsesClass(BooleanDatum::class)]
#[UsesClass(EdgeDatum::class)]
#[UsesClass(IntegerDatum::class)]
#[UsesClass(ListDatum::class)]
#[UsesClass(NodeDatum::class)]
#[UsesClass(StringDatum::class)]
#[UsesClass(EdgeLabels::class)]
#[UsesClass(EdgeProperties::class)]
#[UsesClass(ElementGraph::class)]
#[UsesClass(NodeLabels::class)]
#[UsesClass(NodeProperties::class)]
#[Small]
final class GraphProjectionTest extends TestCase
{
    public function testOfTurnsEveryAnalysedSymbolAndRelationIntoOneAPatternCanMatch(): void
    {
        $controller = new ClassNode(ClassNodeId::of('App\Http\Controller'), true, new FileMeta('/project/src/Http/Controller.php', 10, 5));
        $kernel = new ClassNode(ClassNodeId::of('App\Http\Kernel'), true, new FileMeta('/project/src/Http/Kernel.php', 7, 5));
        $graph = new Graph();
        $graph->addNodes([$controller, $kernel]);
        $graph->addEdge(new ExtendsEdge($controller, $kernel, new FileMeta('/project/src/Http/Controller.php', 10, 5)));
        $extends = new EdgeDatum(
            'App\Http\Controller|declaration-extends|App\Http\Kernel',
            ['extends', 'declaration'],
            [
                'kind' => new StringDatum('declaration-extends'),
                'file' => new StringDatum('/project/src/Http/Controller.php'),
                'fileName' => new StringDatum('Controller.php'),
                'line' => new IntegerDatum(10),
                'column' => new IntegerDatum(5),
            ],
            'App\Http\Controller',
            'App\Http\Kernel',
        );

        self::assertEquals(
            new ElementGraph(
                [
                    'App\Http\Controller' => new NodeDatum('App\Http\Controller', ['Class', 'ClassLike', 'Resolved'], [
                        'id' => new StringDatum('App\Http\Controller'),
                        'kind' => new StringDatum('class'),
                        'resolved' => new BooleanDatum(true),
                        'name' => new StringDatum('Controller'),
                        'namespace' => new StringDatum('App\Http'),
                        'file' => new StringDatum('/project/src/Http/Controller.php'),
                        'fileName' => new StringDatum('Controller.php'),
                        'line' => new IntegerDatum(10),
                        'column' => new IntegerDatum(5),
                    ]),
                    'App\Http\Kernel' => new NodeDatum('App\Http\Kernel', ['Class', 'ClassLike', 'Resolved'], [
                        'id' => new StringDatum('App\Http\Kernel'),
                        'kind' => new StringDatum('class'),
                        'resolved' => new BooleanDatum(true),
                        'name' => new StringDatum('Kernel'),
                        'namespace' => new StringDatum('App\Http'),
                        'file' => new StringDatum('/project/src/Http/Kernel.php'),
                        'fileName' => new StringDatum('Kernel.php'),
                        'line' => new IntegerDatum(7),
                        'column' => new IntegerDatum(5),
                    ]),
                ],
                ['App\Http\Controller' => [$extends]],
                ['App\Http\Kernel' => [$extends]],
            ),
            GraphProjection::of($graph),
        );
    }

    public function testOfLeavesOutTheReadingsPeqDerivesRatherThanReads(): void
    {
        $show = new MethodNode(MethodNodeId::of('App\Http\Controller', 'show'), true);
        $total = new MethodNode(MethodNodeId::of('App\Domain\Invoice', 'total'), true);
        $graph = new Graph();
        $graph->addNodes([$show, $total]);
        $graph->addEdge(new MethodCallEdge($show, $total, new FileMeta('/project/src/Http/Controller.php', 22, 5)));

        self::assertSame([], GraphProjection::of($graph)->leaving('App\Domain\Invoice::total'));
    }

    public function testOfTurnsEverySymbolOfTheSampleCodebaseIntoOneAPatternCanMatch(): void
    {
        self::assertSame(
            [
                'App\Http\Controller',
                'App\Http\Kernel',
                'App\Domain\Invoice',
                'App\Cache\Store',
                'App\Http\Controller::show',
                'App\Http\Controller::store',
                'App\Domain\Invoice::total',
                'App\Cache\Store::get',
                'App\Missing',
            ],
            array_column(GraphProjection::of(SampleGraph::analysed())->nodes(), 'id'),
        );
    }

    public function testNodeCarriesTheLabelsAPatternSelectsASymbolByAndWhatAQueryCanAskIt(): void
    {
        self::assertEquals(
            new NodeDatum('App\Domain\Invoice', ['Class', 'ClassLike', 'Resolved'], [
                'id' => new StringDatum('App\Domain\Invoice'),
                'kind' => new StringDatum('class'),
                'resolved' => new BooleanDatum(true),
                'name' => new StringDatum('Invoice'),
                'namespace' => new StringDatum('App\Domain'),
                'file' => new StringDatum('/project/src/Domain/Invoice.php'),
                'fileName' => new StringDatum('Invoice.php'),
                'line' => new IntegerDatum(5),
                'column' => new IntegerDatum(5),
            ]),
            GraphProjection::node(SampleGraph::invoice()),
        );
    }

    public function testNodeCarriesLittleForASymbolAnalysisOnlyEverSawReferredTo(): void
    {
        self::assertEquals(
            new NodeDatum('App\Missing', ['Unknown', 'Unresolved'], [
                'id' => new StringDatum('App\Missing'),
                'kind' => new StringDatum('unknown'),
                'resolved' => new BooleanDatum(false),
                'name' => new StringDatum('Missing'),
                'namespace' => new StringDatum('App'),
            ]),
            GraphProjection::node(new UnknownNode(new UnknownNodeId('App\Missing'))),
        );
    }

    public function testEdgeIsIdentifiedByWhatItJoinsAndHowAndCarriesWhereItIsWritten(): void
    {
        $written = new MethodCallEdge(SampleGraph::show(), SampleGraph::total(), new FileMeta('/project/src/Http/Controller.php', 22, 5));

        self::assertEquals(
            new EdgeDatum(
                'App\Http\Controller::show|method-call|App\Domain\Invoice::total',
                ['methodCall', 'call', 'usage'],
                [
                    'kind' => new StringDatum('method-call'),
                    'file' => new StringDatum('/project/src/Http/Controller.php'),
                    'fileName' => new StringDatum('Controller.php'),
                    'line' => new IntegerDatum(22),
                    'column' => new IntegerDatum(5),
                ],
                'App\Http\Controller::show',
                'App\Domain\Invoice::total',
            ),
            GraphProjection::edge($written),
        );
    }
}
