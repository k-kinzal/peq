<?php

declare(strict_types=1);

namespace Tests\Unit\Analyzer\Graph\Node;

use App\Analyzer\Graph\Declaration\Modifiers;
use App\Analyzer\Graph\Declaration\SymbolDeclaration;
use App\Analyzer\Graph\FileMeta;
use App\Analyzer\Graph\Node\PropertyNode;
use App\Analyzer\Graph\NodeId\PropertyNodeId;
use App\Analyzer\Graph\NodeKind;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(PropertyNode::class)]
#[UsesClass(Modifiers::class)]
#[UsesClass(SymbolDeclaration::class)]
#[UsesClass(FileMeta::class)]
#[UsesClass(PropertyNodeId::class)]
#[UsesClass(\App\Analyzer\Graph\QualifiedName::class)]
#[Small]
final class PropertyNodeTest extends TestCase
{
    public function testIdReturnsTheIdentifierItWasBuiltWith(): void
    {
        $id = new PropertyNodeId('App\Domain', 'Invoice', 'lines');

        self::assertSame($id, (new PropertyNode($id))->id());
    }

    public function testKindReportsTheSymbolItStandsFor(): void
    {
        self::assertSame(NodeKind::Property, (new PropertyNode(new PropertyNodeId('App\Domain', 'Invoice', 'lines')))->kind());
    }

    public function testResolvedReportsWhatAnalysisEstablished(): void
    {
        self::assertTrue((new PropertyNode(new PropertyNodeId('App\Domain', 'Invoice', 'lines'), true))->resolved());
    }

    public function testResolvedIsFalseUntilAnalysisEstablishesOtherwise(): void
    {
        self::assertFalse((new PropertyNode(new PropertyNodeId('App\Domain', 'Invoice', 'lines')))->resolved());
    }

    public function testMetaReturnsWhereTheSymbolIsDeclared(): void
    {
        $meta = new FileMeta('/project/src/Invoice.php', 10, 5);

        self::assertSame($meta, (new PropertyNode(new PropertyNodeId('App\Domain', 'Invoice', 'lines'), true, $meta))->meta());
    }

    public function testMetaIsNullForASymbolWithNoKnownLocation(): void
    {
        self::assertNull((new PropertyNode(new PropertyNodeId('App\Domain', 'Invoice', 'lines')))->meta());
    }

    public function testDeclarationReturnsWhatTheSourceDeclaresAboutTheSymbol(): void
    {
        $declared = new SymbolDeclaration(modifiers: new Modifiers(final: true));

        self::assertSame($declared, (new PropertyNode(new PropertyNodeId('App\Domain', 'Invoice', 'lines'), true, null, $declared))->declaration());
    }

    public function testDeclarationIsNullForASymbolAnalysisOnlyReferredTo(): void
    {
        self::assertNull((new PropertyNode(new PropertyNodeId('App\Domain', 'Invoice', 'lines')))->declaration());
    }
}
