<?php

declare(strict_types=1);

namespace Tests\Unit\Analyzer\Graph\Edge\Inverse;

use App\Analyzer\Graph\Edge\Declaration\MethodEdge;
use App\Analyzer\Graph\Edge\Inverse\DeclaredInEdge;
use App\Analyzer\Graph\EdgeKind;
use App\Analyzer\Graph\FileMeta;
use App\Analyzer\Graph\Node\ClassNode;
use App\Analyzer\Graph\Node\MethodNode;
use App\Analyzer\Graph\NodeId\ClassNodeId;
use App\Analyzer\Graph\NodeId\MethodNodeId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(DeclaredInEdge::class)]
#[UsesClass(\App\Analyzer\Graph\AuthoredEdge::class)]
#[UsesClass(MethodEdge::class)]
#[UsesClass(FileMeta::class)]
#[UsesClass(ClassNodeId::class)]
#[UsesClass(MethodNodeId::class)]
#[UsesClass(ClassNode::class)]
#[UsesClass(MethodNode::class)]
#[UsesClass(\App\Analyzer\Graph\QualifiedName::class)]
#[Small]
final class DeclaredInEdgeTest extends TestCase
{
    #[DataProvider('providerInvoiceDeclaringTotal')]
    public function testFromNamesTheDeclaredSymbol(MethodEdge $declaration): void
    {
        self::assertSame('App\Domain\Invoice::total', (new DeclaredInEdge($declaration))->from()->toString());
    }

    #[DataProvider('providerInvoiceDeclaringTotal')]
    public function testToNamesTheSymbolThatDeclaresIt(MethodEdge $declaration): void
    {
        self::assertSame('App\Domain\Invoice', (new DeclaredInEdge($declaration))->to()->toString());
    }

    #[DataProvider('providerInvoiceDeclaringTotal')]
    public function testKindMarksTheRelationAsAReverseDeclaration(MethodEdge $declaration): void
    {
        self::assertSame(EdgeKind::DeclaredIn, (new DeclaredInEdge($declaration))->kind());
    }

    #[DataProvider('providerInvoiceDeclaringTotal')]
    public function testMetaIsWhereTheDeclarationItReversesIsWritten(MethodEdge $declaration): void
    {
        self::assertSame($declaration->meta(), (new DeclaredInEdge($declaration))->meta());
    }

    #[DataProvider('providerInvoiceDeclaringTotal')]
    public function testInvertGivesBackTheExactDeclarationItWasDerivedFrom(MethodEdge $declaration): void
    {
        self::assertSame($declaration, (new DeclaredInEdge($declaration))->invert());
    }

    #[DataProvider('providerInvoiceDeclaringTotal')]
    public function testTheOriginalKindSurvivesTheReverseReading(MethodEdge $declaration): void
    {
        self::assertSame(EdgeKind::DeclarationMethod, (new DeclaredInEdge($declaration))->invert()->kind());
    }

    /**
     * @return iterable<string, array{MethodEdge}>
     */
    public static function providerInvoiceDeclaringTotal(): iterable
    {
        $meta = new FileMeta('/project/src/Domain/Invoice.php', 12, 1);

        yield 'App\Domain\Invoice declares total' => [new MethodEdge(
            new ClassNode(ClassNodeId::of('App\Domain\Invoice'), true, $meta),
            new MethodNode(MethodNodeId::of('App\Domain\Invoice', 'total'), true, $meta),
            $meta,
        )];
    }
}
