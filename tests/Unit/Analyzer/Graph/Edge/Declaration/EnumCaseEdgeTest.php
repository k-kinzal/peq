<?php

declare(strict_types=1);

namespace Tests\Unit\Analyzer\Graph\Edge\Declaration;

use App\Analyzer\Graph\Edge\Declaration\EnumCaseEdge;
use App\Analyzer\Graph\Edge\Inverse\DeclaredInEdge;
use App\Analyzer\Graph\EdgeKind;
use App\Analyzer\Graph\FileMeta;
use App\Analyzer\Graph\Node\EnumCaseNode;
use App\Analyzer\Graph\Node\EnumNode;
use App\Analyzer\Graph\NodeId\EnumCaseNodeId;
use App\Analyzer\Graph\NodeId\EnumNodeId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(EnumCaseEdge::class)]
#[UsesClass(DeclaredInEdge::class)]
#[UsesClass(FileMeta::class)]
#[UsesClass(EnumCaseNodeId::class)]
#[UsesClass(EnumNodeId::class)]
#[UsesClass(EnumCaseNode::class)]
#[UsesClass(EnumNode::class)]
#[UsesClass(\App\Analyzer\Graph\QualifiedName::class)]
#[Small]
final class EnumCaseEdgeTest extends TestCase
{
    public function testKindReportsTheRelationItRecords(): void
    {
        $meta = new FileMeta('/project/src/Invoice.php', 12, 1);
        $edge = new EnumCaseEdge(
            new EnumNode(EnumNodeId::of('App\Domain\InvoiceState'), true, $meta),
            new EnumCaseNode(EnumCaseNodeId::of('App\Domain\InvoiceState', 'OPEN'), true, $meta),
            $meta,
        );

        self::assertSame(EdgeKind::DeclarationEnumCase, $edge->kind());
    }

    public function testFromNamesTheSymbolTheRelationStartsAt(): void
    {
        $meta = new FileMeta('/project/src/Invoice.php', 12, 1);
        $edge = new EnumCaseEdge(
            new EnumNode(EnumNodeId::of('App\Domain\InvoiceState'), true, $meta),
            new EnumCaseNode(EnumCaseNodeId::of('App\Domain\InvoiceState', 'OPEN'), true, $meta),
            $meta,
        );

        self::assertSame('App\Domain\InvoiceState', $edge->from()->toString());
    }

    public function testToNamesTheSymbolTheRelationPointsAt(): void
    {
        $meta = new FileMeta('/project/src/Invoice.php', 12, 1);
        $edge = new EnumCaseEdge(
            new EnumNode(EnumNodeId::of('App\Domain\InvoiceState'), true, $meta),
            new EnumCaseNode(EnumCaseNodeId::of('App\Domain\InvoiceState', 'OPEN'), true, $meta),
            $meta,
        );

        self::assertSame('App\Domain\InvoiceState::OPEN', $edge->to()->toString());
    }

    public function testMetaReturnsWhereTheRelationIsWritten(): void
    {
        $meta = new FileMeta('/project/src/Invoice.php', 12, 1);
        $edge = new EnumCaseEdge(
            new EnumNode(EnumNodeId::of('App\Domain\InvoiceState'), true, $meta),
            new EnumCaseNode(EnumCaseNodeId::of('App\Domain\InvoiceState', 'OPEN'), true, $meta),
            $meta,
        );

        self::assertSame($meta, $edge->meta());
    }

    public function testInvertReadsTheRelationTheOtherWayRound(): void
    {
        $meta = new FileMeta('/project/src/Invoice.php', 12, 1);
        $edge = new EnumCaseEdge(
            new EnumNode(EnumNodeId::of('App\Domain\InvoiceState'), true, $meta),
            new EnumCaseNode(EnumCaseNodeId::of('App\Domain\InvoiceState', 'OPEN'), true, $meta),
            $meta,
        );

        $inverted = $edge->invert();

        self::assertInstanceOf(DeclaredInEdge::class, $inverted);
        self::assertSame('App\Domain\InvoiceState::OPEN', $inverted->from()->toString());
        self::assertSame('App\Domain\InvoiceState', $inverted->to()->toString());
        self::assertSame($meta, $inverted->meta());
    }

    public function testInvertTwiceYieldsTheEdgeItStartedFrom(): void
    {
        $meta = new FileMeta('/project/src/Invoice.php', 12, 1);
        $edge = new EnumCaseEdge(
            new EnumNode(EnumNodeId::of('App\Domain\InvoiceState'), true, $meta),
            new EnumCaseNode(EnumCaseNodeId::of('App\Domain\InvoiceState', 'OPEN'), true, $meta),
            $meta,
        );

        self::assertSame($edge, $edge->invert()->invert());
    }
}
