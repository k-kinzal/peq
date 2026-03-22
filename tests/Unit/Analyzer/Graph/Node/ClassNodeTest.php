<?php

declare(strict_types=1);

namespace Tests\Unit\Analyzer\Graph\Node;

use App\Analyzer\Graph\FileMeta;
use App\Analyzer\Graph\Node\ClassNode;
use App\Analyzer\Graph\NodeId\ClassNodeId;
use App\Analyzer\Graph\NodeKind;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
final class ClassNodeTest extends TestCase
{
    #[Test]
    public function testConstruct(): void
    {
        $id = new ClassNodeId('App', 'MyClass');
        $meta = new FileMeta('/path/to/file.php', 10, 5);

        $node = new ClassNode($id, true, $meta);

        self::assertSame($id, $node->id());
        self::assertSame(NodeKind::Klass, $node->kind());
        self::assertSame($meta, $node->meta());
        self::assertTrue($node->resolved());
    }

    #[Test]
    public function testConstructWithDefaults(): void
    {
        $id = new ClassNodeId('App', 'MyClass');
        $node = new ClassNode($id);

        self::assertNull($node->meta());
        self::assertFalse($node->resolved());
    }
}
