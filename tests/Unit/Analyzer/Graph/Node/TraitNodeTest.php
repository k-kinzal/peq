<?php

declare(strict_types=1);

namespace Tests\Unit\Analyzer\Graph\Node;

use App\Analyzer\Graph\Declaration\Modifiers;
use App\Analyzer\Graph\Declaration\SymbolDeclaration;
use App\Analyzer\Graph\FileMeta;
use App\Analyzer\Graph\Node\TraitNode;
use App\Analyzer\Graph\NodeId\TraitNodeId;
use App\Analyzer\Graph\NodeKind;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(TraitNode::class)]
#[UsesClass(Modifiers::class)]
#[UsesClass(SymbolDeclaration::class)]
#[UsesClass(FileMeta::class)]
#[UsesClass(TraitNodeId::class)]
#[UsesClass(\App\Analyzer\Graph\QualifiedName::class)]
#[Small]
final class TraitNodeTest extends TestCase
{
    public function testIdReturnsTheIdentifierItWasBuiltWith(): void
    {
        $id = new TraitNodeId('App\Domain', 'Timestamped');

        self::assertSame($id, (new TraitNode($id))->id());
    }

    public function testKindReportsTheSymbolItStandsFor(): void
    {
        self::assertSame(NodeKind::Trait, (new TraitNode(new TraitNodeId('App\Domain', 'Timestamped')))->kind());
    }

    public function testResolvedReportsWhatAnalysisEstablished(): void
    {
        self::assertTrue((new TraitNode(new TraitNodeId('App\Domain', 'Timestamped'), true))->resolved());
    }

    public function testResolvedIsFalseUntilAnalysisEstablishesOtherwise(): void
    {
        self::assertFalse((new TraitNode(new TraitNodeId('App\Domain', 'Timestamped')))->resolved());
    }

    public function testMetaReturnsWhereTheSymbolIsDeclared(): void
    {
        $meta = new FileMeta('/project/src/Invoice.php', 10, 5);

        self::assertSame($meta, (new TraitNode(new TraitNodeId('App\Domain', 'Timestamped'), true, $meta))->meta());
    }

    public function testMetaIsNullForASymbolWithNoKnownLocation(): void
    {
        self::assertNull((new TraitNode(new TraitNodeId('App\Domain', 'Timestamped')))->meta());
    }

    public function testDeclarationReturnsWhatTheSourceDeclaresAboutTheSymbol(): void
    {
        $declared = new SymbolDeclaration(modifiers: new Modifiers(final: true));

        self::assertSame($declared, (new TraitNode(new TraitNodeId('App\Domain', 'Timestamped'), true, null, $declared))->declaration());
    }

    public function testDeclarationIsNullForASymbolAnalysisOnlyReferredTo(): void
    {
        self::assertNull((new TraitNode(new TraitNodeId('App\Domain', 'Timestamped')))->declaration());
    }
}
