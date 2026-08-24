<?php

declare(strict_types=1);

namespace Tests\Unit\Analyzer\Graph\Edge;

use App\Analyzer\Graph\Edge\StaticPropertyAccessEdge;
use App\Analyzer\Graph\Edge\UsedByEdge;
use App\Analyzer\Graph\EdgeKind;
use App\Analyzer\Graph\FileMeta;
use App\Analyzer\Graph\Node\FunctionNode;
use App\Analyzer\Graph\Node\PropertyNode;
use App\Analyzer\Graph\NodeId\FunctionNodeId;
use App\Analyzer\Graph\NodeId\PropertyNodeId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(StaticPropertyAccessEdge::class)]
#[UsesClass(UsedByEdge::class)]
#[UsesClass(FileMeta::class)]
#[UsesClass(FunctionNodeId::class)]
#[UsesClass(PropertyNodeId::class)]
#[UsesClass(FunctionNode::class)]
#[UsesClass(PropertyNode::class)]
#[UsesClass(\App\Analyzer\Graph\QualifiedName::class)]
#[Small]
final class StaticPropertyAccessEdgeTest extends TestCase
{
    public function testKindReportsTheRelationItRecords(): void
    {
        $meta = new FileMeta('/project/src/Invoice.php', 12, 1);
        $edge = new StaticPropertyAccessEdge(
            new FunctionNode(FunctionNodeId::of('App\Domain\formatMoney'), true, $meta),
            new PropertyNode(PropertyNodeId::of('App\Domain\Invoice', 'lines'), true, $meta),
            $meta,
        );

        self::assertSame(EdgeKind::StaticPropertyAccess, $edge->kind());
    }

    public function testFromNamesTheSymbolTheRelationStartsAt(): void
    {
        $meta = new FileMeta('/project/src/Invoice.php', 12, 1);
        $edge = new StaticPropertyAccessEdge(
            new FunctionNode(FunctionNodeId::of('App\Domain\formatMoney'), true, $meta),
            new PropertyNode(PropertyNodeId::of('App\Domain\Invoice', 'lines'), true, $meta),
            $meta,
        );

        self::assertSame('App\Domain\formatMoney', $edge->from()->toString());
    }

    public function testToNamesTheSymbolTheRelationPointsAt(): void
    {
        $meta = new FileMeta('/project/src/Invoice.php', 12, 1);
        $edge = new StaticPropertyAccessEdge(
            new FunctionNode(FunctionNodeId::of('App\Domain\formatMoney'), true, $meta),
            new PropertyNode(PropertyNodeId::of('App\Domain\Invoice', 'lines'), true, $meta),
            $meta,
        );

        self::assertSame('App\Domain\Invoice::lines', $edge->to()->toString());
    }

    public function testMetaReturnsWhereTheRelationIsWritten(): void
    {
        $meta = new FileMeta('/project/src/Invoice.php', 12, 1);
        $edge = new StaticPropertyAccessEdge(
            new FunctionNode(FunctionNodeId::of('App\Domain\formatMoney'), true, $meta),
            new PropertyNode(PropertyNodeId::of('App\Domain\Invoice', 'lines'), true, $meta),
            $meta,
        );

        self::assertSame($meta, $edge->meta());
    }

    public function testInvertReadsTheRelationTheOtherWayRound(): void
    {
        $meta = new FileMeta('/project/src/Invoice.php', 12, 1);
        $edge = new StaticPropertyAccessEdge(
            new FunctionNode(FunctionNodeId::of('App\Domain\formatMoney'), true, $meta),
            new PropertyNode(PropertyNodeId::of('App\Domain\Invoice', 'lines'), true, $meta),
            $meta,
        );

        $inverted = $edge->invert();

        self::assertInstanceOf(UsedByEdge::class, $inverted);
        self::assertSame('App\Domain\Invoice::lines', $inverted->from()->toString());
        self::assertSame('App\Domain\formatMoney', $inverted->to()->toString());
        self::assertSame($meta, $inverted->meta());
    }

    public function testInvertTwiceYieldsTheEdgeItStartedFrom(): void
    {
        $meta = new FileMeta('/project/src/Invoice.php', 12, 1);
        $edge = new StaticPropertyAccessEdge(
            new FunctionNode(FunctionNodeId::of('App\Domain\formatMoney'), true, $meta),
            new PropertyNode(PropertyNodeId::of('App\Domain\Invoice', 'lines'), true, $meta),
            $meta,
        );

        self::assertSame($edge, $edge->invert()->invert());
    }
}
