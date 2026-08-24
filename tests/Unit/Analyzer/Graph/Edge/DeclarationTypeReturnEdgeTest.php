<?php

declare(strict_types=1);

namespace Tests\Unit\Analyzer\Graph\Edge;

use App\Analyzer\Graph\Edge\DeclarationTypeReturnEdge;
use App\Analyzer\Graph\Edge\DeclaredInEdge;
use App\Analyzer\Graph\EdgeKind;
use App\Analyzer\Graph\FileMeta;
use App\Analyzer\Graph\Node\BuiltinNode;
use App\Analyzer\Graph\Node\FunctionNode;
use App\Analyzer\Graph\NodeId\BuiltinNodeId;
use App\Analyzer\Graph\NodeId\FunctionNodeId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(DeclarationTypeReturnEdge::class)]
#[UsesClass(DeclaredInEdge::class)]
#[UsesClass(FileMeta::class)]
#[UsesClass(BuiltinNodeId::class)]
#[UsesClass(FunctionNodeId::class)]
#[UsesClass(BuiltinNode::class)]
#[UsesClass(FunctionNode::class)]
#[UsesClass(\App\Analyzer\Graph\QualifiedName::class)]
#[Small]
final class DeclarationTypeReturnEdgeTest extends TestCase
{
    public function testKindReportsTheRelationItRecords(): void
    {
        $meta = new FileMeta('/project/src/Invoice.php', 12, 1);
        $edge = new DeclarationTypeReturnEdge(
            new FunctionNode(FunctionNodeId::of('App\Domain\formatMoney'), true, $meta),
            new BuiltinNode(BuiltinNodeId::of('int'), true, $meta),
            $meta,
        );

        self::assertSame(EdgeKind::DeclarationTypeReturn, $edge->kind());
    }

    public function testFromNamesTheSymbolTheRelationStartsAt(): void
    {
        $meta = new FileMeta('/project/src/Invoice.php', 12, 1);
        $edge = new DeclarationTypeReturnEdge(
            new FunctionNode(FunctionNodeId::of('App\Domain\formatMoney'), true, $meta),
            new BuiltinNode(BuiltinNodeId::of('int'), true, $meta),
            $meta,
        );

        self::assertSame('App\Domain\formatMoney', $edge->from()->toString());
    }

    public function testToNamesTheSymbolTheRelationPointsAt(): void
    {
        $meta = new FileMeta('/project/src/Invoice.php', 12, 1);
        $edge = new DeclarationTypeReturnEdge(
            new FunctionNode(FunctionNodeId::of('App\Domain\formatMoney'), true, $meta),
            new BuiltinNode(BuiltinNodeId::of('int'), true, $meta),
            $meta,
        );

        self::assertSame('int', $edge->to()->toString());
    }

    public function testMetaReturnsWhereTheRelationIsWritten(): void
    {
        $meta = new FileMeta('/project/src/Invoice.php', 12, 1);
        $edge = new DeclarationTypeReturnEdge(
            new FunctionNode(FunctionNodeId::of('App\Domain\formatMoney'), true, $meta),
            new BuiltinNode(BuiltinNodeId::of('int'), true, $meta),
            $meta,
        );

        self::assertSame($meta, $edge->meta());
    }

    public function testInvertReadsTheRelationTheOtherWayRound(): void
    {
        $meta = new FileMeta('/project/src/Invoice.php', 12, 1);
        $edge = new DeclarationTypeReturnEdge(
            new FunctionNode(FunctionNodeId::of('App\Domain\formatMoney'), true, $meta),
            new BuiltinNode(BuiltinNodeId::of('int'), true, $meta),
            $meta,
        );

        $inverted = $edge->invert();

        self::assertInstanceOf(DeclaredInEdge::class, $inverted);
        self::assertSame('int', $inverted->from()->toString());
        self::assertSame('App\Domain\formatMoney', $inverted->to()->toString());
        self::assertSame($meta, $inverted->meta());
    }

    public function testInvertTwiceYieldsTheEdgeItStartedFrom(): void
    {
        $meta = new FileMeta('/project/src/Invoice.php', 12, 1);
        $edge = new DeclarationTypeReturnEdge(
            new FunctionNode(FunctionNodeId::of('App\Domain\formatMoney'), true, $meta),
            new BuiltinNode(BuiltinNodeId::of('int'), true, $meta),
            $meta,
        );

        self::assertSame($edge, $edge->invert()->invert());
    }
}
