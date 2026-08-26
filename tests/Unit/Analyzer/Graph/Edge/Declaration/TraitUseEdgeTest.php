<?php

declare(strict_types=1);

namespace Tests\Unit\Analyzer\Graph\Edge\Declaration;

use App\Analyzer\Graph\Edge\Declaration\TraitUseEdge;
use App\Analyzer\Graph\Edge\Inverse\DeclaredInEdge;
use App\Analyzer\Graph\EdgeKind;
use App\Analyzer\Graph\FileMeta;
use App\Analyzer\Graph\Node\ClassNode;
use App\Analyzer\Graph\Node\TraitNode;
use App\Analyzer\Graph\NodeId\ClassNodeId;
use App\Analyzer\Graph\NodeId\TraitNodeId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(TraitUseEdge::class)]
#[UsesClass(DeclaredInEdge::class)]
#[UsesClass(FileMeta::class)]
#[UsesClass(ClassNodeId::class)]
#[UsesClass(TraitNodeId::class)]
#[UsesClass(ClassNode::class)]
#[UsesClass(TraitNode::class)]
#[UsesClass(\App\Analyzer\Graph\QualifiedName::class)]
#[Small]
final class TraitUseEdgeTest extends TestCase
{
    public function testKindReportsTheRelationItRecords(): void
    {
        $meta = new FileMeta('/project/src/Invoice.php', 12, 1);
        $edge = new TraitUseEdge(
            new ClassNode(ClassNodeId::of('App\Domain\Invoice'), true, $meta),
            new TraitNode(TraitNodeId::of('App\Domain\Timestamped'), true, $meta),
            $meta,
        );

        self::assertSame(EdgeKind::DeclarationTraitUse, $edge->kind());
    }

    public function testFromNamesTheSymbolTheRelationStartsAt(): void
    {
        $meta = new FileMeta('/project/src/Invoice.php', 12, 1);
        $edge = new TraitUseEdge(
            new ClassNode(ClassNodeId::of('App\Domain\Invoice'), true, $meta),
            new TraitNode(TraitNodeId::of('App\Domain\Timestamped'), true, $meta),
            $meta,
        );

        self::assertSame('App\Domain\Invoice', $edge->from()->toString());
    }

    public function testToNamesTheSymbolTheRelationPointsAt(): void
    {
        $meta = new FileMeta('/project/src/Invoice.php', 12, 1);
        $edge = new TraitUseEdge(
            new ClassNode(ClassNodeId::of('App\Domain\Invoice'), true, $meta),
            new TraitNode(TraitNodeId::of('App\Domain\Timestamped'), true, $meta),
            $meta,
        );

        self::assertSame('App\Domain\Timestamped', $edge->to()->toString());
    }

    public function testMetaReturnsWhereTheRelationIsWritten(): void
    {
        $meta = new FileMeta('/project/src/Invoice.php', 12, 1);
        $edge = new TraitUseEdge(
            new ClassNode(ClassNodeId::of('App\Domain\Invoice'), true, $meta),
            new TraitNode(TraitNodeId::of('App\Domain\Timestamped'), true, $meta),
            $meta,
        );

        self::assertSame($meta, $edge->meta());
    }

    public function testInvertReadsTheRelationTheOtherWayRound(): void
    {
        $meta = new FileMeta('/project/src/Invoice.php', 12, 1);
        $edge = new TraitUseEdge(
            new ClassNode(ClassNodeId::of('App\Domain\Invoice'), true, $meta),
            new TraitNode(TraitNodeId::of('App\Domain\Timestamped'), true, $meta),
            $meta,
        );

        $inverted = $edge->invert();

        self::assertInstanceOf(DeclaredInEdge::class, $inverted);
        self::assertSame('App\Domain\Timestamped', $inverted->from()->toString());
        self::assertSame('App\Domain\Invoice', $inverted->to()->toString());
        self::assertSame($meta, $inverted->meta());
    }

    public function testInvertTwiceYieldsTheEdgeItStartedFrom(): void
    {
        $meta = new FileMeta('/project/src/Invoice.php', 12, 1);
        $edge = new TraitUseEdge(
            new ClassNode(ClassNodeId::of('App\Domain\Invoice'), true, $meta),
            new TraitNode(TraitNodeId::of('App\Domain\Timestamped'), true, $meta),
            $meta,
        );

        self::assertSame($edge, $edge->invert()->invert());
    }
}
