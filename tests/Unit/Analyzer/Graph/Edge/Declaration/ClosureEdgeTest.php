<?php

declare(strict_types=1);

namespace Tests\Unit\Analyzer\Graph\Edge\Declaration;

use App\Analyzer\Graph\Declaration\SymbolDeclaration;
use App\Analyzer\Graph\Edge\Declaration\ClosureEdge;
use App\Analyzer\Graph\EdgeKind;
use App\Analyzer\Graph\FileMeta;
use App\Analyzer\Graph\Node\ClosureNode;
use App\Analyzer\Graph\Node\FunctionNode;
use App\Analyzer\Graph\NodeId\ClosureNodeId;
use App\Analyzer\Graph\NodeId\FunctionNodeId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesNamespace;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(ClosureEdge::class)]
#[UsesNamespace('App')]
#[Small]
final class ClosureEdgeTest extends TestCase
{
    public function testClosureContainmentInvertsAsADeclaration(): void
    {
        $owner = new FunctionNode(FunctionNodeId::of('A'));
        $meta = new FileMeta('/source.php', 2, 3, 20);
        $closure = new ClosureNode(new ClosureNodeId('A', 2, 3), $meta, $owner, new SymbolDeclaration());
        $edge = new ClosureEdge($owner, $closure);

        self::assertSame('A', $edge->from()->toString());
        self::assertSame('A{closure@2:3}', $edge->to()->toString());
        self::assertSame(EdgeKind::DeclarationClosure, $edge->kind());
        self::assertSame($meta, $edge->meta());
        self::assertSame(EdgeKind::DeclaredIn, $edge->invert()->kind());
        self::assertSame($edge, $edge->invert()->invert());
    }

    public function testKindRetainsContainment(): void
    {
        $owner = new FunctionNode(FunctionNodeId::of('A'));
        $meta = new FileMeta('/source.php', 2, 3, 20);
        $declaration = new SymbolDeclaration();
        $closure = new ClosureNode(new ClosureNodeId('A', 2, 3), $meta, $owner, $declaration);
        $edge = new ClosureEdge($owner, $closure);
        self::assertSame(EdgeKind::DeclarationClosure, $edge->kind());
    }

    public function testInvertRetainsContainment(): void
    {
        $owner = new FunctionNode(FunctionNodeId::of('A'));
        $meta = new FileMeta('/source.php', 2, 3, 20);
        $declaration = new SymbolDeclaration();
        $closure = new ClosureNode(new ClosureNodeId('A', 2, 3), $meta, $owner, $declaration);
        $edge = new ClosureEdge($owner, $closure);
        self::assertSame($edge, $edge->invert()->invert());
    }
}
