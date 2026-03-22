<?php

declare(strict_types=1);

namespace Tests\Unit\Analyzer\Graph\Node;

use App\Analyzer\Graph\FileMeta;
use App\Analyzer\Graph\Node\EnumNode;
use App\Analyzer\Graph\NodeId\EnumNodeId;
use App\Analyzer\Graph\NodeKind;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
final class EnumNodeTest extends TestCase
{
    #[Test]
    public function testConstruct(): void
    {
        $id = new EnumNodeId('App', 'MyEnum');
        $meta = new FileMeta('/path/to/file.php', 10, 5);

        $node = new EnumNode($id, true, $meta);

        self::assertSame($id, $node->id());
        self::assertSame(NodeKind::Enum, $node->kind());
        self::assertSame($meta, $node->meta());
        self::assertTrue($node->resolved());
    }

    #[Test]
    public function testConstructWithDefaults(): void
    {
        $id = new EnumNodeId('App', 'MyEnum');
        $node = new EnumNode($id);

        self::assertNull($node->meta());
        self::assertFalse($node->resolved());
    }
}
