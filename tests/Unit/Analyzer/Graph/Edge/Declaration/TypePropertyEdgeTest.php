<?php

declare(strict_types=1);

namespace Tests\Unit\Analyzer\Graph\Edge\Declaration;

use App\Analyzer\Graph\Edge\Declaration\TypePropertyEdge;
use App\Analyzer\Graph\Edge\Inverse\DeclaredInEdge;
use App\Analyzer\Graph\EdgeKind;
use App\Analyzer\Graph\FileMeta;
use App\Analyzer\Graph\Node\BuiltinNode;
use App\Analyzer\Graph\Node\PropertyNode;
use App\Analyzer\Graph\NodeId\BuiltinNodeId;
use App\Analyzer\Graph\NodeId\PropertyNodeId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(TypePropertyEdge::class)]
#[UsesClass(DeclaredInEdge::class)]
#[UsesClass(FileMeta::class)]
#[UsesClass(BuiltinNodeId::class)]
#[UsesClass(PropertyNodeId::class)]
#[UsesClass(BuiltinNode::class)]
#[UsesClass(PropertyNode::class)]
#[UsesClass(\App\Analyzer\Graph\QualifiedName::class)]
#[Small]
final class TypePropertyEdgeTest extends TestCase
{
    public function testKindReportsTheRelationItRecords(): void
    {
        $meta = new FileMeta('/project/src/Invoice.php', 12, 1);
        $edge = new TypePropertyEdge(
            new PropertyNode(PropertyNodeId::of('App\Domain\Invoice', 'lines'), true, $meta),
            new BuiltinNode(BuiltinNodeId::of('int'), true, $meta),
            $meta,
        );

        self::assertSame(EdgeKind::DeclarationTypeProperty, $edge->kind());
    }

    public function testFromNamesTheSymbolTheRelationStartsAt(): void
    {
        $meta = new FileMeta('/project/src/Invoice.php', 12, 1);
        $edge = new TypePropertyEdge(
            new PropertyNode(PropertyNodeId::of('App\Domain\Invoice', 'lines'), true, $meta),
            new BuiltinNode(BuiltinNodeId::of('int'), true, $meta),
            $meta,
        );

        self::assertSame('App\Domain\Invoice::lines', $edge->from()->toString());
    }

    public function testToNamesTheSymbolTheRelationPointsAt(): void
    {
        $meta = new FileMeta('/project/src/Invoice.php', 12, 1);
        $edge = new TypePropertyEdge(
            new PropertyNode(PropertyNodeId::of('App\Domain\Invoice', 'lines'), true, $meta),
            new BuiltinNode(BuiltinNodeId::of('int'), true, $meta),
            $meta,
        );

        self::assertSame('int', $edge->to()->toString());
    }

    public function testMetaReturnsWhereTheRelationIsWritten(): void
    {
        $meta = new FileMeta('/project/src/Invoice.php', 12, 1);
        $edge = new TypePropertyEdge(
            new PropertyNode(PropertyNodeId::of('App\Domain\Invoice', 'lines'), true, $meta),
            new BuiltinNode(BuiltinNodeId::of('int'), true, $meta),
            $meta,
        );

        self::assertSame($meta, $edge->meta());
    }

    public function testInvertReadsTheRelationTheOtherWayRound(): void
    {
        $meta = new FileMeta('/project/src/Invoice.php', 12, 1);
        $edge = new TypePropertyEdge(
            new PropertyNode(PropertyNodeId::of('App\Domain\Invoice', 'lines'), true, $meta),
            new BuiltinNode(BuiltinNodeId::of('int'), true, $meta),
            $meta,
        );

        $inverted = $edge->invert();

        self::assertInstanceOf(DeclaredInEdge::class, $inverted);
        self::assertSame('int', $inverted->from()->toString());
        self::assertSame('App\Domain\Invoice::lines', $inverted->to()->toString());
        self::assertSame($meta, $inverted->meta());
    }

    public function testInvertTwiceYieldsTheEdgeItStartedFrom(): void
    {
        $meta = new FileMeta('/project/src/Invoice.php', 12, 1);
        $edge = new TypePropertyEdge(
            new PropertyNode(PropertyNodeId::of('App\Domain\Invoice', 'lines'), true, $meta),
            new BuiltinNode(BuiltinNodeId::of('int'), true, $meta),
            $meta,
        );

        self::assertSame($edge, $edge->invert()->invert());
    }
}
