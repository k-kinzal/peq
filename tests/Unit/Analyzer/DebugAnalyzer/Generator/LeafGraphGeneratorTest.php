<?php

declare(strict_types=1);

namespace Tests\Unit\Analyzer\DebugAnalyzer\Generator;

use App\Analyzer\DebugAnalyzer\Generator\LeafGraphGenerator;
use App\Analyzer\DebugAnalyzer\Generator\NameGenerator;
use App\Analyzer\DebugAnalyzer\Generator\NodeGenerator;
use App\Analyzer\DebugAnalyzer\Generator\NodeIdGenerator;
use App\Analyzer\DebugAnalyzer\Generator\RandomSource;
use App\Analyzer\Graph\NodeId\ConstantNodeId;
use App\Analyzer\Graph\NodeKind;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(LeafGraphGenerator::class)]
#[UsesClass(\App\Analyzer\DebugAnalyzer\Generator\GeneratedGraph::class)]
#[UsesClass(NameGenerator::class)]
#[UsesClass(NodeGenerator::class)]
#[UsesClass(NodeIdGenerator::class)]
#[UsesClass(\App\Analyzer\Graph\FileMeta::class)]
#[UsesClass(\App\Analyzer\Graph\Graph::class)]
#[UsesClass(\App\Analyzer\Graph\NodeId\BuiltinNodeId::class)]
#[UsesClass(ConstantNodeId::class)]
#[UsesClass(\App\Analyzer\Graph\NodeId\EnumCaseNodeId::class)]
#[UsesClass(\App\Analyzer\Graph\Node\BuiltinNode::class)]
#[UsesClass(\App\Analyzer\Graph\Node\ConstantNode::class)]
#[UsesClass(\App\Analyzer\Graph\Node\EnumCaseNode::class)]
#[UsesClass(\App\Analyzer\Graph\QualifiedName::class)]
#[UsesClass(RandomSource::class)]
#[Small]
final class LeafGraphGeneratorTest extends TestCase
{
    #[DataProvider('providerLeafGraphGenerator')]
    public function testConstantGraphIsRootedAtAConstant(LeafGraphGenerator $leaves): void
    {
        self::assertSame(NodeKind::Constant, $leaves->constantGraph()->root->kind());
    }

    #[DataProvider('providerLeafGraphGenerator')]
    public function testConstantGraphHoldsNothingButTheConstant(LeafGraphGenerator $leaves): void
    {
        self::assertCount(1, $leaves->constantGraph()->graph->nodes());
    }

    #[DataProvider('providerLeafGraphGenerator')]
    public function testConstantGraphReusesAnIdentifierItIsGiven(LeafGraphGenerator $leaves): void
    {
        $id = ConstantNodeId::of('App\Domain\Invoice', 'MAX_ITEMS');

        self::assertSame('App\Domain\Invoice::MAX_ITEMS', $leaves->constantGraph($id)->root->id()->toString());
    }

    #[DataProvider('providerLeafGraphGenerator')]
    public function testEnumCaseGraphIsRootedAtAnEnumCase(LeafGraphGenerator $leaves): void
    {
        self::assertSame(NodeKind::EnumCase, $leaves->enumCaseGraph()->root->kind());
    }

    #[DataProvider('providerLeafGraphGenerator')]
    public function testEnumCaseGraphHoldsNothingButTheEnumCase(LeafGraphGenerator $leaves): void
    {
        self::assertCount(1, $leaves->enumCaseGraph()->graph->nodes());
    }

    #[DataProvider('providerLeafGraphGenerator')]
    public function testBuiltinGraphIsRootedAtABuiltinType(LeafGraphGenerator $leaves): void
    {
        self::assertSame(NodeKind::Builtin, $leaves->builtinGraph()->root->kind());
    }

    #[DataProvider('providerLeafGraphGenerator')]
    public function testBuiltinGraphHoldsNothingButTheBuiltinType(LeafGraphGenerator $leaves): void
    {
        self::assertCount(1, $leaves->builtinGraph()->graph->nodes());
    }

    #[DataProvider('providerLeafGraphGenerator')]
    public function testALeafGraphHoldsNoRelationsAtAll(LeafGraphGenerator $leaves): void
    {
        self::assertSame([], $leaves->constantGraph()->graph->authoredEdges());
    }

    /**
     * @return iterable<string, array{LeafGraphGenerator}>
     */
    public static function providerLeafGraphGenerator(): iterable
    {
        $random = new RandomSource(42);

        yield 'drawing from seed 42' => [new LeafGraphGenerator(new NodeGenerator(new NodeIdGenerator(new NameGenerator($random), $random), $random))];
    }
}
