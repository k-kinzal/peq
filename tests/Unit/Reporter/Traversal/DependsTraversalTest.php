<?php

declare(strict_types=1);

namespace Tests\Unit\Reporter\Traversal;

use App\Analyzer\Graph\Edge\DeclarationMethodEdge;
use App\Analyzer\Graph\Edge\FunctionCallEdge;
use App\Analyzer\Graph\FileMeta;
use App\Analyzer\Graph\Graph;
use App\Analyzer\Graph\Node;
use App\Analyzer\Graph\Node\ClassNode;
use App\Analyzer\Graph\Node\FunctionNode;
use App\Analyzer\Graph\Node\MethodNode;
use App\Analyzer\Graph\NodeId\ClassNodeId;
use App\Analyzer\Graph\NodeId\FunctionNodeId;
use App\Analyzer\Graph\NodeId\MethodNodeId;
use App\Reporter\Traversal\DependsTraversal;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
final class DependsTraversalTest extends TestCase
{
    #[Test]
    public function testTraverseFollowsUsedByEdges(): void
    {
        $traversal = new DependsTraversal();
        $graph = new Graph();

        $nodeA = new FunctionNode(new FunctionNodeId('App', 'A'));
        $nodeB = new FunctionNode(new FunctionNodeId('App', 'B'));

        $graph->addNode($nodeA);
        $graph->addNode($nodeB);

        $graph->addEdge(new FunctionCallEdge($nodeA, $nodeB, new FileMeta('file.php', 1, 1)));

        $visited = [];
        $traversal->traverse($graph, $nodeB->id, function (Node $node) use (&$visited) {
            $visited[] = $node->id()->toString();

            return true;
        });

        self::assertContains('App\B', $visited);
        self::assertContains('App\A', $visited);
    }

    #[Test]
    public function testTraverseFollowsDeclaredInEdges(): void
    {
        $traversal = new DependsTraversal();
        $graph = new Graph();

        $classC = new ClassNode(new ClassNodeId('App', 'C'));
        $methodM = new MethodNode(new MethodNodeId('App', 'C', 'M'));

        $graph->addNode($classC);
        $graph->addNode($methodM);

        $graph->addEdge(new DeclarationMethodEdge($classC, $methodM, new FileMeta('file.php', 1, 1)));

        $visited = [];
        $traversal->traverse($graph, $methodM->id, function (Node $node) use (&$visited) {
            $visited[] = $node->id()->toString();

            return true;
        });

        self::assertContains('App\C::M', $visited);
        self::assertContains('App\C', $visited);
    }

    #[Test]
    public function testTraverseHandlesRecursion(): void
    {
        $traversal = new DependsTraversal();
        $graph = new Graph();

        $nodeA = new FunctionNode(new FunctionNodeId('App', 'A'));
        $nodeB = new FunctionNode(new FunctionNodeId('App', 'B'));
        $nodeC = new FunctionNode(new FunctionNodeId('App', 'C'));

        $graph->addNode($nodeA);
        $graph->addNode($nodeB);
        $graph->addNode($nodeC);

        $graph->addEdge(new FunctionCallEdge($nodeA, $nodeB, new FileMeta('file.php', 1, 1)));
        $graph->addEdge(new FunctionCallEdge($nodeB, $nodeC, new FileMeta('file.php', 1, 1)));
        $graph->addEdge(new FunctionCallEdge($nodeC, $nodeA, new FileMeta('file.php', 1, 1)));

        $visited = [];
        $traversal->traverse($graph, $nodeA->id, function (Node $node) use (&$visited) {
            $visited[] = $node->id()->toString();

            return true;
        });

        self::assertSame(['App\A', 'App\C', 'App\B', 'App\A'], $visited);
    }

    #[Test]
    public function testTraverseHandlesNonExistentNode(): void
    {
        $traversal = new DependsTraversal();
        $graph = new Graph();
        $nodeId = new ClassNodeId('App', 'NonExistent');

        $visited = [];
        $traversal->traverse($graph, $nodeId, function (Node $node) use (&$visited) {
            $visited[] = $node->id()->toString();

            return true;
        });

        self::assertEmpty($visited);
    }

    #[Test]
    public function testTraverseStopsWhenCallbackReturnsFalse(): void
    {
        $traversal = new DependsTraversal();
        $graph = new Graph();
        $nodeA = new FunctionNode(new FunctionNodeId('App', 'A'));
        $nodeB = new FunctionNode(new FunctionNodeId('App', 'B'));

        $graph->addNode($nodeA);
        $graph->addNode($nodeB);

        $graph->addEdge(new FunctionCallEdge($nodeA, $nodeB, new FileMeta('file.php', 1, 1)));

        $visited = [];
        $traversal->traverse($graph, $nodeB->id, function (Node $node) use (&$visited) {
            $visited[] = $node->id()->toString();

            return false;
        });

        self::assertSame(['App\B'], $visited);
        self::assertNotContains('App\A', $visited);
    }
}
