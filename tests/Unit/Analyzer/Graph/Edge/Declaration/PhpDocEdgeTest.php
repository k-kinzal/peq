<?php

declare(strict_types=1);

namespace Tests\Unit\Analyzer\Graph\Edge\Declaration;

use App\Analyzer\Graph\Edge\Declaration\PhpDocEdge;
use App\Analyzer\Graph\EdgeKind;
use App\Analyzer\Graph\FileMeta;
use App\Analyzer\Graph\Node\ClassNode;
use App\Analyzer\Graph\NodeId\ClassNodeId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesNamespace;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(PhpDocEdge::class)]
#[UsesNamespace('App\Analyzer\Graph')]
#[Small]
final class PhpDocEdgeTest extends TestCase
{
    public function testKindDistinguishesDocumentationFromRuntimeRelations(): void
    {
        $edge = new PhpDocEdge(new ClassNode(ClassNodeId::of('Subject')), new ClassNode(ClassNodeId::of('Item')), new FileMeta('/subject.php', 7, 1));

        self::assertSame(EdgeKind::PhpDoc, $edge->kind());
    }

    public function testInvertPreservesTheSourceAndReverseReading(): void
    {
        $source = new ClassNode(ClassNodeId::of('Subject'));
        $target = new ClassNode(ClassNodeId::of('Item'));
        $meta = new FileMeta('/subject.php', 7, 1);

        $edge = new PhpDocEdge($source, $target, $meta);

        self::assertSame(EdgeKind::PhpDoc, $edge->kind());
        self::assertSame('Subject', $edge->from()->toString());
        self::assertSame('Item', $edge->to()->toString());
        self::assertSame($meta, $edge->meta());
        self::assertSame(EdgeKind::DeclaredIn, $edge->invert()->kind());
        self::assertSame('Item', $edge->invert()->from()->toString());
        self::assertSame('Subject', $edge->invert()->to()->toString());
        self::assertSame($edge, $edge->invert()->invert());
    }
}
