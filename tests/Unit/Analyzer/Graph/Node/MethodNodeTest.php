<?php

declare(strict_types=1);

namespace Tests\Unit\Analyzer\Graph\Node;

use App\Analyzer\Graph\FileMeta;
use App\Analyzer\Graph\Node\MethodNode;
use App\Analyzer\Graph\NodeId\MethodNodeId;
use App\Analyzer\Graph\NodeKind;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
final class MethodNodeTest extends TestCase
{
    #[Test]
    public function testConstruct(): void
    {
        $id = new MethodNodeId('App', 'MyClass', 'myMethod');
        $meta = new FileMeta('/path/to/file.php', 10, 5);

        $node = new MethodNode($id, true, $meta);

        self::assertSame($id, $node->id());
        self::assertSame(NodeKind::Method, $node->kind());
        self::assertSame($meta, $node->meta());
        self::assertTrue($node->resolved());
    }

    #[Test]
    public function testConstructWithDefaults(): void
    {
        $id = new MethodNodeId('App', 'MyClass', 'myMethod');
        $node = new MethodNode($id);

        self::assertNull($node->meta());
        self::assertFalse($node->resolved());
    }
}
