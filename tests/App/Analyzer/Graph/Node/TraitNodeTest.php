<?php

declare(strict_types=1);

namespace Tests\App\Analyzer\Graph\Node;

use App\Analyzer\Graph\FileMeta;
use App\Analyzer\Graph\Node\TraitNode;
use App\Analyzer\Graph\NodeId\TraitNodeId;
use App\Analyzer\Graph\NodeKind;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
final class TraitNodeTest extends TestCase
{
    #[Test]
    public function testConstruct(): void
    {
        $id = new TraitNodeId('App', 'MyTrait');
        $meta = new FileMeta('/path/to/file.php', 10, 5);

        $node = new TraitNode($id, true, $meta);

        self::assertSame($id, $node->id());
        self::assertSame(NodeKind::Trait, $node->kind());
        self::assertSame($meta, $node->meta());
        self::assertTrue($node->resolved());
    }

    #[Test]
    public function testConstructWithDefaults(): void
    {
        $id = new TraitNodeId('App', 'MyTrait');
        $node = new TraitNode($id);

        self::assertNull($node->meta());
        self::assertFalse($node->resolved());
    }
}
