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
}
