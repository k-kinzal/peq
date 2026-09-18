<?php

declare(strict_types=1);

namespace Tests\Unit\Analyzer\Graph\Edge\Declaration;

use App\Analyzer\Graph\Edge\Declaration\MethodEdge;
use App\Analyzer\Graph\Edge\Inverse\DeclaredInEdge;
use App\Analyzer\Graph\EdgeKind;
use App\Analyzer\Graph\FileMeta;
use App\Analyzer\Graph\Node\ClassNode;
use App\Analyzer\Graph\Node\MethodNode;
use App\Analyzer\Graph\NodeId\ClassNodeId;
use App\Analyzer\Graph\NodeId\MethodNodeId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(MethodEdge::class)]
#[UsesClass(DeclaredInEdge::class)]
#[UsesClass(FileMeta::class)]
#[UsesClass(ClassNodeId::class)]
#[UsesClass(MethodNodeId::class)]
#[UsesClass(ClassNode::class)]
#[UsesClass(MethodNode::class)]
#[UsesClass(\App\Analyzer\Graph\QualifiedName::class)]
#[UsesClass(\App\Analyzer\Graph\AuthoredEdge::class)]
#[Small]
final class MethodEdgeTest extends TestCase
{
    public function testKindReportsTheRelationItRecords(): void
    {
        $meta = new FileMeta('/project/src/Invoice.php', 12, 1);
        $edge = new MethodEdge(
            new ClassNode(ClassNodeId::of('App\Domain\Invoice'), true, $meta),
            new MethodNode(MethodNodeId::of('App\Domain\Invoice', 'total'), true, $meta),
            $meta,
        );

        self::assertSame(EdgeKind::DeclarationMethod, $edge->kind());
    }

    public function testFromNamesTheSymbolTheRelationStartsAt(): void
    {
        $meta = new FileMeta('/project/src/Invoice.php', 12, 1);
        $edge = new MethodEdge(
            new ClassNode(ClassNodeId::of('App\Domain\Invoice'), true, $meta),
            new MethodNode(MethodNodeId::of('App\Domain\Invoice', 'total'), true, $meta),
            $meta,
        );

        self::assertSame('App\Domain\Invoice', $edge->from()->toString());
    }

    public function testToNamesTheSymbolTheRelationPointsAt(): void
    {
        $meta = new FileMeta('/project/src/Invoice.php', 12, 1);
        $edge = new MethodEdge(
            new ClassNode(ClassNodeId::of('App\Domain\Invoice'), true, $meta),
            new MethodNode(MethodNodeId::of('App\Domain\Invoice', 'total'), true, $meta),
            $meta,
        );

        self::assertSame('App\Domain\Invoice::total', $edge->to()->toString());
    }

    public function testMetaReturnsWhereTheRelationIsWritten(): void
    {
        $meta = new FileMeta('/project/src/Invoice.php', 12, 1);
        $edge = new MethodEdge(
            new ClassNode(ClassNodeId::of('App\Domain\Invoice'), true, $meta),
            new MethodNode(MethodNodeId::of('App\Domain\Invoice', 'total'), true, $meta),
            $meta,
        );

        self::assertSame($meta, $edge->meta());
    }

    public function testInvertReadsTheRelationTheOtherWayRound(): void
    {
        $meta = new FileMeta('/project/src/Invoice.php', 12, 1);
        $edge = new MethodEdge(
            new ClassNode(ClassNodeId::of('App\Domain\Invoice'), true, $meta),
            new MethodNode(MethodNodeId::of('App\Domain\Invoice', 'total'), true, $meta),
            $meta,
        );

        $inverted = $edge->invert();

        self::assertInstanceOf(DeclaredInEdge::class, $inverted);
        self::assertSame('App\Domain\Invoice::total', $inverted->from()->toString());
        self::assertSame('App\Domain\Invoice', $inverted->to()->toString());
        self::assertSame($meta, $inverted->meta());
    }

    public function testInvertTwiceYieldsTheEdgeItStartedFrom(): void
    {
        $meta = new FileMeta('/project/src/Invoice.php', 12, 1);
        $edge = new MethodEdge(
            new ClassNode(ClassNodeId::of('App\Domain\Invoice'), true, $meta),
            new MethodNode(MethodNodeId::of('App\Domain\Invoice', 'total'), true, $meta),
            $meta,
        );

        self::assertSame($edge, $edge->invert()->invert());
    }
}
