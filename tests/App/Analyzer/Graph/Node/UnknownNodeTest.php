<?php

declare(strict_types=1);

namespace Tests\App\Analyzer\Graph\Node;

use App\Analyzer\Graph\FileMeta;
use App\Analyzer\Graph\Node\UnknownNode;
use App\Analyzer\Graph\NodeId\UnknownNodeId;
use App\Analyzer\Graph\NodeKind;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
final class UnknownNodeTest extends TestCase
{
    #[Test]
    public function testConstruct(): void
    {
        $id = new UnknownNodeId('UnknownType');
        $meta = new FileMeta('/path/to/file.php', 10, 5);

        $node = new UnknownNode($id, true, $meta);

        self::assertSame($id, $node->id());
        self::assertSame(NodeKind::Unknown, $node->kind());
        self::assertSame($meta, $node->meta());
        self::assertTrue($node->resolved());
    }

    #[Test]
    public function testConstructWithDefaults(): void
    {
        $id = new UnknownNodeId('App\Unknown');
        $node = new UnknownNode($id);

        self::assertNull($node->meta());
        self::assertFalse($node->resolved());
    }
}
