<?php

declare(strict_types=1);

namespace Tests\Unit\Analyzer\Graph\Edge;

use App\Analyzer\Graph\Edge\DeclarationPropertyEdge;
use App\Analyzer\Graph\Edge\DeclaredInEdge;
use App\Analyzer\Graph\EdgeKind;
use App\Analyzer\Graph\FileMeta;
use App\Analyzer\Graph\Node\ClassNode;
use App\Analyzer\Graph\Node\PropertyNode;
use App\Analyzer\Graph\NodeId\ClassNodeId;
use App\Analyzer\Graph\NodeId\PropertyNodeId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(DeclarationPropertyEdge::class)]
#[UsesClass(DeclaredInEdge::class)]
#[UsesClass(FileMeta::class)]
#[UsesClass(ClassNodeId::class)]
#[UsesClass(PropertyNodeId::class)]
#[UsesClass(ClassNode::class)]
#[UsesClass(PropertyNode::class)]
#[UsesClass(\App\Analyzer\Graph\QualifiedName::class)]
#[Small]
final class DeclarationPropertyEdgeTest extends TestCase
{
    public function testKindReportsTheRelationItRecords(): void
    {
        $meta = new FileMeta('/project/src/Invoice.php', 12, 1);
        $edge = new DeclarationPropertyEdge(
            new ClassNode(ClassNodeId::of('App\Domain\Invoice'), true, $meta),
            new PropertyNode(PropertyNodeId::of('App\Domain\Invoice', 'lines'), true, $meta),
            $meta,
        );

        self::assertSame(EdgeKind::DeclarationProperty, $edge->kind());
    }

    public function testFromNamesTheSymbolTheRelationStartsAt(): void
    {
        $meta = new FileMeta('/project/src/Invoice.php', 12, 1);
        $edge = new DeclarationPropertyEdge(
            new ClassNode(ClassNodeId::of('App\Domain\Invoice'), true, $meta),
            new PropertyNode(PropertyNodeId::of('App\Domain\Invoice', 'lines'), true, $meta),
            $meta,
        );

        self::assertSame('App\Domain\Invoice', $edge->from()->toString());
    }

    public function testToNamesTheSymbolTheRelationPointsAt(): void
    {
        $meta = new FileMeta('/project/src/Invoice.php', 12, 1);
        $edge = new DeclarationPropertyEdge(
            new ClassNode(ClassNodeId::of('App\Domain\Invoice'), true, $meta),
            new PropertyNode(PropertyNodeId::of('App\Domain\Invoice', 'lines'), true, $meta),
            $meta,
        );

        self::assertSame('App\Domain\Invoice::lines', $edge->to()->toString());
    }

    public function testMetaReturnsWhereTheRelationIsWritten(): void
    {
        $meta = new FileMeta('/project/src/Invoice.php', 12, 1);
        $edge = new DeclarationPropertyEdge(
            new ClassNode(ClassNodeId::of('App\Domain\Invoice'), true, $meta),
            new PropertyNode(PropertyNodeId::of('App\Domain\Invoice', 'lines'), true, $meta),
            $meta,
        );

        self::assertSame($meta, $edge->meta());
    }

    public function testInvertReadsTheRelationTheOtherWayRound(): void
    {
        $meta = new FileMeta('/project/src/Invoice.php', 12, 1);
        $edge = new DeclarationPropertyEdge(
            new ClassNode(ClassNodeId::of('App\Domain\Invoice'), true, $meta),
            new PropertyNode(PropertyNodeId::of('App\Domain\Invoice', 'lines'), true, $meta),
            $meta,
        );

        $inverted = $edge->invert();

        self::assertInstanceOf(DeclaredInEdge::class, $inverted);
        self::assertSame('App\Domain\Invoice::lines', $inverted->from()->toString());
        self::assertSame('App\Domain\Invoice', $inverted->to()->toString());
        self::assertSame($meta, $inverted->meta());
    }

    public function testInvertTwiceYieldsTheEdgeItStartedFrom(): void
    {
        $meta = new FileMeta('/project/src/Invoice.php', 12, 1);
        $edge = new DeclarationPropertyEdge(
            new ClassNode(ClassNodeId::of('App\Domain\Invoice'), true, $meta),
            new PropertyNode(PropertyNodeId::of('App\Domain\Invoice', 'lines'), true, $meta),
            $meta,
        );

        self::assertSame($edge, $edge->invert()->invert());
    }
}
