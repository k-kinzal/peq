<?php

declare(strict_types=1);

namespace Tests\Unit\Analyzer\Graph\Edge\Inverse;

use App\Analyzer\Graph\Edge\Inverse\DeclaredInEdge;
use App\Analyzer\Graph\EdgeKind;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Tests\Fixture\Graph\SampleEdges;

/**
 * @internal
 */
#[CoversClass(DeclaredInEdge::class)]
#[UsesClass(\App\Analyzer\Graph\AuthoredEdge::class)]
#[UsesClass(\App\Analyzer\Graph\Edge\Declaration\MethodEdge::class)]
#[UsesClass(\App\Analyzer\Graph\FileMeta::class)]
#[UsesClass(\App\Analyzer\Graph\NodeId\ClassNodeId::class)]
#[UsesClass(\App\Analyzer\Graph\NodeId\MethodNodeId::class)]
#[UsesClass(\App\Analyzer\Graph\Node\ClassNode::class)]
#[UsesClass(\App\Analyzer\Graph\Node\MethodNode::class)]
#[UsesClass(\App\Analyzer\Graph\QualifiedName::class)]
#[Small]
final class DeclaredInEdgeTest extends TestCase
{
    public function testFromNamesTheDeclaredSymbol(): void
    {
        self::assertSame('App\Domain\Invoice::total', (new DeclaredInEdge(SampleEdges::methodDeclaration()))->from()->toString());
    }

    public function testToNamesTheSymbolThatDeclaresIt(): void
    {
        self::assertSame('App\Domain\Invoice', (new DeclaredInEdge(SampleEdges::methodDeclaration()))->to()->toString());
    }

    public function testKindMarksTheRelationAsAReverseDeclaration(): void
    {
        self::assertSame(EdgeKind::DeclaredIn, (new DeclaredInEdge(SampleEdges::methodDeclaration()))->kind());
    }

    public function testMetaIsWhereTheDeclarationItReversesIsWritten(): void
    {
        $declaration = SampleEdges::methodDeclaration();

        self::assertSame($declaration->meta(), (new DeclaredInEdge($declaration))->meta());
    }

    public function testInvertGivesBackTheExactDeclarationItWasDerivedFrom(): void
    {
        $declaration = SampleEdges::methodDeclaration();

        self::assertSame($declaration, (new DeclaredInEdge($declaration))->invert());
    }

    public function testTheOriginalKindSurvivesTheReverseReading(): void
    {
        self::assertSame(EdgeKind::DeclarationMethod, (new DeclaredInEdge(SampleEdges::methodDeclaration()))->invert()->kind());
    }
}
