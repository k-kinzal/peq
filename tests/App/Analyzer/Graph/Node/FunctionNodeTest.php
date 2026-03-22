<?php

declare(strict_types=1);

namespace Tests\App\Analyzer\Graph\Node;

use App\Analyzer\Graph\FileMeta;
use App\Analyzer\Graph\Node\FunctionNode;
use App\Analyzer\Graph\NodeId\FunctionNodeId;
use App\Analyzer\Graph\NodeKind;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
final class FunctionNodeTest extends TestCase
{
    #[Test]
    public function testConstruct(): void
    {
        $id = new FunctionNodeId('App', 'myFunction');
        $meta = new FileMeta('/path/to/file.php', 10, 5);

        $node = new FunctionNode($id, true, $meta);

        self::assertSame($id, $node->id());
        self::assertSame(NodeKind::Function, $node->kind());
        self::assertSame($meta, $node->meta());
        self::assertTrue($node->resolved());
    }

    #[Test]
    public function testConstructWithDefaults(): void
    {
        $id = new FunctionNodeId('App', 'myFunction');
        $node = new FunctionNode($id);

        self::assertNull($node->meta());
        self::assertFalse($node->resolved());
    }
}
