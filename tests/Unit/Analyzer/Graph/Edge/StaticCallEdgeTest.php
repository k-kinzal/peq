<?php

declare(strict_types=1);

namespace Tests\Unit\Analyzer\Graph\Edge;

use App\Analyzer\Graph\Edge\StaticCallEdge;
use App\Analyzer\Graph\Edge\UsedByEdge;
use App\Analyzer\Graph\EdgeKind;
use App\Analyzer\Graph\FileMeta;
use App\Analyzer\Graph\Node\FunctionNode;
use App\Analyzer\Graph\Node\MethodNode;
use App\Analyzer\Graph\NodeId\FunctionNodeId;
use App\Analyzer\Graph\NodeId\MethodNodeId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(StaticCallEdge::class)]
#[UsesClass(UsedByEdge::class)]
#[UsesClass(FileMeta::class)]
#[UsesClass(FunctionNodeId::class)]
#[UsesClass(MethodNodeId::class)]
#[UsesClass(FunctionNode::class)]
#[UsesClass(MethodNode::class)]
#[UsesClass(\App\Analyzer\Graph\QualifiedName::class)]
#[Small]
final class StaticCallEdgeTest extends TestCase
{
    public function testKindReportsTheRelationItRecords(): void
    {
        $meta = new FileMeta('/project/src/Invoice.php', 12, 1);
        $edge = new StaticCallEdge(
            new FunctionNode(FunctionNodeId::of('App\Domain\formatMoney'), true, $meta),
            new MethodNode(MethodNodeId::of('App\Domain\Invoice', 'total'), true, $meta),
            $meta,
        );

        self::assertSame(EdgeKind::StaticCall, $edge->kind());
    }

    public function testFromNamesTheSymbolTheRelationStartsAt(): void
    {
        $meta = new FileMeta('/project/src/Invoice.php', 12, 1);
        $edge = new StaticCallEdge(
            new FunctionNode(FunctionNodeId::of('App\Domain\formatMoney'), true, $meta),
            new MethodNode(MethodNodeId::of('App\Domain\Invoice', 'total'), true, $meta),
            $meta,
        );

        self::assertSame('App\Domain\formatMoney', $edge->from()->toString());
    }

    public function testToNamesTheSymbolTheRelationPointsAt(): void
    {
        $meta = new FileMeta('/project/src/Invoice.php', 12, 1);
        $edge = new StaticCallEdge(
            new FunctionNode(FunctionNodeId::of('App\Domain\formatMoney'), true, $meta),
            new MethodNode(MethodNodeId::of('App\Domain\Invoice', 'total'), true, $meta),
            $meta,
        );

        self::assertSame('App\Domain\Invoice::total', $edge->to()->toString());
    }

    public function testMetaReturnsWhereTheRelationIsWritten(): void
    {
        $meta = new FileMeta('/project/src/Invoice.php', 12, 1);
        $edge = new StaticCallEdge(
            new FunctionNode(FunctionNodeId::of('App\Domain\formatMoney'), true, $meta),
            new MethodNode(MethodNodeId::of('App\Domain\Invoice', 'total'), true, $meta),
            $meta,
        );

        self::assertSame($meta, $edge->meta());
    }

    public function testInvertReadsTheRelationTheOtherWayRound(): void
    {
        $meta = new FileMeta('/project/src/Invoice.php', 12, 1);
        $edge = new StaticCallEdge(
            new FunctionNode(FunctionNodeId::of('App\Domain\formatMoney'), true, $meta),
            new MethodNode(MethodNodeId::of('App\Domain\Invoice', 'total'), true, $meta),
            $meta,
        );

        $inverted = $edge->invert();

        self::assertInstanceOf(UsedByEdge::class, $inverted);
        self::assertSame('App\Domain\Invoice::total', $inverted->from()->toString());
        self::assertSame('App\Domain\formatMoney', $inverted->to()->toString());
        self::assertSame($meta, $inverted->meta());
    }

    public function testInvertTwiceYieldsTheEdgeItStartedFrom(): void
    {
        $meta = new FileMeta('/project/src/Invoice.php', 12, 1);
        $edge = new StaticCallEdge(
            new FunctionNode(FunctionNodeId::of('App\Domain\formatMoney'), true, $meta),
            new MethodNode(MethodNodeId::of('App\Domain\Invoice', 'total'), true, $meta),
            $meta,
        );

        self::assertSame($edge, $edge->invert()->invert());
    }
}
