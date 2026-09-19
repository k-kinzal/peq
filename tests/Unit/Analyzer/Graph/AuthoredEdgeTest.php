<?php

declare(strict_types=1);

namespace Tests\Unit\Analyzer\Graph;

use App\Analyzer\Graph\AuthoredEdge;
use App\Analyzer\Graph\FileMeta;
use App\Analyzer\Graph\NodeId\ClassNodeId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Tests\Fixture\Graph\StubEdge;
use Tests\Fixture\Graph\StubNode;

/**
 * @internal
 */
#[CoversClass(AuthoredEdge::class)]
#[UsesClass(FileMeta::class)]
#[UsesClass(ClassNodeId::class)]
#[UsesClass(\App\Analyzer\Graph\QualifiedName::class)]
#[Small]
final class AuthoredEdgeTest extends TestCase
{
    public function testFromReportsTheIdentifierOfTheSourceNodeRatherThanTheNode(): void
    {
        $edge = new StubEdge(
            new StubNode(ClassNodeId::of('App\Domain\Invoice')),
            new StubNode(ClassNodeId::of('App\Domain\Money')),
            new FileMeta('/project/src/Domain/Invoice.php', 12, 1),
        );

        self::assertSame('App\Domain\Invoice', $edge->from()->toString());
    }

    public function testToReportsTheIdentifierOfTheTargetNodeRatherThanTheNode(): void
    {
        $edge = new StubEdge(
            new StubNode(ClassNodeId::of('App\Domain\Invoice')),
            new StubNode(ClassNodeId::of('App\Domain\Money')),
            new FileMeta('/project/src/Domain/Invoice.php', 12, 1),
        );

        self::assertSame('App\Domain\Money', $edge->to()->toString());
    }

    public function testMetaReportsTheLocationItWasBuiltWith(): void
    {
        $meta = new FileMeta('/project/src/Domain/Invoice.php', 12, 1);
        $edge = new StubEdge(
            new StubNode(ClassNodeId::of('App\Domain\Invoice')),
            new StubNode(ClassNodeId::of('App\Domain\Money')),
            $meta,
        );

        self::assertSame($meta, $edge->meta());
    }

    public function testAnEdgeBetweenOneNodeAndItselfNamesItAtBothEnds(): void
    {
        $node = new StubNode(ClassNodeId::of('App\Domain\Invoice'));
        $edge = new StubEdge($node, $node, new FileMeta('/project/src/Domain/Invoice.php', 12, 1));

        self::assertSame($edge->from()->toString(), $edge->to()->toString());
    }
}
