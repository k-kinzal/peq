<?php

declare(strict_types=1);

namespace Tests\Unit\Analyzer\Graph;

use App\Analyzer\Graph\AuthoredEdge;
use App\Analyzer\Graph\Edge\Usage\MethodCallEdge;
use App\Analyzer\Graph\FileMeta;
use App\Analyzer\Graph\Node\MethodNode;
use App\Analyzer\Graph\NodeId\MethodNodeId;
use App\Analyzer\Graph\QualifiedName;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(AuthoredEdge::class)]
#[UsesClass(FileMeta::class)]
#[UsesClass(MethodCallEdge::class)]
#[UsesClass(MethodNode::class)]
#[UsesClass(MethodNodeId::class)]
#[UsesClass(QualifiedName::class)]
#[Small]
final class AuthoredEdgeTest extends TestCase
{
    public function testFromReportsTheIdentifierOfTheSourceNodeRatherThanTheNode(): void
    {
        $edge = new MethodCallEdge(
            new MethodNode(MethodNodeId::of('App\Domain\Invoice', 'total')),
            new MethodNode(MethodNodeId::of('App\Domain\Money', 'add')),
            new FileMeta('/project/src/Domain/Invoice.php', 12, 1),
        );

        self::assertEquals(MethodNodeId::of('App\Domain\Invoice', 'total'), $edge->from());
    }

    public function testToReportsTheIdentifierOfTheTargetNodeRatherThanTheNode(): void
    {
        $edge = new MethodCallEdge(
            new MethodNode(MethodNodeId::of('App\Domain\Invoice', 'total')),
            new MethodNode(MethodNodeId::of('App\Domain\Money', 'add')),
            new FileMeta('/project/src/Domain/Invoice.php', 12, 1),
        );

        self::assertEquals(MethodNodeId::of('App\Domain\Money', 'add'), $edge->to());
    }

    public function testMetaReportsTheLocationItWasBuiltWith(): void
    {
        $meta = new FileMeta('/project/src/Domain/Invoice.php', 12, 1);
        $edge = new MethodCallEdge(
            new MethodNode(MethodNodeId::of('App\Domain\Invoice', 'total')),
            new MethodNode(MethodNodeId::of('App\Domain\Money', 'add')),
            $meta,
        );

        self::assertSame($meta, $edge->meta());
    }

    public function testAnEdgeBetweenOneNodeAndItselfNamesItAtBothEnds(): void
    {
        $node = new MethodNode(MethodNodeId::of('App\Domain\Invoice', 'total'));
        $edge = new MethodCallEdge($node, $node, new FileMeta('/project/src/Domain/Invoice.php', 12, 1));

        self::assertSame([$node->id(), $node->id()], [$edge->from(), $edge->to()]);
    }
}
