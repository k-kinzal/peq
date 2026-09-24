<?php

declare(strict_types=1);

namespace Tests\Unit\Action\Inspect;

use App\Action\Inspect\InspectionGraph;
use App\Analyzer\Graph\Direction;
use App\Analyzer\Graph\Edge;
use App\Analyzer\Graph\Edge\Declaration\MethodEdge;
use App\Analyzer\Graph\Edge\Usage\InstantiationEdge;
use App\Analyzer\Graph\Edge\Usage\MethodCallEdge;
use App\Analyzer\Graph\EdgeKind;
use App\Analyzer\Graph\FileMeta;
use App\Analyzer\Graph\Graph;
use App\Analyzer\Graph\Node\ClassNode;
use App\Analyzer\Graph\Node\MethodNode;
use App\Analyzer\Graph\NodeId\ClassNodeId;
use App\Analyzer\Graph\NodeId\MethodNodeId;
use App\Analyzer\NativeAnalyzer\NativeAnalyzer;
use App\Config\InspectFilter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(InspectionGraph::class)]
#[Medium]
final class InspectionGraphTest extends TestCase
{
    public function testOfExcludesPropertyAndTypeRelationsWithoutChangingTheAnalysedGraph(): void
    {
        $graph = (new NativeAnalyzer())->analyze(dirname(__DIR__, 3).'/Fixture/Source/Dip.php');
        $root = $graph->nodeNamed('Tests\Fixture\Source\Dip\Controller::action');
        self::assertNotNull($root);
        $before = count($graph->forwardEdges());

        $calls = InspectionGraph::of($graph, $root, InspectFilter::Calls, Direction::Uses);
        $kinds = array_values(array_unique(array_map(static fn (Edge $edge): string => $edge->kind()->value, $calls->forwardEdges())));
        sort($kinds);

        self::assertSame(['method-call', 'possible-call'], $kinds);
        self::assertSame($before, count($graph->forwardEdges()));
        self::assertSame($graph, InspectionGraph::of($graph, $root, InspectFilter::All, Direction::Uses));
    }

    public function testDependenciesDoNotIncludeAnUnrelatedMethodOfItsClass(): void
    {
        $graph = (new NativeAnalyzer())->analyze(dirname(__DIR__, 3).'/Fixture/Source/Dip.php');
        $root = $graph->nodeNamed('Tests\Fixture\Source\Dip\Controller::action');
        self::assertNotNull($root);

        $depend = InspectionGraph::of($graph, $root, InspectFilter::Depend, Direction::Uses);

        self::assertNull($depend->nodeNamed('DateTimeImmutable'));
        self::assertNotNull($depend->nodeNamed('PDO'));
        self::assertNotNull($depend->nodeNamed('Tests\Fixture\Source\Dip\Service'));
        self::assertNotNull($depend->nodeNamed('Tests\Fixture\Source\Dip\BaseService'));
        self::assertNull($depend->nodeNamed('Tests\Fixture\Source\Dip\OtherService'));
        self::assertNull($depend->nodeNamed('Tests\Fixture\Source\Dip\Port::unused'));
    }

    public function testCallsStartAtItsMethods(): void
    {
        $graph = (new NativeAnalyzer())->analyze(dirname(__DIR__, 3).'/Fixture/Source/Dip.php');
        $root = $graph->nodeNamed('Tests\Fixture\Source\Dip\Controller');
        self::assertNotNull($root);

        $calls = InspectionGraph::of($graph, $root, InspectFilter::Calls, Direction::Uses);
        $entries = array_values(array_filter($calls->edges($root->id()), static fn (Edge $edge): bool => $edge->kind() === EdgeKind::DeclarationMethod));

        self::assertSame([
            'Tests\Fixture\Source\Dip\Controller::__construct',
            'Tests\Fixture\Source\Dip\Controller::action',
            'Tests\Fixture\Source\Dip\Controller::unrelated',
        ], array_map(static fn (Edge $edge): string => $edge->to()->toString(), $entries));
    }

    public function testIsCallExcludesInstantiationDependencies(): void
    {
        $from = new MethodNode(MethodNodeId::of('Controller', 'action'));
        $meta = new FileMeta('/source.php', 1, 1);

        self::assertFalse(InspectionGraph::isCall(new InstantiationEdge($from, new ClassNode(ClassNodeId::of('Service')), $meta)));
        self::assertTrue(InspectionGraph::isCall(new MethodCallEdge($from, new MethodNode(MethodNodeId::of('Service', 'run')), $meta)));
    }

    public function testCallableScopeReadsOnlyCallsInTheChosenDirection(): void
    {
        $graph = new Graph();
        $from = new MethodNode(MethodNodeId::of('Controller', 'action'));
        $to = new MethodNode(MethodNodeId::of('Service', 'run'));
        $graph->addNodes([$from, $to]);
        $graph->addEdge(new MethodCallEdge($from, $to, new FileMeta('/source.php', 1, 1)));

        self::assertSame(['Service::run' => true, 'Controller::action' => true], InspectionGraph::callableScope($graph, $to, Direction::UsedBy));
        self::assertSame(['Service::run' => true], InspectionGraph::callableScope($graph, $to, Direction::Uses));
    }

    public function testIsContainmentRecognizesDeclarationsThatAreNotCalls(): void
    {
        $owner = new ClassNode(ClassNodeId::of('Service'));
        $method = new MethodNode(MethodNodeId::of('Service', 'run'));

        self::assertTrue(InspectionGraph::isContainment(new MethodEdge($owner, $method, new FileMeta('/source.php', 1, 1))));
    }

    public function testProjectOmitsSelfDependenciesAfterAggregation(): void
    {
        $graph = new Graph();
        $root = new ClassNode(ClassNodeId::of('Service'), true);
        $from = new MethodNode(MethodNodeId::of('Service', 'run'));
        $to = new MethodNode(MethodNodeId::of('Service', 'helper'));
        $graph->addNodes([$root, $from, $to]);
        $selected = new Graph();

        InspectionGraph::project($graph, $selected, new MethodCallEdge($from, $to, new FileMeta('/source.php', 1, 1)), $root, false);

        self::assertSame([], $selected->forwardEdges());
    }

    public function testOwnerKeepsTheCallableRootAsTheInspectionContext(): void
    {
        $root = new MethodNode(MethodNodeId::of('Controller', 'action'));
        $helper = new MethodNode(MethodNodeId::of('Controller', 'helper'));

        self::assertSame($root, InspectionGraph::owner(new Graph(), $helper, $root, true));
        self::assertNull(InspectionGraph::owner(new Graph(), null, $root, true));
    }

    public function testCallsKeepOnlyTheEffectiveOverrideAsAClassEntry(): void
    {
        $graph = new Graph();
        $meta = new FileMeta('/source.php', 1, 1);
        $parent = new ClassNode(ClassNodeId::of('Base'), true);
        $child = new ClassNode(ClassNodeId::of('Child'), true);
        $baseMethod = new MethodNode(MethodNodeId::of('Base', 'run'), true);
        $override = new MethodNode(MethodNodeId::of('Child', 'run'), true);
        $graph->addNodes([$parent, $child, $baseMethod, $override]);
        $graph->addEdges([new Edge\Declaration\ExtendsEdge($child, $parent, $meta), new MethodEdge($parent, $baseMethod, $meta), new MethodEdge($child, $override, $meta)]);

        $calls = InspectionGraph::calls($graph, $child);

        self::assertSame(['Child::run'], array_map(static fn (Edge $edge): string => $edge->to()->toString(), $calls->edges($child->id())));
    }

    public function testCallsSeedTheClassMethodsWhenFindingTheirCallers(): void
    {
        $graph = new Graph();
        $meta = new FileMeta('/source.php', 1, 1);
        $owner = new ClassNode(ClassNodeId::of('Service'), true);
        $method = new MethodNode(MethodNodeId::of('Service', 'run'), true);
        $caller = new MethodNode(MethodNodeId::of('Controller', 'action'), true);
        $graph->addNodes([$owner, $method, $caller]);
        $graph->addEdges([new MethodEdge($owner, $method, $meta), new MethodCallEdge($caller, $method, $meta)]);
        $selected = InspectionGraph::calls($graph, $owner, Direction::UsedBy);
        $visited = [];

        (new \App\Reporter\Traversal\DepthFirstTraversal(Direction::UsedBy))->traverse($selected, $owner->id(), static function (\App\Analyzer\Graph\Node $node) use (&$visited): bool {
            $visited[] = $node->id()->toString();

            return true;
        });

        self::assertSame(['Service', 'Service::run', 'Controller::action'], $visited);
    }
}
