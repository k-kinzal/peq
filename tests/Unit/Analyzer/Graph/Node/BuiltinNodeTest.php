<?php

declare(strict_types=1);

namespace Tests\Unit\Analyzer\Graph\Node;

use App\Analyzer\Graph\FileMeta;
use App\Analyzer\Graph\Node\BuiltinNode;
use App\Analyzer\Graph\NodeId\BuiltinNodeId;
use App\Analyzer\Graph\NodeKind;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
final class BuiltinNodeTest extends TestCase
{
    #[Test]
    public function testConstruct(): void
    {
        $id = new BuiltinNodeId('Builtin', 'StringType');
        $meta = new FileMeta('/path/to/file.php', 10, 5);

        $node = new BuiltinNode($id, true, $meta);

        self::assertSame($id, $node->id());
        self::assertSame(NodeKind::Builtin, $node->kind());
        self::assertSame($meta, $node->meta());
        self::assertTrue($node->resolved());
    }

    #[Test]
    public function testConstructWithDefaults(): void
    {
        $id = new BuiltinNodeId('Builtin', 'string');
        $node = new BuiltinNode($id);

        self::assertNull($node->meta());
        self::assertFalse($node->resolved());
    }
}
