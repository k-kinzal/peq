<?php

declare(strict_types=1);

namespace Tests\App\Analyzer\Graph\Node;

use App\Analyzer\Graph\FileMeta;
use App\Analyzer\Graph\Node\PropertyNode;
use App\Analyzer\Graph\NodeId\PropertyNodeId;
use App\Analyzer\Graph\NodeKind;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
final class PropertyNodeTest extends TestCase
{
    #[Test]
    public function testConstruct(): void
    {
        $id = new PropertyNodeId('App', 'MyClass', 'myProperty');
        $meta = new FileMeta('/path/to/file.php', 10, 5);

        $node = new PropertyNode($id, true, $meta);

        self::assertSame($id, $node->id());
        self::assertSame(NodeKind::Property, $node->kind());
        self::assertSame($meta, $node->meta());
        self::assertTrue($node->resolved());
    }

    #[Test]
    public function testConstructWithDefaults(): void
    {
        $id = new PropertyNodeId('App', 'MyClass', 'myProperty');
        $node = new PropertyNode($id);

        self::assertNull($node->meta());
        self::assertFalse($node->resolved());
    }
}
