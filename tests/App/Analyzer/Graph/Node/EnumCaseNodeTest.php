<?php

declare(strict_types=1);

namespace Tests\App\Analyzer\Graph\Node;

use App\Analyzer\Graph\FileMeta;
use App\Analyzer\Graph\Node\EnumCaseNode;
use App\Analyzer\Graph\NodeId\EnumCaseNodeId;
use App\Analyzer\Graph\NodeKind;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
final class EnumCaseNodeTest extends TestCase
{
    #[Test]
    public function testConstruct(): void
    {
        $id = new EnumCaseNodeId('App', 'MyEnum', 'CASE_ONE');
        $meta = new FileMeta('/path/to/file.php', 10, 5);

        $node = new EnumCaseNode($id, true, $meta);

        self::assertSame($id, $node->id());
        self::assertSame(NodeKind::EnumCase, $node->kind());
        self::assertSame($meta, $node->meta());
        self::assertTrue($node->resolved());
    }

    #[Test]
    public function testConstructWithDefaults(): void
    {
        $id = new EnumCaseNodeId('App', 'MyEnum', 'CASE_ONE');
        $node = new EnumCaseNode($id);

        self::assertNull($node->meta());
        self::assertFalse($node->resolved());
    }
}
