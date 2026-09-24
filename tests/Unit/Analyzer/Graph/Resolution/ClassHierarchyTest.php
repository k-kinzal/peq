<?php

declare(strict_types=1);

namespace Tests\Unit\Analyzer\Graph\Resolution;

use App\Analyzer\Graph\Declaration\Modifiers;
use App\Analyzer\Graph\Declaration\SymbolDeclaration;
use App\Analyzer\Graph\Edge\Declaration\ExtendsEdge;
use App\Analyzer\Graph\FileMeta;
use App\Analyzer\Graph\Graph;
use App\Analyzer\Graph\Node\ClassNode;
use App\Analyzer\Graph\Node\GraphInterfaceNode;
use App\Analyzer\Graph\Node\MethodNode;
use App\Analyzer\Graph\NodeId\ClassNodeId;
use App\Analyzer\Graph\NodeId\InterfaceNodeId;
use App\Analyzer\Graph\NodeId\MethodNodeId;
use App\Analyzer\Graph\NodeKind;
use App\Analyzer\Graph\Resolution\ClassHierarchy;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[UsesClass(\App\Analyzer\Graph\AuthoredEdge::class)]
#[UsesClass(Modifiers::class)]
#[UsesClass(SymbolDeclaration::class)]
#[UsesClass(\App\Analyzer\Graph\EdgeIdentity::class)]
#[UsesClass(ExtendsEdge::class)]
#[UsesClass(\App\Analyzer\Graph\Edge\Declaration\ImplementsEdge::class)]
#[UsesClass(\App\Analyzer\Graph\Edge\Inverse\DeclaredInEdge::class)]
#[UsesClass(FileMeta::class)]
#[UsesClass(Graph::class)]
#[UsesClass(ClassNodeId::class)]
#[UsesClass(InterfaceNodeId::class)]
#[UsesClass(MethodNodeId::class)]
#[UsesClass(ClassNode::class)]
#[UsesClass(GraphInterfaceNode::class)]
#[UsesClass(MethodNode::class)]
#[UsesClass(\App\Analyzer\Graph\QualifiedName::class)]
#[UsesClass(\App\Analyzer\Graph\Node\PropertyNode::class)]
#[UsesClass(\App\Analyzer\Graph\NodeId\PropertyNodeId::class)]
#[CoversClass(ClassHierarchy::class)]
#[Small]
final class ClassHierarchyTest extends TestCase
{
    public function testMethodInheritedBodyTakesPrecedenceOverAnInterfaceDeclaration(): void
    {
        $graph = new Graph();
        $base = new ClassNode(ClassNodeId::of('Base'), true);
        $child = new ClassNode(ClassNodeId::of('Child'), true);
        $port = new GraphInterfaceNode(InterfaceNodeId::of('Port'), true);
        $body = new MethodNode(MethodNodeId::of('Base', 'run'), true);
        $contract = new MethodNode(MethodNodeId::of('Port', 'run'), true);
        $meta = new FileMeta('/source.php', 1, 1);
        $graph->addNodes([$base, $child, $port, $body, $contract]);
        $graph->addEdges([
            new ExtendsEdge($child, $base, $meta),
            new \App\Analyzer\Graph\Edge\Declaration\ImplementsEdge($child, $port, $meta),
        ]);

        $hierarchy = new ClassHierarchy($graph);

        self::assertSame($body, $hierarchy->method('Child', 'RUN'));
        self::assertTrue($hierarchy->isSubtype('Child', 'Port'));
        self::assertFalse($hierarchy->isSubtype('Base', 'Port'));
    }

    public function testOwnerDistinguishesMembersFromStandaloneSymbols(): void
    {
        self::assertSame('App\Service', ClassHierarchy::owner(new MethodNode(MethodNodeId::of('App\Service', 'run'))));
        self::assertNull(ClassHierarchy::owner(new ClassNode(ClassNodeId::of('App\Service'))));
    }

    public function testNodeMatchesClassNamesCaseInsensitively(): void
    {
        $graph = new Graph();
        $node = new ClassNode(ClassNodeId::of('App\Service'), true);
        $graph->addNode($node);

        self::assertSame($node, (new ClassHierarchy($graph))->node('app\SERVICE'));
    }

    public function testAncestorsTerminatesAnInvalidInheritanceCycle(): void
    {
        $graph = new Graph();
        $a = new ClassNode(ClassNodeId::of('A'), true);
        $b = new ClassNode(ClassNodeId::of('B'), true);
        $meta = new FileMeta('/source.php', 1, 1);
        $graph->addNodes([$a, $b]);
        $graph->addEdges([new ExtendsEdge($a, $b, $meta), new ExtendsEdge($b, $a, $meta)]);

        self::assertSame(['a', 'b'], (new ClassHierarchy($graph))->ancestors('A'));
    }

    public function testIsSubtypeRejectsUnrelatedNames(): void
    {
        $hierarchy = new ClassHierarchy(new Graph());

        self::assertTrue($hierarchy->isSubtype('Port', 'port'));
        self::assertFalse($hierarchy->isSubtype('Other', 'Port'));
    }

    public function testMemberDoesNotMistakeAPropertyForAMethod(): void
    {
        $graph = new Graph();
        $graph->addNode(new MethodNode(MethodNodeId::of('Service', 'run'), true));

        self::assertNull((new ClassHierarchy($graph))->member('Service', 'run', NodeKind::Property));
    }

    public function testConcreteTypesExcludeInterfacesAndAbstractClasses(): void
    {
        $graph = new Graph();
        $concrete = new ClassNode(ClassNodeId::of('Service'), true);
        $graph->addNodes([
            $concrete,
            new ClassNode(ClassNodeId::of('Base'), true, null, new SymbolDeclaration(modifiers: new Modifiers(abstract: true))),
            new GraphInterfaceNode(InterfaceNodeId::of('Port'), true),
        ]);

        self::assertSame([$concrete], (new ClassHierarchy($graph))->concreteTypes());
    }

    public function testMemberPreservesTheCaseOfPropertiesWhileIgnoringClassNameCase(): void
    {
        $graph = new Graph();
        $lower = new \App\Analyzer\Graph\Node\PropertyNode(\App\Analyzer\Graph\NodeId\PropertyNodeId::of('Service', 'port'), true);
        $upper = new \App\Analyzer\Graph\Node\PropertyNode(\App\Analyzer\Graph\NodeId\PropertyNodeId::of('Service', 'Port'), true);
        $graph->addNodes([$lower, $upper]);
        $hierarchy = new ClassHierarchy($graph);

        self::assertSame($lower, $hierarchy->member('SERVICE', 'port', NodeKind::Property));
        self::assertSame($upper, $hierarchy->member('service', 'Port', NodeKind::Property));
        self::assertNull($hierarchy->member('Service', 'PORT', NodeKind::Property));
    }

    public function testMemberPrefersAResolvedAncestorOverAnUnresolvedChildReference(): void
    {
        $graph = new Graph();
        $parent = new ClassNode(ClassNodeId::of('Base'), true);
        $child = new ClassNode(ClassNodeId::of('Child'), true);
        $resolved = new MethodNode(MethodNodeId::of('Base', 'run'), true);
        $graph->addNodes([$parent, $child, $resolved, new MethodNode(MethodNodeId::of('Child', 'run'))]);
        $graph->addEdge(new ExtendsEdge($child, $parent, new FileMeta('/source.php', 1, 1)));

        self::assertSame($resolved, (new ClassHierarchy($graph))->method('Child', 'run'));
    }

    public function testAncestorsKeepsOtherBranchesAfterADiamondRevisitsAnInterface(): void
    {
        $graph = new Graph();
        $leaf = new GraphInterfaceNode(InterfaceNodeId::of('Leaf'), true);
        $left = new GraphInterfaceNode(InterfaceNodeId::of('Left'), true);
        $right = new GraphInterfaceNode(InterfaceNodeId::of('Right'), true);
        $shared = new GraphInterfaceNode(InterfaceNodeId::of('Shared'), true);
        $extra = new GraphInterfaceNode(InterfaceNodeId::of('Extra'), true);
        $graph->addNodes([$leaf, $left, $right, $shared, $extra]);
        $meta = new FileMeta('/source.php', 1, 1);
        $graph->addEdges([new ExtendsEdge($leaf, $left, $meta), new ExtendsEdge($leaf, $right, $meta), new ExtendsEdge($left, $shared, $meta), new ExtendsEdge($right, $shared, $meta), new ExtendsEdge($right, $extra, $meta)]);

        self::assertSame(['leaf', 'left', 'right', 'shared', 'extra'], (new ClassHierarchy($graph))->ancestors('Leaf'));
    }

    public function testMemberUsesAnInheritedInterfaceContractWhenNoClassBodyExists(): void
    {
        $graph = new Graph();
        $port = new GraphInterfaceNode(InterfaceNodeId::of('Port'), true);
        $specialized = new GraphInterfaceNode(InterfaceNodeId::of('Specialized'), true);
        $method = new MethodNode(MethodNodeId::of('Port', 'run'), true);
        $graph->addNodes([$port, $specialized, $method]);
        $graph->addEdge(new ExtendsEdge($specialized, $port, new FileMeta('/source.php', 1, 1)));

        self::assertSame($method, (new ClassHierarchy($graph))->method('Specialized', 'run'));
    }
}
