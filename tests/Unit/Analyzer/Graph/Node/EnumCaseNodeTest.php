<?php

declare(strict_types=1);

namespace Tests\Unit\Analyzer\Graph\Node;

use App\Analyzer\Graph\FileMeta;
use App\Analyzer\Graph\Node\EnumCaseNode;
use App\Analyzer\Graph\NodeId\EnumCaseNodeId;
use App\Analyzer\Graph\NodeKind;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(EnumCaseNode::class)]
#[UsesClass(FileMeta::class)]
#[UsesClass(EnumCaseNodeId::class)]
#[UsesClass(\App\Analyzer\Graph\QualifiedName::class)]
#[Small]
final class EnumCaseNodeTest extends TestCase
{
    public function testIdReturnsTheIdentifierItWasBuiltWith(): void
    {
        $id = new EnumCaseNodeId('App\Domain', 'InvoiceState', 'OPEN');

        self::assertSame($id, (new EnumCaseNode($id))->id());
    }

    public function testKindReportsTheSymbolItStandsFor(): void
    {
        self::assertSame(NodeKind::EnumCase, (new EnumCaseNode(new EnumCaseNodeId('App\Domain', 'InvoiceState', 'OPEN')))->kind());
    }

    public function testResolvedReportsWhatAnalysisEstablished(): void
    {
        self::assertTrue((new EnumCaseNode(new EnumCaseNodeId('App\Domain', 'InvoiceState', 'OPEN'), true))->resolved());
    }

    public function testResolvedIsFalseUntilAnalysisEstablishesOtherwise(): void
    {
        self::assertFalse((new EnumCaseNode(new EnumCaseNodeId('App\Domain', 'InvoiceState', 'OPEN')))->resolved());
    }

    public function testMetaReturnsWhereTheSymbolIsDeclared(): void
    {
        $meta = new FileMeta('/project/src/Invoice.php', 10, 5);

        self::assertSame($meta, (new EnumCaseNode(new EnumCaseNodeId('App\Domain', 'InvoiceState', 'OPEN'), true, $meta))->meta());
    }

    public function testMetaIsNullForASymbolWithNoKnownLocation(): void
    {
        self::assertNull((new EnumCaseNode(new EnumCaseNodeId('App\Domain', 'InvoiceState', 'OPEN')))->meta());
    }
}
