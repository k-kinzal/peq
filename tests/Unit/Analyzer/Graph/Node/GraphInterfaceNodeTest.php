<?php

declare(strict_types=1);

namespace Tests\Unit\Analyzer\Graph\Node;

use App\Analyzer\Graph\FileMeta;
use App\Analyzer\Graph\Node\GraphInterfaceNode;
use App\Analyzer\Graph\NodeId\InterfaceNodeId;
use App\Analyzer\Graph\NodeKind;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
final class GraphInterfaceNodeTest extends TestCase
{
    #[Test]
    public function testConstruct(): void
    {
        $id = new InterfaceNodeId('App', 'MyInterface');
        $meta = new FileMeta('/path/to/file.php', 10, 5);

        $node = new GraphInterfaceNode($id, true, $meta);

        self::assertSame($id, $node->id());
        self::assertSame(NodeKind::Interface, $node->kind());
        self::assertSame($meta, $node->meta());
        self::assertTrue($node->resolved());
    }

    #[Test]
    public function testConstructWithDefaults(): void
    {
        $id = new InterfaceNodeId('App', 'MyInterface');
        $node = new GraphInterfaceNode($id);

        self::assertNull($node->meta());
        self::assertFalse($node->resolved());
    }
}
