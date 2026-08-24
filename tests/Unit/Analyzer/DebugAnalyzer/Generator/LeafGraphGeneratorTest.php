<?php

declare(strict_types=1);

namespace Tests\Unit\Analyzer\DebugAnalyzer\Generator;

use App\Analyzer\DebugAnalyzer\Generator\LeafGraphGenerator;
use App\Analyzer\Graph\NodeId\ConstantNodeId;
use App\Analyzer\Graph\NodeKind;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Tests\Fixture\Analyzer\SeededGenerators;

/**
 * @internal
 */
#[CoversClass(LeafGraphGenerator::class)]
#[UsesClass(\App\Analyzer\DebugAnalyzer\Generator\GeneratedGraph::class)]
#[UsesClass(\App\Analyzer\DebugAnalyzer\Generator\NameGenerator::class)]
#[UsesClass(\App\Analyzer\DebugAnalyzer\Generator\NodeGenerator::class)]
#[UsesClass(\App\Analyzer\DebugAnalyzer\Generator\NodeIdGenerator::class)]
#[UsesClass(\App\Analyzer\Graph\FileMeta::class)]
#[UsesClass(\App\Analyzer\Graph\Graph::class)]
#[UsesClass(\App\Analyzer\Graph\NodeId\BuiltinNodeId::class)]
#[UsesClass(ConstantNodeId::class)]
#[UsesClass(\App\Analyzer\Graph\NodeId\EnumCaseNodeId::class)]
#[UsesClass(\App\Analyzer\Graph\Node\BuiltinNode::class)]
#[UsesClass(\App\Analyzer\Graph\Node\ConstantNode::class)]
#[UsesClass(\App\Analyzer\Graph\Node\EnumCaseNode::class)]
#[UsesClass(\App\Analyzer\Graph\QualifiedName::class)]
#[Small]
final class LeafGraphGeneratorTest extends TestCase
{
    public function testConstantGraphIsRootedAtAConstant(): void
    {
        self::assertSame(NodeKind::Constant, SeededGenerators::leaves()->constantGraph()->root->kind());
    }

    public function testConstantGraphHoldsNothingButTheConstant(): void
    {
        self::assertCount(1, SeededGenerators::leaves()->constantGraph()->graph->nodes());
    }

    public function testConstantGraphReusesAnIdentifierItIsGiven(): void
    {
        $id = ConstantNodeId::of('App\Domain\Invoice', 'MAX_ITEMS');

        self::assertSame('App\Domain\Invoice::MAX_ITEMS', SeededGenerators::leaves()->constantGraph($id)->root->id()->toString());
    }

    public function testEnumCaseGraphIsRootedAtAnEnumCase(): void
    {
        self::assertSame(NodeKind::EnumCase, SeededGenerators::leaves()->enumCaseGraph()->root->kind());
    }

    public function testEnumCaseGraphHoldsNothingButTheEnumCase(): void
    {
        self::assertCount(1, SeededGenerators::leaves()->enumCaseGraph()->graph->nodes());
    }

    public function testBuiltinGraphIsRootedAtABuiltinType(): void
    {
        self::assertSame(NodeKind::Builtin, SeededGenerators::leaves()->builtinGraph()->root->kind());
    }

    public function testBuiltinGraphHoldsNothingButTheBuiltinType(): void
    {
        self::assertCount(1, SeededGenerators::leaves()->builtinGraph()->graph->nodes());
    }

    public function testALeafGraphHoldsNoRelationsAtAll(): void
    {
        self::assertSame([], SeededGenerators::leaves()->constantGraph()->graph->authoredEdges());
    }
}
