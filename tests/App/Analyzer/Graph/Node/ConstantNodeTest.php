<?php

declare(strict_types=1);

namespace Tests\App\Analyzer\Graph\Node;

use App\Analyzer\Graph\FileMeta;
use App\Analyzer\Graph\Node\ConstantNode;
use App\Analyzer\Graph\NodeId\ConstantNodeId;
use App\Analyzer\Graph\NodeKind;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
final class ConstantNodeTest extends TestCase
{
    #[Test]
    public function testConstruct(): void
    {
        $id = new ConstantNodeId('App', 'MyClass', 'MY_CONSTANT');
        $meta = new FileMeta('/path/to/file.php', 10, 5);

        $node = new ConstantNode($id, true, $meta);

        self::assertSame($id, $node->id());
        self::assertSame(NodeKind::Constant, $node->kind());
        self::assertSame($meta, $node->meta());
        self::assertTrue($node->resolved());
    }

    #[Test]
    public function testConstructWithDefaults(): void
    {
        $id = new ConstantNodeId('App', 'MyClass', 'MY_CONSTANT');
        $node = new ConstantNode($id);

        self::assertNull($node->meta());
        self::assertFalse($node->resolved());
    }
}
