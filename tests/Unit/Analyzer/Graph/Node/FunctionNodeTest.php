<?php

declare(strict_types=1);

namespace Tests\Unit\Analyzer\Graph\Node;

use App\Analyzer\Graph\Declaration\Modifiers;
use App\Analyzer\Graph\Declaration\SymbolDeclaration;
use App\Analyzer\Graph\FileMeta;
use App\Analyzer\Graph\Node\FunctionNode;
use App\Analyzer\Graph\NodeId\FunctionNodeId;
use App\Analyzer\Graph\NodeKind;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(FunctionNode::class)]
#[UsesClass(Modifiers::class)]
#[UsesClass(SymbolDeclaration::class)]
#[UsesClass(FileMeta::class)]
#[UsesClass(FunctionNodeId::class)]
#[UsesClass(\App\Analyzer\Graph\QualifiedName::class)]
#[Small]
final class FunctionNodeTest extends TestCase
{
    public function testIdReturnsTheIdentifierItWasBuiltWith(): void
    {
        $id = new FunctionNodeId('App\Domain', 'formatMoney');

        self::assertSame($id, (new FunctionNode($id))->id());
    }

    public function testKindReportsTheSymbolItStandsFor(): void
    {
        self::assertSame(NodeKind::Function, (new FunctionNode(new FunctionNodeId('App\Domain', 'formatMoney')))->kind());
    }

    public function testResolvedReportsWhatAnalysisEstablished(): void
    {
        self::assertTrue((new FunctionNode(new FunctionNodeId('App\Domain', 'formatMoney'), true))->resolved());
    }

    public function testResolvedIsFalseUntilAnalysisEstablishesOtherwise(): void
    {
        self::assertFalse((new FunctionNode(new FunctionNodeId('App\Domain', 'formatMoney')))->resolved());
    }

    public function testMetaReturnsWhereTheSymbolIsDeclared(): void
    {
        $meta = new FileMeta('/project/src/Invoice.php', 10, 5);

        self::assertSame($meta, (new FunctionNode(new FunctionNodeId('App\Domain', 'formatMoney'), true, $meta))->meta());
    }

    public function testMetaIsNullForASymbolWithNoKnownLocation(): void
    {
        self::assertNull((new FunctionNode(new FunctionNodeId('App\Domain', 'formatMoney')))->meta());
    }

    public function testDeclarationReturnsWhatTheSourceDeclaresAboutTheSymbol(): void
    {
        $declared = new SymbolDeclaration(modifiers: new Modifiers(final: true));

        self::assertSame($declared, (new FunctionNode(new FunctionNodeId('App\Domain', 'formatMoney'), true, null, $declared))->declaration());
    }

    public function testDeclarationIsNullForASymbolAnalysisOnlyReferredTo(): void
    {
        self::assertNull((new FunctionNode(new FunctionNodeId('App\Domain', 'formatMoney')))->declaration());
    }
}
