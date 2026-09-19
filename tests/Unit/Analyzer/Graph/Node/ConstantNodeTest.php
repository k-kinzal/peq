<?php

declare(strict_types=1);

namespace Tests\Unit\Analyzer\Graph\Node;

use App\Analyzer\Graph\Declaration\Modifiers;
use App\Analyzer\Graph\Declaration\SymbolDeclaration;
use App\Analyzer\Graph\FileMeta;
use App\Analyzer\Graph\Node\ConstantNode;
use App\Analyzer\Graph\NodeId\ConstantNodeId;
use App\Analyzer\Graph\NodeKind;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(ConstantNode::class)]
#[UsesClass(Modifiers::class)]
#[UsesClass(SymbolDeclaration::class)]
#[UsesClass(FileMeta::class)]
#[UsesClass(ConstantNodeId::class)]
#[UsesClass(\App\Analyzer\Graph\QualifiedName::class)]
#[Small]
final class ConstantNodeTest extends TestCase
{
    public function testIdReturnsTheIdentifierItWasBuiltWith(): void
    {
        $id = new ConstantNodeId('App\Domain', 'Invoice', 'MAX_ITEMS');

        self::assertSame($id, (new ConstantNode($id))->id());
    }

    public function testKindReportsTheSymbolItStandsFor(): void
    {
        self::assertSame(NodeKind::Constant, (new ConstantNode(new ConstantNodeId('App\Domain', 'Invoice', 'MAX_ITEMS')))->kind());
    }

    public function testResolvedReportsWhatAnalysisEstablished(): void
    {
        self::assertTrue((new ConstantNode(new ConstantNodeId('App\Domain', 'Invoice', 'MAX_ITEMS'), true))->resolved());
    }

    public function testResolvedIsFalseUntilAnalysisEstablishesOtherwise(): void
    {
        self::assertFalse((new ConstantNode(new ConstantNodeId('App\Domain', 'Invoice', 'MAX_ITEMS')))->resolved());
    }

    public function testMetaReturnsWhereTheSymbolIsDeclared(): void
    {
        $meta = new FileMeta('/project/src/Invoice.php', 10, 5);

        self::assertSame($meta, (new ConstantNode(new ConstantNodeId('App\Domain', 'Invoice', 'MAX_ITEMS'), true, $meta))->meta());
    }

    public function testMetaIsNullForASymbolWithNoKnownLocation(): void
    {
        self::assertNull((new ConstantNode(new ConstantNodeId('App\Domain', 'Invoice', 'MAX_ITEMS')))->meta());
    }

    public function testDeclarationReturnsWhatTheSourceDeclaresAboutTheSymbol(): void
    {
        $declared = new SymbolDeclaration(modifiers: new Modifiers(final: true));

        self::assertSame($declared, (new ConstantNode(new ConstantNodeId('App\Domain', 'Invoice', 'MAX_ITEMS'), true, null, $declared))->declaration());
    }

    public function testDeclarationIsNullForASymbolAnalysisOnlyReferredTo(): void
    {
        self::assertNull((new ConstantNode(new ConstantNodeId('App\Domain', 'Invoice', 'MAX_ITEMS')))->declaration());
    }
}
