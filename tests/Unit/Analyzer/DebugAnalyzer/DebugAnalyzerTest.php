<?php

declare(strict_types=1);

namespace Tests\Unit\Analyzer\DebugAnalyzer;

use App\Analyzer\DebugAnalyzer\DebugAnalyzer;
use App\Analyzer\Graph\NodeKind;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Tests\Fixture\Graph\GraphInvariants;

/**
 * @internal
 */
#[CoversClass(DebugAnalyzer::class)]
#[UsesClass(\App\Analyzer\DebugAnalyzer\Generator\ClassLikeGraphGenerator::class)]
#[UsesClass(\App\Analyzer\DebugAnalyzer\Generator\GeneratedGraph::class)]
#[UsesClass(\App\Analyzer\DebugAnalyzer\Generator\GraphGenerator::class)]
#[UsesClass(\App\Analyzer\DebugAnalyzer\Generator\LeafGraphGenerator::class)]
#[UsesClass(\App\Analyzer\DebugAnalyzer\Generator\MemberGraphGenerator::class)]
#[UsesClass(\App\Analyzer\DebugAnalyzer\Generator\NameGenerator::class)]
#[UsesClass(\App\Analyzer\DebugAnalyzer\Generator\FakerRandomSource::class)]
#[UsesClass(\App\Analyzer\DebugAnalyzer\Generator\NodeGenerator::class)]
#[UsesClass(\App\Analyzer\DebugAnalyzer\Generator\NodeIdGenerator::class)]
#[UsesClass(\App\Analyzer\Graph\AuthoredEdge::class)]
#[UsesClass(\App\Analyzer\Graph\Edge\Usage\ConstFetchEdge::class)]
#[UsesClass(\App\Analyzer\Graph\Edge\Declaration\ConstantEdge::class)]
#[UsesClass(\App\Analyzer\Graph\Edge\Declaration\ExtendsEdge::class)]
#[UsesClass(\App\Analyzer\Graph\Edge\Declaration\ImplementsEdge::class)]
#[UsesClass(\App\Analyzer\Graph\Edge\Declaration\MethodEdge::class)]
#[UsesClass(\App\Analyzer\Graph\Edge\Declaration\PropertyEdge::class)]
#[UsesClass(\App\Analyzer\Graph\Edge\Declaration\TraitUseEdge::class)]
#[UsesClass(\App\Analyzer\Graph\Edge\Declaration\TypeParameterEdge::class)]
#[UsesClass(\App\Analyzer\Graph\Edge\Declaration\TypePropertyEdge::class)]
#[UsesClass(\App\Analyzer\Graph\Edge\Declaration\TypeReturnEdge::class)]
#[UsesClass(\App\Analyzer\Graph\Edge\Inverse\DeclaredInEdge::class)]
#[UsesClass(\App\Analyzer\Graph\Edge\Usage\FunctionCallEdge::class)]
#[UsesClass(\App\Analyzer\Graph\Edge\Usage\InstantiationEdge::class)]
#[UsesClass(\App\Analyzer\Graph\Edge\Usage\MethodCallEdge::class)]
#[UsesClass(\App\Analyzer\Graph\Edge\Usage\PropertyAccessEdge::class)]
#[UsesClass(\App\Analyzer\Graph\Edge\Usage\StaticCallEdge::class)]
#[UsesClass(\App\Analyzer\Graph\Edge\Usage\StaticPropertyAccessEdge::class)]
#[UsesClass(\App\Analyzer\Graph\Edge\Inverse\UsedByEdge::class)]
#[UsesClass(\App\Analyzer\Graph\FileMeta::class)]
#[UsesClass(\App\Analyzer\Graph\Graph::class)]
#[UsesClass(\App\Analyzer\Graph\NodeId\BuiltinNodeId::class)]
#[UsesClass(\App\Analyzer\Graph\NodeId\ClassNodeId::class)]
#[UsesClass(\App\Analyzer\Graph\NodeId\ConstantNodeId::class)]
#[UsesClass(\App\Analyzer\Graph\NodeId\EnumNodeId::class)]
#[UsesClass(\App\Analyzer\Graph\NodeId\FunctionNodeId::class)]
#[UsesClass(\App\Analyzer\Graph\NodeId\InterfaceNodeId::class)]
#[UsesClass(\App\Analyzer\Graph\NodeId\MethodNodeId::class)]
#[UsesClass(\App\Analyzer\Graph\NodeId\PropertyNodeId::class)]
#[UsesClass(\App\Analyzer\Graph\NodeId\TraitNodeId::class)]
#[UsesClass(\App\Analyzer\Graph\Node\BuiltinNode::class)]
#[UsesClass(\App\Analyzer\Graph\Node\ClassNode::class)]
#[UsesClass(\App\Analyzer\Graph\Node\ConstantNode::class)]
#[UsesClass(\App\Analyzer\Graph\Node\EnumNode::class)]
#[UsesClass(\App\Analyzer\Graph\Node\FunctionNode::class)]
#[UsesClass(\App\Analyzer\Graph\Node\GraphInterfaceNode::class)]
#[UsesClass(\App\Analyzer\Graph\Node\MethodNode::class)]
#[UsesClass(\App\Analyzer\Graph\Node\PropertyNode::class)]
#[UsesClass(\App\Analyzer\Graph\Node\TraitNode::class)]
#[UsesClass(\App\Analyzer\Graph\QualifiedName::class)]
#[Small]
final class DebugAnalyzerTest extends TestCase
{
    public function testAnalyzeProducesAGraphWithoutReadingAnySource(): void
    {
        self::assertNotEmpty((new DebugAnalyzer(seed: 42, depth: 3))->analyze('/no/such/path'));
    }

    public function testAnalyzeIgnoresThePathItIsGiven(): void
    {
        self::assertSame(
            count((new DebugAnalyzer(seed: 42, depth: 3))->analyze('/one/path')->nodes()),
            count((new DebugAnalyzer(seed: 42, depth: 3))->analyze('/another/path')->nodes()),
        );
    }

    public function testAnalyzeProducesTheSameGraphForTheSameSeed(): void
    {
        self::assertSame(
            (new DebugAnalyzer(seed: 42, depth: 3))->analyze('/generated')->nodes()[0]->id()->toString(),
            (new DebugAnalyzer(seed: 42, depth: 3))->analyze('/generated')->nodes()[0]->id()->toString(),
        );
    }

    public function testAnalyzeProducesADifferentGraphForADifferentSeed(): void
    {
        self::assertNotSame(
            (new DebugAnalyzer(seed: 1, depth: 3))->analyze('/generated')->nodes()[0]->id()->toString(),
            (new DebugAnalyzer(seed: 2, depth: 3))->analyze('/generated')->nodes()[0]->id()->toString(),
        );
    }

    public function testAnalyzeProducesOnlyTheRootSymbolAtTheShallowestDepth(): void
    {
        self::assertCount(1, (new DebugAnalyzer(seed: 42, depth: 1))->analyze('/generated')->nodes());
    }

    public function testAnalyzeProducesMoreThanTheRootAtAUsefulDepth(): void
    {
        self::assertGreaterThan(1, count((new DebugAnalyzer(seed: 42, depth: 3))->analyze('/generated')->nodes()));
    }

    public function testAnalyzeRootsTheGraphAtSomethingPhpDeclaresAtTheTopOfAFile(): void
    {
        $graph = (new DebugAnalyzer(seed: 42, depth: 3))->analyze('/generated');

        self::assertContains($graph->nodes()[0]->kind(), [NodeKind::Klass, NodeKind::Interface, NodeKind::Trait, NodeKind::Function]);
    }

    public function testAnalyzeProducesAGraphThatKeepsTheGraphInvariants(): void
    {
        $graph = (new DebugAnalyzer(seed: 42, depth: 3))->analyze('/generated');

        GraphInvariants::assertBidirectional($graph);
        GraphInvariants::assertEndpointsExist($graph);
        GraphInvariants::assertNodeUniqueness($graph);
    }
}
