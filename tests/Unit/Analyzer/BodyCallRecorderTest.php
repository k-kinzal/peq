<?php

declare(strict_types=1);

namespace Tests\Unit\Analyzer;

use App\Analyzer\BodyCallRecorder;
use App\Analyzer\Graph\FileMeta;
use App\Analyzer\Graph\Graph;
use App\Analyzer\Graph\Node\FunctionNode;
use App\Analyzer\Graph\NodeId\FunctionNodeId;
use App\Analyzer\Graph\Resolution\ClassHierarchy;
use App\Analyzer\ReceiverBinding;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Stmt\Expression;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[UsesClass(\App\Analyzer\CallBody::class)]
#[UsesClass(\App\Analyzer\Graph\AuthoredEdge::class)]
#[UsesClass(\App\Analyzer\Graph\Declaration\Modifiers::class)]
#[UsesClass(\App\Analyzer\Graph\Declaration\Parameter::class)]
#[UsesClass(\App\Analyzer\Graph\Declaration\Signature::class)]
#[UsesClass(\App\Analyzer\Graph\Declaration\SymbolDeclaration::class)]
#[UsesClass(\App\Analyzer\Graph\EdgeIdentity::class)]
#[UsesClass(\App\Analyzer\Graph\Edge\Inverse\UsedByEdge::class)]
#[UsesClass(\App\Analyzer\Graph\Edge\Usage\MethodCallEdge::class)]
#[UsesClass(FileMeta::class)]
#[UsesClass(Graph::class)]
#[UsesClass(FunctionNodeId::class)]
#[UsesClass(\App\Analyzer\Graph\NodeId\MethodNodeId::class)]
#[UsesClass(\App\Analyzer\Graph\NodeId\UnknownNodeId::class)]
#[UsesClass(FunctionNode::class)]
#[UsesClass(\App\Analyzer\Graph\Node\MethodNode::class)]
#[UsesClass(\App\Analyzer\Graph\Node\UnknownNode::class)]
#[UsesClass(\App\Analyzer\Graph\QualifiedName::class)]
#[UsesClass(ClassHierarchy::class)]
#[UsesClass(\App\Analyzer\Graph\Resolution\TypeConstraint::class)]
#[UsesClass(ReceiverBinding::class)]
#[CoversClass(BodyCallRecorder::class)]
#[Small]
final class BodyCallRecorderTest extends TestCase
{
    public function testRecordAreRecordedWithoutAClassScope(): void
    {
        $graph = new Graph();
        $source = new FunctionNode(
            FunctionNodeId::of('invoke'),
            true,
            new FileMeta('/source.php', 1, 1),
            new \App\Analyzer\Graph\Declaration\SymbolDeclaration(signature: new \App\Analyzer\Graph\Declaration\Signature([
                new \App\Analyzer\Graph\Declaration\Parameter('port', 'Port'),
            ])),
        );
        $graph->addNode($source);
        $call = new MethodCall(new Variable('port'), 'run', [], ['startLine' => 2, 'startFilePos' => 20]);

        (new BodyCallRecorder($graph, new ClassHierarchy($graph)))->record([new Expression($call)], $source);
        $edges = $graph->forwardEdges();

        self::assertCount(1, $edges);
        self::assertSame('invoke', $edges[0]->from()->toString());
        self::assertSame('Port::run', $edges[0]->to()->toString());
        self::assertSame(20, $edges[0]->meta()->offset);
    }

    public function testExpressionsKeepsTheWrittenCall(): void
    {
        $call = new MethodCall(new Variable('port'), 'run');
        $graph = new Graph();
        $recorder = new BodyCallRecorder($graph, new ClassHierarchy($graph));

        self::assertContains($call, $recorder->expressions([new Expression($call)]));
    }

    public function testCallKeepsAnUnknownReceiverAsAnUnresolvedOccurrence(): void
    {
        $graph = new Graph();
        $source = new FunctionNode(FunctionNodeId::of('run'), true, new FileMeta('/source.php', 1, 1));
        $graph->addNode($source);
        $call = new MethodCall(new Variable('unknown'), 'run', [], ['startLine' => 2, 'startFilePos' => 20, 'peqStartColumn' => 5]);
        $hierarchy = new ClassHierarchy($graph);

        (new BodyCallRecorder($graph, $hierarchy))->call($call, $source, new ReceiverBinding($hierarchy, $source));

        self::assertCount(1, $graph->forwardEdges());
        self::assertSame('unresolved-call@/source.php:2:5', $graph->forwardEdges()[0]->to()->toString());
        self::assertEquals(new FileMeta('/source.php', 2, 5, 20), $graph->forwardEdges()[0]->meta());
        self::assertEquals(new FileMeta('/source.php', 2, 5, 20), $graph->nodeNamed('unresolved-call@/source.php:2:5')?->meta());
    }

    public function testTargetsOnlyUseTheIntersectionMemberThatDeclaresTheMethod(): void
    {
        $graph = new Graph();
        $method = new \App\Analyzer\Graph\Node\MethodNode(\App\Analyzer\Graph\NodeId\MethodNodeId::of('Named', 'name'), true);
        $graph->addNode($method);
        $recorder = new BodyCallRecorder($graph, new ClassHierarchy($graph));

        self::assertSame([$method], $recorder->targets('Named&Countable', 'name'));
    }

    public function testRecordUsesLocalAssignmentsAndKeepsResolvedNodesAndSourcePositions(): void
    {
        $graph = new Graph();
        $source = new FunctionNode(FunctionNodeId::of('run'), true, new FileMeta('/source.php', 1, 1));
        $body = [new Expression(new \PhpParser\Node\Expr\Assign(new Variable('service'), new \PhpParser\Node\Expr\New_(new \PhpParser\Node\Name('Service')))), new Expression(new \PhpParser\Node\Expr\NullsafeMethodCall(new Variable('service'), 'run', [], ['startLine' => 3, 'startFilePos' => 42]))];

        (new BodyCallRecorder($graph, new ClassHierarchy($graph)))->record($body, $source);
        $edges = $graph->forwardEdges();

        self::assertCount(1, $edges);
        self::assertSame('Service::run', $edges[0]->to()->toString());
        self::assertNotNull($graph->nodeNamed('Service::run'));
        self::assertSame(['/source.php', 3, 1, 42], [$edges[0]->meta()->path, $edges[0]->meta()->line, $edges[0]->meta()->column, $edges[0]->meta()->offset]);
    }

    public function testCallLeavesNamedThisCallsToTheSourceEmitter(): void
    {
        $graph = new Graph();
        $source = new \App\Analyzer\Graph\Node\MethodNode(\App\Analyzer\Graph\NodeId\MethodNodeId::of('Service', 'run'), true, new FileMeta('/source.php', 1, 1));
        $hierarchy = new ClassHierarchy($graph);

        (new BodyCallRecorder($graph, $hierarchy))->call(new MethodCall(new Variable('this'), 'named'), $source, new ReceiverBinding($hierarchy, $source));

        self::assertSame([], $graph->forwardEdges());
    }

    public function testCallKeepsDynamicMethodNamesAsUnresolvedEvidence(): void
    {
        $graph = new Graph();
        $source = new \App\Analyzer\Graph\Node\MethodNode(\App\Analyzer\Graph\NodeId\MethodNodeId::of('Service', 'run'), true, new FileMeta('/source.php', 1, 1));
        $hierarchy = new ClassHierarchy($graph);
        $call = new MethodCall(new Variable('this'), new Variable('name'), [], ['startLine' => 2, 'startFilePos' => 20]);

        (new BodyCallRecorder($graph, $hierarchy))->call($call, $source, new ReceiverBinding($hierarchy, $source));
        $target = $graph->nodeNamed('unresolved-call@/source.php:2:1');
        $edges = $graph->forwardEdges();

        self::assertNotNull($target);
        self::assertFalse($target->resolved());
        self::assertCount(1, $edges);
        self::assertInstanceOf(\App\Analyzer\Graph\Edge\Usage\MethodCallEdge::class, $edges[0]);
        self::assertSame('$this->{$name}()', $edges[0]->expression);
    }

    public function testCallDoesNotInventALocationForASymbolWithoutSource(): void
    {
        $graph = new Graph();
        $source = new FunctionNode(FunctionNodeId::of('run'));
        $hierarchy = new ClassHierarchy($graph);

        (new BodyCallRecorder($graph, $hierarchy))->call(new MethodCall(new Variable('unknown'), 'run'), $source, new ReceiverBinding($hierarchy, $source));

        self::assertSame([], $graph->forwardEdges());
    }
}
