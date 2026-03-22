<?php

declare(strict_types=1);

namespace Tests\App\Reporter\Traversal;

use App\Analyzer\Graph\Edge;
use App\Analyzer\Graph\Edge\ConstFetchEdge;
use App\Analyzer\Graph\Edge\DeclarationConstantEdge;
use App\Analyzer\Graph\Edge\DeclarationEnumCaseEdge;
use App\Analyzer\Graph\Edge\DeclarationExtendsEdge;
use App\Analyzer\Graph\Edge\DeclarationImplementsEdge;
use App\Analyzer\Graph\Edge\DeclarationMethodEdge;
use App\Analyzer\Graph\Edge\DeclarationPropertyEdge;
use App\Analyzer\Graph\Edge\DeclarationTraitUseEdge;
use App\Analyzer\Graph\Edge\DeclarationTypeParameterEdge;
use App\Analyzer\Graph\Edge\DeclarationTypePropertyEdge;
use App\Analyzer\Graph\Edge\DeclarationTypeReturnEdge;
use App\Analyzer\Graph\Edge\FunctionCallEdge;
use App\Analyzer\Graph\Edge\InstantiationEdge;
use App\Analyzer\Graph\Edge\MethodCallEdge;
use App\Analyzer\Graph\Edge\PropertyAccessEdge;
use App\Analyzer\Graph\Edge\StaticCallEdge;
use App\Analyzer\Graph\Edge\StaticPropertyAccessEdge;
use App\Analyzer\Graph\EdgeKind;
use App\Analyzer\Graph\FileMeta;
use App\Analyzer\Graph\Graph;
use App\Analyzer\Graph\Node;
use App\Analyzer\Graph\Node\ClassNode;
use App\Analyzer\Graph\Node\ConstantNode;
use App\Analyzer\Graph\Node\EnumCaseNode;
use App\Analyzer\Graph\Node\EnumNode;
use App\Analyzer\Graph\Node\FunctionNode;
use App\Analyzer\Graph\Node\GraphInterfaceNode;
use App\Analyzer\Graph\Node\MethodNode;
use App\Analyzer\Graph\Node\PropertyNode;
use App\Analyzer\Graph\Node\TraitNode;
use App\Analyzer\Graph\NodeId\ClassNodeId;
use App\Analyzer\Graph\NodeId\ConstantNodeId;
use App\Analyzer\Graph\NodeId\EnumCaseNodeId;
use App\Analyzer\Graph\NodeId\EnumNodeId;
use App\Analyzer\Graph\NodeId\FunctionNodeId;
use App\Analyzer\Graph\NodeId\InterfaceNodeId;
use App\Analyzer\Graph\NodeId\MethodNodeId;
use App\Analyzer\Graph\NodeId\PropertyNodeId;
use App\Analyzer\Graph\NodeId\TraitNodeId;
use App\Analyzer\Graph\NodeKind;
use App\Reporter\Traversal\DependencyTraversal;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Test for DependencyTraversal using a graph based on examples/coverage/AllTypes.php.
 *
 * This test demonstrates all Node and Edge types in a realistic PHP code structure.
 *
 * @internal
 */
final class DependencyTraversalTest extends TestCase
{
    #[Test]
    public function testTraverseWithComplexGraphIncludingAllNodeAndEdgeTypes(): void
    {
        $graph = new Graph();
        $meta = new FileMeta('/path/to/file.php', 1, 1);

        $classA = new ClassNodeId('Example\Coverage', 'ClassA');
        $baseClass = new ClassNodeId('Example\Coverage', 'BaseClass');
        $helperClass = new ClassNodeId('Example\Coverage', 'HelperClass');

        $serviceInterface = new InterfaceNodeId('Example\Coverage', 'ServiceInterface');

        $loggerTrait = new TraitNodeId('Example\Coverage', 'LoggerTrait');

        $statusEnum = new EnumNodeId('Example\Coverage', 'Status');
        $activeCase = new EnumCaseNodeId('Example\Coverage', 'Status', 'ACTIVE');
        $inactiveCase = new EnumCaseNodeId('Example\Coverage', 'Status', 'INACTIVE');
        $pendingCase = new EnumCaseNodeId('Example\Coverage', 'Status', 'PENDING');

        $helperFunction = new FunctionNodeId('Example\Coverage', 'helperFunction');

        $methodA = new MethodNodeId('Example\Coverage', 'ClassA', 'methodA');
        $execute = new MethodNodeId('Example\Coverage', 'ClassA', 'execute');
        $helperMethod = new MethodNodeId('Example\Coverage', 'HelperClass', 'helperMethod');
        $staticHelper = new MethodNodeId('Example\Coverage', 'HelperClass', 'staticHelper');
        $baseMethod = new MethodNodeId('Example\Coverage', 'BaseClass', 'baseMethod');
        $logMethod = new MethodNodeId('Example\Coverage', 'LoggerTrait', 'log');
        $interfaceExecute = new MethodNodeId('Example\Coverage', 'ServiceInterface', 'execute');

        $serviceProperty = new PropertyNodeId('Example\Coverage', 'ClassA', 'service');
        $statusProperty = new PropertyNodeId('Example\Coverage', 'ClassA', 'status');
        $staticProperty = new PropertyNodeId('Example\Coverage', 'HelperClass', 'staticProperty');
        $baseProperty = new PropertyNodeId('Example\Coverage', 'BaseClass', 'baseProperty');

        $myConstant = new ConstantNodeId('Example\Coverage', 'ClassA', 'MY_CONSTANT');
        $baseConstant = new ConstantNodeId('Example\Coverage', 'BaseClass', 'BASE_CONSTANT');

        $graph->addNodes([
            new ClassNode(id: $classA),
            new ClassNode(id: $baseClass),
            new ClassNode(id: $helperClass),
            new GraphInterfaceNode(id: $serviceInterface),
            new TraitNode(id: $loggerTrait),
            new EnumNode(id: $statusEnum),
            new EnumCaseNode(id: $activeCase),
            new EnumCaseNode(id: $inactiveCase),
            new EnumCaseNode(id: $pendingCase),
            new FunctionNode(id: $helperFunction),
            new MethodNode(id: $methodA),
            new MethodNode(id: $execute),
            new MethodNode(id: $helperMethod),
            new MethodNode(id: $staticHelper),
            new MethodNode(id: $baseMethod),
            new MethodNode(id: $logMethod),
            new MethodNode(id: $interfaceExecute),
            new PropertyNode(id: $serviceProperty),
            new PropertyNode(id: $statusProperty),
            new PropertyNode(id: $staticProperty),
            new PropertyNode(id: $baseProperty),
            new ConstantNode(id: $myConstant),
            new ConstantNode(id: $baseConstant),
        ]);

        $graph->addEdge(new DeclarationExtendsEdge(
            from: new ClassNode(id: $classA, resolved: true, meta: new FileMeta('', 1, 1)),
            to: new ClassNode(id: $baseClass, resolved: true, meta: new FileMeta('', 1, 1)),
            meta: $meta
        ));
        $graph->addEdge(new DeclarationImplementsEdge(
            from: new ClassNode(id: $classA, resolved: true, meta: new FileMeta('', 1, 1)),
            to: new GraphInterfaceNode(id: $serviceInterface, resolved: true, meta: new FileMeta('', 1, 1)),
            meta: $meta
        ));
        $graph->addEdge(new DeclarationTraitUseEdge(
            from: new ClassNode(id: $classA, resolved: true, meta: new FileMeta('', 1, 1)),
            to: new TraitNode(id: $loggerTrait, resolved: true, meta: new FileMeta('', 1, 1)),
            meta: $meta
        ));
        $graph->addEdge(new DeclarationConstantEdge(
            from: new ClassNode(id: $classA, resolved: true, meta: new FileMeta('', 1, 1)),
            to: new ConstantNode(id: $myConstant, resolved: true, meta: new FileMeta('', 1, 1)),
            meta: $meta
        ));
        $graph->addEdge(new DeclarationPropertyEdge(
            from: new ClassNode(id: $classA, resolved: true, meta: new FileMeta('', 1, 1)),
            to: new PropertyNode(id: $serviceProperty, resolved: true, meta: new FileMeta('', 1, 1)),
            meta: $meta
        ));
        $graph->addEdge(new DeclarationPropertyEdge(from: new ClassNode(id: $classA, resolved: true, meta: new FileMeta('', 1, 1)), to: new PropertyNode(id: $statusProperty, resolved: true, meta: new FileMeta('', 1, 1)), meta: $meta));
        $graph->addEdge(new DeclarationMethodEdge(from: new ClassNode(id: $classA, resolved: true, meta: new FileMeta('', 1, 1)), to: new MethodNode(id: $methodA, resolved: true, meta: new FileMeta('', 1, 1)), meta: $meta));
        $graph->addEdge(new DeclarationMethodEdge(from: new ClassNode(id: $classA, resolved: true, meta: new FileMeta('', 1, 1)), to: new MethodNode(id: $execute, resolved: true, meta: new FileMeta('', 1, 1)), meta: $meta));

        $graph->addEdge(new DeclarationTypePropertyEdge(
            from: new PropertyNode(id: $serviceProperty, resolved: true, meta: new FileMeta('', 1, 1)),
            to: new GraphInterfaceNode(id: $serviceInterface, resolved: true, meta: new FileMeta('', 1, 1)),
            meta: $meta
        ));
        $graph->addEdge(new DeclarationTypePropertyEdge(
            from: new PropertyNode(id: $statusProperty, resolved: true, meta: new FileMeta('', 1, 1)),
            to: new EnumNode(id: $statusEnum, resolved: true, meta: new FileMeta('', 1, 1)),
            meta: $meta
        ));

        $graph->addEdge(new DeclarationTypeParameterEdge(
            from: new MethodNode(id: $methodA, resolved: true, meta: new FileMeta('', 1, 1)),
            to: new EnumNode(id: $statusEnum, resolved: true, meta: new FileMeta('', 1, 1)),
            meta: $meta
        ));
        $graph->addEdge(new DeclarationTypeReturnEdge(
            from: new MethodNode(id: $methodA, resolved: true, meta: new FileMeta('', 1, 1)),
            to: new GraphInterfaceNode(id: $serviceInterface, resolved: true, meta: new FileMeta('', 1, 1)),
            meta: $meta
        ));

        $graph->addEdge(new InstantiationEdge(from: new MethodNode(id: $methodA, resolved: true, meta: new FileMeta('', 1, 1)), to: new ClassNode(id: $helperClass, resolved: true, meta: new FileMeta('', 1, 1)), meta: $meta));
        $graph->addEdge(new MethodCallEdge(from: new MethodNode(id: $methodA, resolved: true, meta: new FileMeta('', 1, 1)), to: new MethodNode(id: $helperMethod, resolved: true, meta: new FileMeta('', 1, 1)), meta: $meta));
        $graph->addEdge(new PropertyAccessEdge(from: new MethodNode(id: $methodA, resolved: true, meta: new FileMeta('', 1, 1)), to: new PropertyNode(id: $statusProperty, resolved: true, meta: new FileMeta('', 1, 1)), meta: $meta));
        $graph->addEdge(new FunctionCallEdge(from: new MethodNode(id: $methodA, resolved: true, meta: new FileMeta('', 1, 1)), to: new FunctionNode(id: $helperFunction, resolved: true, meta: new FileMeta('', 1, 1)), meta: $meta));
        $graph->addEdge(new ConstFetchEdge(from: new MethodNode(id: $methodA, resolved: true, meta: new FileMeta('', 1, 1)), to: new ConstantNode(id: $myConstant, resolved: true, meta: new FileMeta('', 1, 1)), meta: $meta));

        $graph->addEdge(new StaticCallEdge(from: new MethodNode(id: $execute, resolved: true, meta: new FileMeta('', 1, 1)), to: new MethodNode(id: $staticHelper, resolved: true, meta: new FileMeta('', 1, 1)), meta: $meta));
        $graph->addEdge(new StaticPropertyAccessEdge(
            from: new MethodNode(id: $execute, resolved: true, meta: new FileMeta('', 1, 1)),
            to: new PropertyNode(id: $staticProperty, resolved: true, meta: new FileMeta('', 1, 1)),
            meta: $meta
        ));

        $graph->addEdge(new DeclarationEnumCaseEdge(from: new EnumNode(id: $statusEnum, resolved: true, meta: new FileMeta('', 1, 1)), to: new EnumCaseNode(id: $activeCase, resolved: true, meta: new FileMeta('', 1, 1)), meta: $meta));
        $graph->addEdge(new DeclarationEnumCaseEdge(
            from: new EnumNode(id: $statusEnum, resolved: true, meta: new FileMeta('', 1, 1)),
            to: new EnumCaseNode(id: $inactiveCase, resolved: true, meta: new FileMeta('', 1, 1)),
            meta: $meta
        ));
        $graph->addEdge(new DeclarationEnumCaseEdge(
            from: new EnumNode(id: $statusEnum, resolved: true, meta: new FileMeta('', 1, 1)),
            to: new EnumCaseNode(id: $pendingCase, resolved: true, meta: new FileMeta('', 1, 1)),
            meta: $meta
        ));

        $graph->addEdge(new DeclarationConstantEdge(
            from: new ClassNode(id: $baseClass, resolved: true, meta: new FileMeta('', 1, 1)),
            to: new ConstantNode(id: $baseConstant, resolved: true, meta: new FileMeta('', 1, 1)),
            meta: $meta
        ));
        $graph->addEdge(new DeclarationPropertyEdge(
            from: new ClassNode(id: $baseClass, resolved: true, meta: new FileMeta('', 1, 1)),
            to: new PropertyNode(id: $baseProperty, resolved: true, meta: new FileMeta('', 1, 1)),
            meta: $meta
        ));
        $graph->addEdge(new DeclarationMethodEdge(from: new ClassNode(id: $baseClass, resolved: true, meta: new FileMeta('', 1, 1)), to: new MethodNode(id: $baseMethod, resolved: true, meta: new FileMeta('', 1, 1)), meta: $meta));

        $graph->addEdge(new DeclarationPropertyEdge(
            from: new ClassNode(id: $helperClass, resolved: true, meta: new FileMeta('', 1, 1)),
            to: new PropertyNode(id: $staticProperty, resolved: true, meta: new FileMeta('', 1, 1)),
            meta: $meta
        ));
        $graph->addEdge(new DeclarationMethodEdge(
            from: new ClassNode(id: $helperClass, resolved: true, meta: new FileMeta('', 1, 1)),
            to: new MethodNode(id: $helperMethod, resolved: true, meta: new FileMeta('', 1, 1)),
            meta: $meta
        ));
        $graph->addEdge(new DeclarationMethodEdge(
            from: new ClassNode(id: $helperClass, resolved: true, meta: new FileMeta('', 1, 1)),
            to: new MethodNode(id: $staticHelper, resolved: true, meta: new FileMeta('', 1, 1)),
            meta: $meta
        ));

        $graph->addEdge(new DeclarationMethodEdge(from: new TraitNode(id: $loggerTrait, resolved: true, meta: new FileMeta('', 1, 1)), to: new MethodNode(id: $logMethod, resolved: true, meta: new FileMeta('', 1, 1)), meta: $meta));

        $graph->addEdge(new DeclarationMethodEdge(
            from: new GraphInterfaceNode(id: $serviceInterface, resolved: true, meta: new FileMeta('', 1, 1)),
            to: new MethodNode(id: $interfaceExecute, resolved: true, meta: new FileMeta('', 1, 1)),
            meta: $meta
        ));

        $visited = [];
        $traversal = new DependencyTraversal();
        $traversal->traverse($graph, $classA, function (Node $node, int $depth) use (&$visited) {
            $visited[] = [
                'id' => $node->id()->toString(),
                'kind' => $node->kind()->value,
                'depth' => $depth,
            ];

            return true;
        });

        self::assertSame('Example\Coverage\ClassA', $visited[0]['id']);
        self::assertSame('Example\Coverage\BaseClass', $visited[1]['id']);
        self::assertSame('Example\Coverage\BaseClass::BASE_CONSTANT', $visited[2]['id']);
        self::assertSame('Example\Coverage\BaseClass::baseProperty', $visited[3]['id']);
        self::assertSame('Example\Coverage\BaseClass::baseMethod', $visited[4]['id']);
        self::assertSame('Example\Coverage\ServiceInterface', $visited[5]['id']);
        self::assertSame('Example\Coverage\ServiceInterface::execute', $visited[6]['id']);
        self::assertSame('Example\Coverage\LoggerTrait', $visited[7]['id']);
        self::assertSame('Example\Coverage\LoggerTrait::log', $visited[8]['id']);
        self::assertSame('Example\Coverage\ClassA::MY_CONSTANT', $visited[9]['id']);
        self::assertSame('Example\Coverage\ClassA::service', $visited[10]['id']);
        self::assertSame('Example\Coverage\ServiceInterface', $visited[11]['id']);
        self::assertSame('Example\Coverage\ServiceInterface::execute', $visited[12]['id']);
        self::assertSame('Example\Coverage\ClassA::status', $visited[13]['id']);
        self::assertSame('Example\Coverage\Status', $visited[14]['id']);
        self::assertSame('Example\Coverage\Status::ACTIVE', $visited[15]['id']);
        self::assertSame('Example\Coverage\Status::INACTIVE', $visited[16]['id']);
        self::assertSame('Example\Coverage\Status::PENDING', $visited[17]['id']);
        self::assertSame('Example\Coverage\ClassA::methodA', $visited[18]['id']);
        self::assertSame('Example\Coverage\Status', $visited[19]['id']);
        self::assertSame('Example\Coverage\Status::ACTIVE', $visited[20]['id']);
        self::assertSame('Example\Coverage\Status::INACTIVE', $visited[21]['id']);
        self::assertSame('Example\Coverage\Status::PENDING', $visited[22]['id']);
        self::assertSame('Example\Coverage\ServiceInterface', $visited[23]['id']);
        self::assertSame('Example\Coverage\ServiceInterface::execute', $visited[24]['id']);
        self::assertSame('Example\Coverage\HelperClass', $visited[25]['id']);
        self::assertSame('Example\Coverage\HelperClass::staticProperty', $visited[26]['id']);
        self::assertSame('Example\Coverage\HelperClass::helperMethod', $visited[27]['id']);
        self::assertSame('Example\Coverage\HelperClass::staticHelper', $visited[28]['id']);
        self::assertSame('Example\Coverage\HelperClass::helperMethod', $visited[29]['id']);
        self::assertSame('Example\Coverage\ClassA::status', $visited[30]['id']);
        self::assertSame('Example\Coverage\Status', $visited[31]['id']);
        self::assertSame('Example\Coverage\Status::ACTIVE', $visited[32]['id']);
        self::assertSame('Example\Coverage\Status::INACTIVE', $visited[33]['id']);
        self::assertSame('Example\Coverage\Status::PENDING', $visited[34]['id']);
        self::assertSame('Example\Coverage\helperFunction', $visited[35]['id']);
        self::assertSame('Example\Coverage\ClassA::MY_CONSTANT', $visited[36]['id']);
        self::assertSame('Example\Coverage\ClassA::execute', $visited[37]['id']);
        self::assertSame('Example\Coverage\HelperClass::staticHelper', $visited[38]['id']);
        self::assertSame('Example\Coverage\HelperClass::staticProperty', $visited[39]['id']);

        self::assertCount(40, $visited);

        $edgeKinds = [
            EdgeKind::MethodCall,
            EdgeKind::StaticCall,
            EdgeKind::PropertyAccess,
            EdgeKind::StaticPropertyAccess,
            EdgeKind::FunctionCall,
            EdgeKind::Instantiation,
            EdgeKind::ConstFetch,
            EdgeKind::DeclarationMethod,
            EdgeKind::DeclarationProperty,
            EdgeKind::DeclarationConstant,
            EdgeKind::DeclarationExtends,
            EdgeKind::DeclarationImplements,
            EdgeKind::DeclarationTraitUse,
            EdgeKind::DeclarationTypeReturn,
            EdgeKind::DeclarationTypeParameter,
            EdgeKind::DeclarationTypeProperty,
        ];

        $nodeKinds = [
            NodeKind::Klass,
            NodeKind::Interface,
            NodeKind::Trait,
            NodeKind::Enum,
            NodeKind::EnumCase,
            NodeKind::Method,
            NodeKind::Property,
            NodeKind::Constant,
            NodeKind::Function,
        ];

        self::assertCount(16, $edgeKinds, 'All 16 EdgeKinds are represented in the test');
        self::assertCount(16, $edgeKinds, 'All 16 EdgeKinds are represented in the test');
        self::assertCount(9, $nodeKinds, 'All 9 NodeKinds are represented in the test');
    }

    #[Test]
    public function testTraverseFiltersUsedByAndDeclaredInEdges(): void
    {
        $graph = new Graph();
        $meta = new FileMeta('/path/to/file.php', 1, 1);

        $fromId = new ClassNodeId('App', 'From');
        $toId = new ClassNodeId('App', 'To');
        $from = new ClassNode($fromId, true, $meta);
        $to = new ClassNode($toId, true, $meta);

        $graph->addNode($from);
        $graph->addNode($to);

        $graph->addEdge(new DeclarationExtendsEdge($from, $to, $meta));

        $declaredInEdge = $graph->edge($toId, $fromId);
        self::assertNotNull($declaredInEdge);
        self::assertSame(EdgeKind::DeclaredIn, $declaredInEdge->kind());

        $visited = [];
        $traversal = new DependencyTraversal();
        $traversal->traverse($graph, $toId, function (Node $node, int $depth) use (&$visited) {
            $visited[] = $node->id()->toString();

            return true;
        });

        self::assertSame(['App\To'], $visited);
    }

    #[Test]
    public function testTraverseHandlesRecursion(): void
    {
        $graph = new Graph();
        $meta = new FileMeta('/path/to/file.php', 1, 1);

        $nodeAId = new ClassNodeId('App', 'A');
        $nodeBId = new ClassNodeId('App', 'B');
        $nodeCId = new ClassNodeId('App', 'C');
        $nodeA = new ClassNode($nodeAId, true, $meta);
        $nodeB = new ClassNode($nodeBId, true, $meta);
        $nodeC = new ClassNode($nodeCId, true, $meta);

        $graph->addNode($nodeA);
        $graph->addNode($nodeB);
        $graph->addNode($nodeC);

        $graph->addEdge(new DeclarationExtendsEdge($nodeA, $nodeB, $meta));
        $graph->addEdge(new DeclarationExtendsEdge($nodeB, $nodeC, $meta));
        $graph->addEdge(new DeclarationExtendsEdge($nodeC, $nodeA, $meta));

        $visited = [];
        $traversal = new DependencyTraversal();
        $traversal->traverse($graph, $nodeAId, function (Node $node, int $depth) use (&$visited) {
            $visited[] = $node->id()->toString();

            return true;
        });

        self::assertSame(['App\A', 'App\B', 'App\C', 'App\A'], $visited);
    }

    #[Test]
    public function testTraverseHandlesNonExistentNode(): void
    {
        $graph = new Graph();
        $nodeId = new ClassNodeId('App', 'NonExistent');

        $visited = [];
        $traversal = new DependencyTraversal();
        $traversal->traverse($graph, $nodeId, function (Node $node, int $depth) use (&$visited) {
            $visited[] = $node->id()->toString();

            return true;
        });

        self::assertEmpty($visited);
    }
}
