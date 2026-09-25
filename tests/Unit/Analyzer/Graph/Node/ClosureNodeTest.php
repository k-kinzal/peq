<?php

declare(strict_types=1);

namespace Tests\Unit\Analyzer\Graph\Node;

use App\Analyzer\Graph\Declaration\SymbolDeclaration;
use App\Analyzer\Graph\FileMeta;
use App\Analyzer\Graph\Node\ClosureNode;
use App\Analyzer\Graph\Node\FunctionNode;
use App\Analyzer\Graph\NodeId\ClosureNodeId;
use App\Analyzer\Graph\NodeId\FunctionNodeId;
use App\Analyzer\Graph\NodeKind;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesNamespace;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(ClosureNode::class)]
#[UsesNamespace('App')]
#[Small]
final class ClosureNodeTest extends TestCase
{
    public function testClosureIsAResolvedCallableWithItsOwnLocationAndEnclosingSymbol(): void
    {
        $owner = new FunctionNode(FunctionNodeId::of('A'));
        $meta = new FileMeta('/source.php', 2, 3, 20);
        $declaration = new SymbolDeclaration();
        $closure = new ClosureNode(new ClosureNodeId('A', 2, 3), $meta, $owner, $declaration);

        self::assertSame('A{closure@2:3}', $closure->id()->toString());
        self::assertSame(NodeKind::Closure, $closure->kind());
        self::assertTrue($closure->resolved());
        self::assertSame($meta, $closure->meta());
        self::assertSame($owner, $closure->owner);
        self::assertSame($declaration, $closure->declaration());
    }

    public function testIdDescribesTheAnonymousCallable(): void
    {
        $owner = new FunctionNode(FunctionNodeId::of('A'));
        $meta = new FileMeta('/source.php', 2, 3, 20);
        $declaration = new SymbolDeclaration();
        $closure = new ClosureNode(new ClosureNodeId('A', 2, 3), $meta, $owner, $declaration);
        self::assertSame('A{closure@2:3}', $closure->id()->toString());
    }

    public function testKindDescribesTheAnonymousCallable(): void
    {
        $owner = new FunctionNode(FunctionNodeId::of('A'));
        $meta = new FileMeta('/source.php', 2, 3, 20);
        $declaration = new SymbolDeclaration();
        $closure = new ClosureNode(new ClosureNodeId('A', 2, 3), $meta, $owner, $declaration);
        self::assertSame(NodeKind::Closure, $closure->kind());
    }

    public function testResolvedDescribesTheAnonymousCallable(): void
    {
        $owner = new FunctionNode(FunctionNodeId::of('A'));
        $meta = new FileMeta('/source.php', 2, 3, 20);
        $declaration = new SymbolDeclaration();
        $closure = new ClosureNode(new ClosureNodeId('A', 2, 3), $meta, $owner, $declaration);
        self::assertTrue($closure->resolved());
    }

    public function testMetaDescribesTheAnonymousCallable(): void
    {
        $owner = new FunctionNode(FunctionNodeId::of('A'));
        $meta = new FileMeta('/source.php', 2, 3, 20);
        $declaration = new SymbolDeclaration();
        $closure = new ClosureNode(new ClosureNodeId('A', 2, 3), $meta, $owner, $declaration);
        self::assertSame($meta, $closure->meta());
    }

    public function testDeclarationDescribesTheAnonymousCallable(): void
    {
        $owner = new FunctionNode(FunctionNodeId::of('A'));
        $meta = new FileMeta('/source.php', 2, 3, 20);
        $declaration = new SymbolDeclaration();
        $closure = new ClosureNode(new ClosureNodeId('A', 2, 3), $meta, $owner, $declaration);
        self::assertSame($declaration, $closure->declaration());
    }
}
