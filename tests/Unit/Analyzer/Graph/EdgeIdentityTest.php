<?php

declare(strict_types=1);

namespace Tests\Unit\Analyzer\Graph;

use App\Analyzer\Graph\Edge\Usage\MethodCallEdge;
use App\Analyzer\Graph\EdgeIdentity;
use App\Analyzer\Graph\FileMeta;
use App\Analyzer\Graph\Node\MethodNode;
use App\Analyzer\Graph\NodeId\MethodNodeId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[UsesClass(\App\Analyzer\Graph\AuthoredEdge::class)]
#[UsesClass(\App\Analyzer\Graph\Edge\Inverse\UsedByEdge::class)]
#[UsesClass(MethodCallEdge::class)]
#[UsesClass(FileMeta::class)]
#[UsesClass(MethodNodeId::class)]
#[UsesClass(MethodNode::class)]
#[UsesClass(\App\Analyzer\Graph\QualifiedName::class)]
#[UsesClass(\App\Analyzer\Graph\Edge\Usage\StaticCallEdge::class)]
#[UsesClass(\App\Analyzer\Graph\Edge\Usage\PossibleCallEdge::class)]
#[UsesClass(\App\Analyzer\Graph\Edge\Usage\InstantiationEdge::class)]
#[UsesClass(\App\Analyzer\Graph\Edge\Declaration\AttributeEdge::class)]
#[UsesClass(\App\Analyzer\Graph\Node\UnknownNode::class)]
#[UsesClass(\App\Analyzer\Graph\NodeId\UnknownNodeId::class)]
#[UsesClass(\App\Analyzer\Graph\Node\ClassNode::class)]
#[UsesClass(\App\Analyzer\Graph\NodeId\ClassNodeId::class)]
#[CoversClass(EdgeIdentity::class)]
#[Small]
final class EdgeIdentityTest extends TestCase
{
    public function testOfOnTheSameLineRemainDistinctAndHaveDistinctInverses(): void
    {
        $caller = new MethodNode(MethodNodeId::of('Controller', 'action'));
        $callee = new MethodNode(MethodNodeId::of('Port', 'run'));
        $first = new MethodCallEdge($caller, $callee, new FileMeta('/source.php', 4, 1, 40));
        $second = new MethodCallEdge($caller, $callee, new FileMeta('/source.php', 4, 1, 60));

        self::assertNotSame(EdgeIdentity::of($first), EdgeIdentity::of($second));
        self::assertNotSame(EdgeIdentity::of($first->invert()), EdgeIdentity::of($second->invert()));
        self::assertSame(EdgeIdentity::of($first), EdgeIdentity::of($first->invert()->invert()));
    }

    public function testEvidenceRetainsTheReceiverConstraint(): void
    {
        $caller = new MethodNode(MethodNodeId::of('Controller', 'action'));
        $callee = new MethodNode(MethodNodeId::of('Port', 'run'));
        $call = new MethodCallEdge($caller, $callee, new FileMeta('/source.php', 4, 1), 'Port&Tagged');

        self::assertSame(['receiverType' => 'Port&Tagged'], EdgeIdentity::evidence($call));
    }

    public function testOfDistinguishesDirectionEndpointsKindsAndSourceFiles(): void
    {
        $a = new MethodNode(MethodNodeId::of('Controller', 'action'));
        $b = new MethodNode(MethodNodeId::of('Port', 'run'));
        $c = new MethodNode(MethodNodeId::of('Port', 'other'));
        $meta = new FileMeta('/first.php', 2, 3, 20);
        $call = new MethodCallEdge($a, $b, $meta);
        $edges = [
            $call,
            $call->invert(),
            new MethodCallEdge($c, $b, $meta),
            new MethodCallEdge($a, $c, $meta),
            new \App\Analyzer\Graph\Edge\Usage\StaticCallEdge($a, $b, $meta),
            new MethodCallEdge($a, $b, new FileMeta('/second.php', 2, 3, 20)),
            new MethodCallEdge($a, $b, new FileMeta('/first.php', 4, 3, 20)),
            new MethodCallEdge($a, $b, new FileMeta('/first.php', 2, 5, 20)),
            new MethodCallEdge($a, $b, new FileMeta('/first.php', 2, 3, 21)),
            new MethodCallEdge($a, $b, $meta, 'Specialized'),
        ];

        self::assertCount(10, array_unique(array_map(EdgeIdentity::of(...), $edges)));
        self::assertSame(EdgeIdentity::of($call), EdgeIdentity::of(new MethodCallEdge($a, $b, $meta)));
    }

    public function testEvidencePreservesTheContractAndConcreteImplementingClass(): void
    {
        $a = new MethodNode(MethodNodeId::of('Controller', 'action'));
        $b = new MethodNode(MethodNodeId::of('Port', 'run'));
        $call = new MethodCallEdge($a, $b, new FileMeta('/source.php', 2, 1));
        $first = new \App\Analyzer\Graph\Edge\Usage\PossibleCallEdge($call, MethodNodeId::of('BaseService', 'run'), 'Port', 'Service');
        $second = new \App\Analyzer\Graph\Edge\Usage\PossibleCallEdge($call, MethodNodeId::of('BaseService', 'run'), 'Port', 'OtherService');

        self::assertSame(['receiverType' => 'Port', 'declaredTarget' => 'Port::run', 'implementationType' => 'Service'], EdgeIdentity::evidence($first));
        self::assertNotSame(EdgeIdentity::of($first), EdgeIdentity::of($second));
    }

    public function testEvidenceKeepsUnresolvedExpressionsAndAttributeScope(): void
    {
        $source = new MethodNode(MethodNodeId::of('Controller', 'action'));
        $target = new \App\Analyzer\Graph\Node\UnknownNode(new \App\Analyzer\Graph\NodeId\UnknownNodeId('unresolved'));
        $attribute = new \App\Analyzer\Graph\Node\ClassNode(\App\Analyzer\Graph\NodeId\ClassNodeId::of('Trace'));
        $meta = new FileMeta('/source.php', 2, 1);
        $call = new MethodCallEdge($source, $target, $meta, expression: '$port->$name()');
        $edge = new \App\Analyzer\Graph\Edge\Declaration\AttributeEdge($source, $attribute, $meta, ["'audit'"], 'port');

        self::assertSame(['expression' => '$port->$name()'], EdgeIdentity::evidence($call));
        self::assertSame(['arguments' => ["'audit'"], 'parameter' => 'port'], EdgeIdentity::evidence($edge));
        self::assertSame([], EdgeIdentity::evidence(new \App\Analyzer\Graph\Edge\Usage\InstantiationEdge($source, $attribute, $meta)));
    }
}
