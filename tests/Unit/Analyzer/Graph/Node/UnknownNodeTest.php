<?php

declare(strict_types=1);

namespace Tests\Unit\Analyzer\Graph\Node;

use App\Analyzer\Graph\FileMeta;
use App\Analyzer\Graph\Node\UnknownNode;
use App\Analyzer\Graph\NodeId\ClassNodeId;
use App\Analyzer\Graph\NodeId\UnknownNodeId;
use App\Analyzer\Graph\NodeKind;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(UnknownNode::class)]
#[UsesClass(FileMeta::class)]
#[UsesClass(ClassNodeId::class)]
#[UsesClass(UnknownNodeId::class)]
#[UsesClass(\App\Analyzer\Graph\QualifiedName::class)]
#[Small]
final class UnknownNodeTest extends TestCase
{
    public function testIdReturnsTheIdentifierItWasBuiltWith(): void
    {
        $id = new UnknownNodeId('App\Domain\Unresolved');

        self::assertSame($id, (new UnknownNode($id))->id());
    }

    public function testKindReportsTheSymbolItStandsFor(): void
    {
        self::assertSame(NodeKind::Unknown, (new UnknownNode(new UnknownNodeId('App\Domain\Unresolved')))->kind());
    }

    public function testResolvedReportsWhatAnalysisEstablished(): void
    {
        self::assertTrue((new UnknownNode(new UnknownNodeId('App\Domain\Unresolved'), true))->resolved());
    }

    public function testResolvedIsFalseUntilAnalysisEstablishesOtherwise(): void
    {
        self::assertFalse((new UnknownNode(new UnknownNodeId('App\Domain\Unresolved')))->resolved());
    }

    public function testMetaReturnsWhereTheSymbolIsDeclared(): void
    {
        $meta = new FileMeta('/project/src/Invoice.php', 10, 5);

        self::assertSame($meta, (new UnknownNode(new UnknownNodeId('App\Domain\Unresolved'), true, $meta))->meta());
    }

    public function testMetaIsNullForASymbolWithNoKnownLocation(): void
    {
        self::assertNull((new UnknownNode(new UnknownNodeId('App\Domain\Unresolved')))->meta());
    }

    public function testStandingInForKeepsAnUnresolvedIdentifierAsItIs(): void
    {
        $id = new UnknownNodeId('App\Domain\Unresolved');

        self::assertSame($id, UnknownNode::standingInFor($id)->id());
    }

    public function testStandingInForRewritesAnyOtherIdentifierAsUnresolved(): void
    {
        $placeholder = UnknownNode::standingInFor(ClassNodeId::of('App\Domain\Invoice'));

        self::assertSame('App\Domain\Invoice', $placeholder->id()->toString());
        self::assertSame(NodeKind::Unknown, $placeholder->kind());
    }

    public function testStandingInForReportsTheSymbolAsUnresolved(): void
    {
        self::assertFalse(UnknownNode::standingInFor(ClassNodeId::of('App\Domain\Invoice'))->resolved());
    }
}
