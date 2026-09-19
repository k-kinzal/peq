<?php

declare(strict_types=1);

namespace Tests\Unit\Analyzer\Graph\Node;

use App\Analyzer\Graph\Declaration\Modifiers;
use App\Analyzer\Graph\Declaration\SymbolDeclaration;
use App\Analyzer\Graph\FileMeta;
use App\Analyzer\Graph\Node\BuiltinNode;
use App\Analyzer\Graph\NodeId\BuiltinNodeId;
use App\Analyzer\Graph\NodeKind;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(BuiltinNode::class)]
#[UsesClass(Modifiers::class)]
#[UsesClass(SymbolDeclaration::class)]
#[UsesClass(FileMeta::class)]
#[UsesClass(BuiltinNodeId::class)]
#[UsesClass(\App\Analyzer\Graph\QualifiedName::class)]
#[Small]
final class BuiltinNodeTest extends TestCase
{
    public function testIdReturnsTheIdentifierItWasBuiltWith(): void
    {
        $id = new BuiltinNodeId('App\Domain', 'Money');

        self::assertSame($id, (new BuiltinNode($id))->id());
    }

    public function testKindReportsTheSymbolItStandsFor(): void
    {
        self::assertSame(NodeKind::Builtin, (new BuiltinNode(new BuiltinNodeId('App\Domain', 'Money')))->kind());
    }

    public function testResolvedReportsWhatAnalysisEstablished(): void
    {
        self::assertTrue((new BuiltinNode(new BuiltinNodeId('App\Domain', 'Money'), true))->resolved());
    }

    public function testResolvedIsFalseUntilAnalysisEstablishesOtherwise(): void
    {
        self::assertFalse((new BuiltinNode(new BuiltinNodeId('App\Domain', 'Money')))->resolved());
    }

    public function testMetaReturnsWhereTheSymbolIsDeclared(): void
    {
        $meta = new FileMeta('/project/src/Invoice.php', 10, 5);

        self::assertSame($meta, (new BuiltinNode(new BuiltinNodeId('App\Domain', 'Money'), true, $meta))->meta());
    }

    public function testMetaIsNullForASymbolWithNoKnownLocation(): void
    {
        self::assertNull((new BuiltinNode(new BuiltinNodeId('App\Domain', 'Money')))->meta());
    }

    public function testDeclarationReturnsWhatTheSourceDeclaresAboutTheSymbol(): void
    {
        $declared = new SymbolDeclaration(modifiers: new Modifiers(final: true));

        self::assertSame($declared, (new BuiltinNode(new BuiltinNodeId('App\Domain', 'Money'), true, null, $declared))->declaration());
    }

    public function testDeclarationIsNullForASymbolAnalysisOnlyReferredTo(): void
    {
        self::assertNull((new BuiltinNode(new BuiltinNodeId('App\Domain', 'Money')))->declaration());
    }
}
