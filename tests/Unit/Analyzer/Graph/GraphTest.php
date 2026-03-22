<?php

declare(strict_types=1);

namespace Tests\Unit\Analyzer\Graph;

use App\Analyzer\Graph\Edge\DeclarationExtendsEdge;
use App\Analyzer\Graph\Edge\DeclarationMethodEdge;
use App\Analyzer\Graph\Edge\FunctionCallEdge;
use App\Analyzer\Graph\Edge\InstanceofEdge;
use App\Analyzer\Graph\Edge\InstantiationEdge;
use App\Analyzer\Graph\Edge\MethodCallEdge;
use App\Analyzer\Graph\Edge\PropertyAccessEdge;
use App\Analyzer\Graph\EdgeKind;
use App\Analyzer\Graph\FileMeta;
use App\Analyzer\Graph\Graph;
use App\Analyzer\Graph\Node\ClassNode;
use App\Analyzer\Graph\Node\FunctionNode;
use App\Analyzer\Graph\Node\GraphInterfaceNode;
use App\Analyzer\Graph\Node\MethodNode;
use App\Analyzer\Graph\Node\PropertyNode;
use App\Analyzer\Graph\Node\UnknownNode;
use App\Analyzer\Graph\NodeId\ClassNodeId;
use App\Analyzer\Graph\NodeId\FunctionNodeId;
use App\Analyzer\Graph\NodeId\InterfaceNodeId;
use App\Analyzer\Graph\NodeId\MethodNodeId;
use App\Analyzer\Graph\NodeId\PropertyNodeId;
use App\Analyzer\Graph\NodeId\UnknownNodeId;
use App\Analyzer\Graph\NodeKind;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
final class GraphTest extends TestCase
{
    #[Test]
    public function testAddNodeAddsNodeToGraph(): void
    {
        $graph = new Graph();
        $node = new ClassNode(id: new ClassNodeId('App\Service', 'MyClass'));

        $graph->addNode($node);

        self::assertSame($node, $graph->node($node->id));
    }

    #[Test]
    public function testAddNodeReplacesUnknownNode(): void
    {
        $graph = new Graph();
        $nodeId = new ClassNodeId('App\Service', 'MyClass');

        $unknownNode = new UnknownNode(id: new UnknownNodeId('App\Service\MyClass'));
        $graph->addNode($unknownNode);

        $concreteNode = new ClassNode(id: new ClassNodeId('App\Service', 'MyClass'));
        $graph->addNode($concreteNode);

        $result = $graph->node($nodeId);
        self::assertSame($concreteNode, $result);
        self::assertSame(NodeKind::Klass, $result->kind());
    }

    #[Test]
    public function testAddNodeThrowsExceptionWhenNodeAlreadyExists(): void
    {
        $graph = new Graph();
        $node = new ClassNode(id: new ClassNodeId('App\Service', 'MyClass'));
        $graph->addNode($node);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Node already exists.');

        $graph->addNode($node);
    }

    #[Test]
    public function testAddNodesAddsMultipleNodes(): void
    {
        $graph = new Graph();
        $node1 = new ClassNode(id: new ClassNodeId('App\Service', 'ClassA'));
        $node2 = new ClassNode(id: new ClassNodeId('App\Service', 'ClassB'));
        $node3 = new GraphInterfaceNode(id: new InterfaceNodeId('App\Service', 'InterfaceA'));

        $nodes = [$node1, $node2, $node3];

        $graph->addNodes($nodes);

        self::assertNotNull($graph->node($node1->id));
        self::assertNotNull($graph->node($node2->id));
        self::assertNotNull($graph->node($node3->id));
    }

    #[Test]
    public function testAddEdgeCreatesEdge(): void
    {
        $graph = new Graph();
        $fromId = new ClassNodeId('App\Service', 'ClassA');
        $toId = new ClassNodeId('App\Service', 'ClassB');
        $edge = new DeclarationExtendsEdge(
            from: new ClassNode(id: $fromId, resolved: true, meta: new FileMeta('', 1, 1)),
            to: new ClassNode(id: $toId, resolved: true, meta: new FileMeta('', 1, 1)),
            meta: new FileMeta('/path/to/file.php', 10, 5)
        );

        $graph->addEdge($edge);

        $result = $graph->edge($fromId, $toId);
        self::assertNotNull($result);
        self::assertSame(EdgeKind::DeclarationExtends, $result->kind());
    }

    #[Test]
    public function testAddEdgeCreatesUnknownNodesForMissingNodes(): void
    {
        $graph = new Graph();
        $fromId = new MethodNodeId('App\Service', 'ClassA', 'method');
        $toId = new MethodNodeId('App\Service', 'ClassB', 'method');
        $edge = new MethodCallEdge(
            from: new MethodNode(id: $fromId, resolved: true, meta: new FileMeta('', 1, 1)),
            to: new MethodNode(id: $toId, resolved: true, meta: new FileMeta('', 1, 1)),
            meta: new FileMeta('/path/to/file.php', 15, 10)
        );

        $graph->addEdge($edge);

        $fromNode = $graph->node($fromId);
        $toNode = $graph->node($toId);
        self::assertNotNull($fromNode);
        self::assertNotNull($toNode);
        self::assertSame(NodeKind::Unknown, $fromNode->kind());
        self::assertSame(NodeKind::Unknown, $toNode->kind());
        self::assertSame(NodeKind::Unknown, $toNode->kind());
    }

    #[Test]
    public function testAddEdgePreservesUnknownNodeId(): void
    {
        $graph = new Graph();
        $fromId = new UnknownNodeId('App\UnknownClass');
        $toId = new UnknownNodeId('App\AnotherUnknown');

        $edge = new StubEdge(
            from: new UnknownNode($fromId),
            to: new UnknownNode($toId),
            meta: new FileMeta('/path/to/file.php', 1, 1)
        );

        $graph->addEdge($edge);

        $fromNode = $graph->node($fromId);
        $toNode = $graph->node($toId);

        self::assertNotNull($fromNode);
        self::assertNotNull($toNode);
        self::assertSame(NodeKind::Unknown, $fromNode->kind());
        self::assertSame(NodeKind::Unknown, $toNode->kind());

        self::assertSame($fromId, $fromNode->id());
        self::assertSame($toId, $toNode->id());
    }

    #[Test]
    public function testAddEdgeCreatesBidirectionalEdge(): void
    {
        $graph = new Graph();
        $fromId = new MethodNodeId('App\Service', 'ClassA', 'method');
        $toId = new MethodNodeId('App\Service', 'ClassB', 'method');
        $edge = new MethodCallEdge(
            from: new MethodNode(id: $fromId, resolved: true, meta: new FileMeta('', 1, 1)),
            to: new MethodNode(id: $toId, resolved: true, meta: new FileMeta('', 1, 1)),
            meta: new FileMeta('/path/to/file.php', 20, 15)
        );

        $graph->addEdge($edge);

        $forwardEdge = $graph->edge($fromId, $toId);
        $reverseEdge = $graph->edge($toId, $fromId);

        self::assertNotNull($forwardEdge);
        self::assertNotNull($reverseEdge);
        self::assertSame(EdgeKind::MethodCall, $forwardEdge->kind());
        self::assertSame(EdgeKind::UsedBy, $reverseEdge->kind());
    }

    #[Test]
    public function testAddEdgesAddsMultipleEdges(): void
    {
        $graph = new Graph();
        $meta = new FileMeta('/path/to/file.php', 25, 20);
        $fromId1 = new MethodNodeId('App\Service', 'ClassA', 'method');
        $toId1 = new MethodNodeId('App\Service', 'ClassB', 'method');
        $fromId2 = new MethodNodeId('App\Service', 'ClassB', 'method');
        $toId2 = new PropertyNodeId('App\Service', 'ClassC', 'prop');

        $edges = [
            new MethodCallEdge(from: new MethodNode(id: $fromId1, resolved: true, meta: new FileMeta('', 1, 1)), to: new MethodNode(id: $toId1, resolved: true, meta: new FileMeta('', 1, 1)), meta: $meta),
            new PropertyAccessEdge(from: new MethodNode(id: $fromId2, resolved: true, meta: new FileMeta('', 1, 1)), to: new PropertyNode(id: $toId2, resolved: true, meta: new FileMeta('', 1, 1)), meta: $meta),
        ];

        $graph->addEdges($edges);

        self::assertNotNull($graph->edge($fromId1, $toId1));
        self::assertNotNull($graph->edge($fromId2, $toId2));
    }

    #[Test]
    public function testNodeReturnsNodeWhenExists(): void
    {
        $graph = new Graph();
        $node = new ClassNode(id: new ClassNodeId('App\Service', 'MyClass'));
        $graph->addNode($node);

        $result = $graph->node($node->id);

        self::assertSame($node, $result);
    }

    #[Test]
    public function testNodeReturnsNullWhenNotExists(): void
    {
        $graph = new Graph();
        $nodeId = new ClassNodeId('App\Service', 'NonExistent');

        $result = $graph->node($nodeId);

        self::assertNull($result);
    }

    #[Test]
    public function testEdgeReturnsEdgeWhenExists(): void
    {
        $graph = new Graph();
        $fromId = new MethodNodeId('App\Service', 'ClassA', 'method');
        $toId = new MethodNodeId('App\Service', 'ClassB', 'method');
        $edge = new MethodCallEdge(
            from: new MethodNode(id: $fromId, resolved: true, meta: new FileMeta('', 1, 1)),
            to: new MethodNode(id: $toId, resolved: true, meta: new FileMeta('', 1, 1)),
            meta: new FileMeta('/path/to/file.php', 30, 25)
        );
        $graph->addEdge($edge);

        $result = $graph->edge($fromId, $toId);

        self::assertNotNull($result);
        self::assertSame($fromId, $result->from());
        self::assertSame($toId, $result->to());
        self::assertSame(EdgeKind::MethodCall, $result->kind());
    }

    #[Test]
    public function testEdgeReturnsNullWhenNotExists(): void
    {
        $graph = new Graph();
        $fromId = new ClassNodeId('App\Service', 'ClassA');
        $toId = new ClassNodeId('App\Service', 'NonExistent');

        $result = $graph->edge($fromId, $toId);

        self::assertNull($result);
    }

    #[Test]
    public function testEdgesReturnsEdgesFromNode(): void
    {
        $graph = new Graph();
        $fromId = new MethodNodeId('App\Service', 'ClassA', 'method');
        $toId1 = new MethodNodeId('App\Service', 'ClassB', 'method');
        $toId2 = new PropertyNodeId('App\Service', 'ClassC', 'prop');
        $meta = new FileMeta('/path/to/file.php', 35, 30);

        $graph->addEdge(new MethodCallEdge(from: new MethodNode(id: $fromId, resolved: true, meta: new FileMeta('', 1, 1)), to: new MethodNode(id: $toId1, resolved: true, meta: new FileMeta('', 1, 1)), meta: $meta));
        $graph->addEdge(new PropertyAccessEdge(from: new MethodNode(id: $fromId, resolved: true, meta: new FileMeta('', 1, 1)), to: new PropertyNode(id: $toId2, resolved: true, meta: new FileMeta('', 1, 1)), meta: $meta));

        $result = $graph->edges($fromId);

        self::assertCount(2, $result);
    }

    #[Test]
    public function testEdgesReturnsEmptyArrayWhenNoEdges(): void
    {
        $graph = new Graph();
        $nodeId = new ClassNodeId('App\Service', 'ClassA');

        $result = $graph->edges($nodeId);

        self::assertEmpty($result);
    }

    #[Test]
    public function testMergeWithEmptyGraphsReturnsEmptyGraph(): void
    {
        $graph1 = new Graph();
        $graph2 = new Graph();

        $merged = $graph1->merge($graph2);

        self::assertCount(0, $merged->nodes());
    }

    #[Test]
    public function testMergeWithEmptyGraphReturnsCopy(): void
    {
        $graph1 = new Graph();
        $node = new ClassNode(id: new ClassNodeId('App\Service', 'ClassA'));
        $graph1->addNode($node);

        $graph2 = new Graph();
        $merged = $graph1->merge($graph2);

        self::assertCount(1, $merged->nodes());
        self::assertSame(NodeKind::Klass, $merged->node($node->id)?->kind());
    }

    #[Test]
    public function testMergeCombinesDistinctNodes(): void
    {
        $graph1 = new Graph();
        $node1 = new ClassNode(id: new ClassNodeId('App\Service', 'ClassA'));
        $graph1->addNode($node1);

        $graph2 = new Graph();
        $node2 = new ClassNode(id: new ClassNodeId('App\Service', 'ClassB'));
        $graph2->addNode($node2);

        $merged = $graph1->merge($graph2);

        self::assertCount(2, $merged->nodes());
        self::assertNotNull($merged->node($node1->id));
        self::assertNotNull($merged->node($node2->id));
    }

    #[Test]
    public function testMergeCombinesDistinctEdges(): void
    {
        $graph1 = new Graph();
        $fromId1 = new MethodNodeId('App\Service', 'ClassA', 'method');
        $toId1 = new MethodNodeId('App\Service', 'ClassB', 'method');
        $meta = new FileMeta('/path/to/file.php', 10, 5);
        $graph1->addEdge(new MethodCallEdge(from: new MethodNode(id: $fromId1, resolved: true, meta: new FileMeta('', 1, 1)), to: new MethodNode(id: $toId1, resolved: true, meta: new FileMeta('', 1, 1)), meta: $meta));

        $graph2 = new Graph();
        $fromId2 = new MethodNodeId('App\Service', 'ClassB', 'method');
        $toId2 = new PropertyNodeId('App\Service', 'ClassC', 'prop');
        $graph2->addEdge(new PropertyAccessEdge(from: new MethodNode(id: $fromId2, resolved: true, meta: new FileMeta('', 1, 1)), to: new PropertyNode(id: $toId2, resolved: true, meta: new FileMeta('', 1, 1)), meta: $meta));

        $merged = $graph1->merge($graph2);

        self::assertNotNull($merged->edge($fromId1, $toId1));
        self::assertNotNull($merged->edge($fromId2, $toId2));
        self::assertCount(3, $merged->nodes());
    }

    #[Test]
    public function testMergeReplacesUnknownNodeWithConcreteNode(): void
    {
        $graph1 = new Graph();
        $nodeId = new ClassNodeId('App\Service', 'ClassA');
        $unknownNode = new UnknownNode(id: new UnknownNodeId('App\Service\ClassA'));
        $graph1->addNode($unknownNode);

        $graph2 = new Graph();
        $concreteNode = new ClassNode(id: new ClassNodeId('App\Service', 'ClassA'));
        $graph2->addNode($concreteNode);

        $merged = $graph1->merge($graph2);

        $node = $merged->node($nodeId);
        self::assertNotNull($node);
        self::assertSame(NodeKind::Klass, $node->kind());
    }

    #[Test]
    public function testMergeDoesNotModifyOriginalGraphs(): void
    {
        $graph1 = new Graph();
        $node1 = new ClassNode(id: new ClassNodeId('App\Service', 'ClassA'));
        $graph1->addNode($node1);

        $graph2 = new Graph();
        $node2 = new ClassNode(id: new ClassNodeId('App\Service', 'ClassB'));
        $graph2->addNode($node2);

        $merged = $graph1->merge($graph2);

        self::assertCount(1, $graph1->nodes());
        self::assertCount(1, $graph2->nodes());
        self::assertNotNull($graph1->node($node1->id));
        self::assertNull($graph1->node($node2->id));
        self::assertNull($graph2->node($node1->id));
        self::assertNotNull($graph2->node($node2->id));

        self::assertCount(2, $merged->nodes());
    }

    #[Test]
    public function testMergeHandlesBidirectionalEdgesCorrectly(): void
    {
        $graph1 = new Graph();
        $fromId = new MethodNodeId('App\Service', 'ClassA', 'method');
        $toId = new MethodNodeId('App\Service', 'ClassB', 'method');
        $meta = new FileMeta('/path/to/file.php', 15, 10);
        $graph1->addEdge(new MethodCallEdge(from: new MethodNode(id: $fromId, resolved: true, meta: new FileMeta('', 1, 1)), to: new MethodNode(id: $toId, resolved: true, meta: new FileMeta('', 1, 1)), meta: $meta));

        $graph2 = new Graph();
        $merged = $graph1->merge($graph2);

        $forwardEdge = $merged->edge($fromId, $toId);
        self::assertNotNull($forwardEdge);
        self::assertSame(EdgeKind::MethodCall, $forwardEdge->kind());

        $reverseEdge = $merged->edge($toId, $fromId);
        self::assertNotNull($reverseEdge);
        self::assertSame(EdgeKind::UsedBy, $reverseEdge->kind());
    }

    #[Test]
    public function testMergeWithSameEdgeDoesNotDuplicate(): void
    {
        $meta = new FileMeta('/path/to/file.php', 20, 15);
        $fromId = new MethodNodeId('App\Service', 'ClassA', 'method');
        $toId = new MethodNodeId('App\Service', 'ClassB', 'method');

        $graph1 = new Graph();
        $graph1->addEdge(new MethodCallEdge(from: new MethodNode(id: $fromId, resolved: true, meta: new FileMeta('', 1, 1)), to: new MethodNode(id: $toId, resolved: true, meta: new FileMeta('', 1, 1)), meta: $meta));

        $graph2 = new Graph();
        $graph2->addEdge(new MethodCallEdge(from: new MethodNode(id: $fromId, resolved: true, meta: new FileMeta('', 1, 1)), to: new MethodNode(id: $toId, resolved: true, meta: new FileMeta('', 1, 1)), meta: $meta));

        $merged = $graph1->merge($graph2);

        self::assertCount(2, $merged->nodes());
        $edges = $merged->edges($fromId);
        self::assertCount(1, $edges);
        self::assertSame(EdgeKind::MethodCall, $edges[0]->kind());
    }

    #[Test]
    public function testAddEdgeDeduplicatesIdenticalEdges(): void
    {
        $graph = new Graph();
        $fromId = new MethodNodeId('App\Service', 'ClassA', 'method');
        $toId = new MethodNodeId('App\Service', 'ClassB', 'method');
        $meta = new FileMeta('/path/to/file.php', 10, 5);

        $edge1 = new MethodCallEdge(from: new MethodNode(id: $fromId, resolved: true, meta: new FileMeta('', 1, 1)), to: new MethodNode(id: $toId, resolved: true, meta: new FileMeta('', 1, 1)), meta: $meta);
        $edge2 = new MethodCallEdge(from: new MethodNode(id: $fromId, resolved: true, meta: new FileMeta('', 1, 1)), to: new MethodNode(id: $toId, resolved: true, meta: new FileMeta('', 1, 1)), meta: $meta);

        $graph->addEdge($edge1);
        $graph->addEdge($edge2);

        self::assertCount(1, $graph->edges($fromId));
        self::assertCount(1, $graph->edges($toId));
    }

    #[Test]
    public function testAddEdgePreservesDifferentKindsBetweenSameNodes(): void
    {
        $graph = new Graph();
        $fromId = new MethodNodeId('App\Service', 'ClassA', 'method');
        $toId = new ClassNodeId('App\Service', 'ClassB');
        $meta = new FileMeta('/path/to/file.php', 10, 5);

        $graph->addEdge(new InstantiationEdge(from: new MethodNode(id: $fromId, resolved: true, meta: new FileMeta('', 1, 1)), to: new ClassNode(id: $toId, resolved: true, meta: new FileMeta('', 1, 1)), meta: $meta));
        $graph->addEdge(new InstanceofEdge(from: new MethodNode(id: $fromId, resolved: true, meta: new FileMeta('', 1, 1)), to: new ClassNode(id: $toId, resolved: true, meta: new FileMeta('', 1, 1)), meta: $meta));

        self::assertCount(2, $graph->edges($fromId));
    }

    #[Test]
    public function testMergeFiltersDeclaredInEdges(): void
    {
        $graph1 = new Graph();
        $from = new MethodNode(new MethodNodeId('App', 'MyClass', 'method'));
        $to = new ClassNode(new ClassNodeId('App', 'MyClass'));
        $declarationEdge = new DeclarationMethodEdge($to, $from, new FileMeta('/path/to/file.php', 10, 5));
        $graph1->addEdge($declarationEdge);

        self::assertNotNull($graph1->edge($to->id, $from->id));
        self::assertNotNull($graph1->edge($from->id, $to->id));

        $graph2 = new Graph();
        $merged = $graph1->merge($graph2);

        self::assertNotNull($merged->edge($to->id, $from->id));
        self::assertNotNull($merged->edge($from->id, $to->id));
    }

    #[Test]
    public function testMergeFiltersUsedByEdges(): void
    {
        $graph1 = new Graph();
        $from = new FunctionNode(new FunctionNodeId('App', 'caller'));
        $to = new FunctionNode(new FunctionNodeId('App', 'callee'));
        $callEdge = new FunctionCallEdge($from, $to, new FileMeta('/path/to/file.php', 10, 5));
        $graph1->addEdge($callEdge);

        self::assertNotNull($graph1->edge($from->id, $to->id));
        self::assertNotNull($graph1->edge($to->id, $from->id));

        $graph2 = new Graph();
        $merged = $graph1->merge($graph2);

        self::assertNotNull($merged->edge($from->id, $to->id));
        self::assertNotNull($merged->edge($to->id, $from->id));
    }

    #[Test]
    public function testEdgeWithKindReturnsSpecificEdge(): void
    {
        $graph = new Graph();
        $fromId = new MethodNodeId('App\Service', 'ClassA', 'method');
        $toId = new ClassNodeId('App\Service', 'ClassB');
        $meta = new FileMeta('/path/to/file.php', 10, 5);

        $graph->addEdge(new InstantiationEdge(from: new MethodNode(id: $fromId, resolved: true, meta: new FileMeta('', 1, 1)), to: new ClassNode(id: $toId, resolved: true, meta: new FileMeta('', 1, 1)), meta: $meta));
        $graph->addEdge(new InstanceofEdge(from: new MethodNode(id: $fromId, resolved: true, meta: new FileMeta('', 1, 1)), to: new ClassNode(id: $toId, resolved: true, meta: new FileMeta('', 1, 1)), meta: $meta));

        $instantiation = $graph->edge($fromId, $toId, EdgeKind::Instantiation);
        $instanceof = $graph->edge($fromId, $toId, EdgeKind::Instanceof);
        $nonExistent = $graph->edge($fromId, $toId, EdgeKind::FunctionCall);

        self::assertNotNull($instantiation);
        self::assertSame(EdgeKind::Instantiation, $instantiation->kind());
        self::assertNotNull($instanceof);
        self::assertSame(EdgeKind::Instanceof, $instanceof->kind());
        self::assertNull($nonExistent);
    }

    #[Test]
    public function testEdgeWithoutKindReturnsFirstMatch(): void
    {
        $graph = new Graph();
        $fromId = new MethodNodeId('App\Service', 'ClassA', 'method');
        $toId = new ClassNodeId('App\Service', 'ClassB');
        $meta = new FileMeta('/path/to/file.php', 10, 5);

        $graph->addEdge(new InstantiationEdge(from: new MethodNode(id: $fromId, resolved: true, meta: new FileMeta('', 1, 1)), to: new ClassNode(id: $toId, resolved: true, meta: new FileMeta('', 1, 1)), meta: $meta));
        $graph->addEdge(new InstanceofEdge(from: new MethodNode(id: $fromId, resolved: true, meta: new FileMeta('', 1, 1)), to: new ClassNode(id: $toId, resolved: true, meta: new FileMeta('', 1, 1)), meta: $meta));

        $result = $graph->edge($fromId, $toId);

        self::assertNotNull($result);
        self::assertSame(EdgeKind::Instantiation, $result->kind());
    }

    #[Test]
    public function testMergeThrowsOnDuplicateConcreteNode(): void
    {
        $graph1 = new Graph();
        $graph1->addNode(new ClassNode(id: new ClassNodeId('App\Service', 'ClassA')));

        $graph2 = new Graph();
        $graph2->addNode(new ClassNode(id: new ClassNodeId('App\Service', 'ClassA')));

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Node already exists.');

        $graph1->merge($graph2);
    }
}
