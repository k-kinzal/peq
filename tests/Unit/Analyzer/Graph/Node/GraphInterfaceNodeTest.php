<?php

declare(strict_types=1);

namespace Tests\Unit\Analyzer\Graph\Node;

use App\Analyzer\Graph\FileMeta;
use App\Analyzer\Graph\Node\GraphInterfaceNode;
use App\Analyzer\Graph\NodeId\InterfaceNodeId;
use App\Analyzer\Graph\NodeKind;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(GraphInterfaceNode::class)]
#[UsesClass(FileMeta::class)]
#[UsesClass(InterfaceNodeId::class)]
#[UsesClass(\App\Analyzer\Graph\QualifiedName::class)]
#[Small]
final class GraphInterfaceNodeTest extends TestCase
{
    public function testIdReturnsTheIdentifierItWasBuiltWith(): void
    {
        $id = new InterfaceNodeId('App\Domain', 'Payable');

        self::assertSame($id, (new GraphInterfaceNode($id))->id());
    }

    public function testKindReportsTheSymbolItStandsFor(): void
    {
        self::assertSame(NodeKind::Interface, (new GraphInterfaceNode(new InterfaceNodeId('App\Domain', 'Payable')))->kind());
    }

    public function testResolvedReportsWhatAnalysisEstablished(): void
    {
        self::assertTrue((new GraphInterfaceNode(new InterfaceNodeId('App\Domain', 'Payable'), true))->resolved());
    }

    public function testResolvedIsFalseUntilAnalysisEstablishesOtherwise(): void
    {
        self::assertFalse((new GraphInterfaceNode(new InterfaceNodeId('App\Domain', 'Payable')))->resolved());
    }

    public function testMetaReturnsWhereTheSymbolIsDeclared(): void
    {
        $meta = new FileMeta('/project/src/Invoice.php', 10, 5);

        self::assertSame($meta, (new GraphInterfaceNode(new InterfaceNodeId('App\Domain', 'Payable'), true, $meta))->meta());
    }

    public function testMetaIsNullForASymbolWithNoKnownLocation(): void
    {
        self::assertNull((new GraphInterfaceNode(new InterfaceNodeId('App\Domain', 'Payable')))->meta());
    }
}
