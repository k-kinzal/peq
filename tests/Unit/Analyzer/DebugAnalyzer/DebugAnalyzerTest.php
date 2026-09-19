<?php

declare(strict_types=1);

namespace Tests\Unit\Analyzer\DebugAnalyzer;

use App\Analyzer\DebugAnalyzer\DebugAnalyzer;
use App\Analyzer\Graph\Edge;
use App\Analyzer\Graph\Node;
use App\Analyzer\Graph\NodeKind;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

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
#[UsesClass(\App\Analyzer\DebugAnalyzer\Generator\NodeGenerator::class)]
#[UsesClass(\App\Analyzer\DebugAnalyzer\Generator\NodeIdGenerator::class)]
#[UsesClass(\App\Analyzer\Graph\AuthoredEdge::class)]
#[UsesClass(Edge\Usage\ConstFetchEdge::class)]
#[UsesClass(Edge\Declaration\ConstantEdge::class)]
#[UsesClass(Edge\Declaration\ExtendsEdge::class)]
#[UsesClass(Edge\Declaration\ImplementsEdge::class)]
#[UsesClass(Edge\Declaration\MethodEdge::class)]
#[UsesClass(Edge\Declaration\PropertyEdge::class)]
#[UsesClass(Edge\Declaration\TraitUseEdge::class)]
#[UsesClass(Edge\Declaration\TypeParameterEdge::class)]
#[UsesClass(Edge\Declaration\TypePropertyEdge::class)]
#[UsesClass(Edge\Declaration\TypeReturnEdge::class)]
#[UsesClass(Edge\Inverse\DeclaredInEdge::class)]
#[UsesClass(Edge\Usage\FunctionCallEdge::class)]
#[UsesClass(Edge\Usage\InstantiationEdge::class)]
#[UsesClass(Edge\Usage\MethodCallEdge::class)]
#[UsesClass(Edge\Usage\PropertyAccessEdge::class)]
#[UsesClass(Edge\Usage\StaticCallEdge::class)]
#[UsesClass(Edge\Usage\StaticPropertyAccessEdge::class)]
#[UsesClass(Edge\Inverse\UsedByEdge::class)]
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
#[UsesClass(Node\BuiltinNode::class)]
#[UsesClass(Node\ClassNode::class)]
#[UsesClass(Node\ConstantNode::class)]
#[UsesClass(Node\EnumNode::class)]
#[UsesClass(Node\FunctionNode::class)]
#[UsesClass(Node\GraphInterfaceNode::class)]
#[UsesClass(Node\MethodNode::class)]
#[UsesClass(Node\PropertyNode::class)]
#[UsesClass(Node\TraitNode::class)]
#[UsesClass(\App\Analyzer\Graph\QualifiedName::class)]
#[UsesClass(\App\Analyzer\DebugAnalyzer\Generator\RandomSource::class)]
#[Small]
final class DebugAnalyzerTest extends TestCase
{
    public function testAnalyzeProducesAGraphWithoutReadingAnySource(): void
    {
        self::assertNotSame([], (new DebugAnalyzer(seed: 42, depth: 3))->analyze('/no/such/path')->nodes());
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

        $edges = array_merge([], ...array_map(static fn (Node $node): array => $graph->edges($node->id()), $graph->nodes()));
        $names = array_map(static fn (Node $node): string => $node->id()->toString(), $graph->nodes());

        self::assertNotSame([], $edges);
        self::assertSame([], array_values(array_filter($edges, static fn (Edge $edge): bool => $graph->edge($edge->to(), $edge->from()) === null)));
        self::assertSame([], array_values(array_filter($edges, static fn (Edge $edge): bool => $graph->node($edge->from()) === null || $graph->node($edge->to()) === null)));
        self::assertSame($names, array_values(array_unique($names)));
    }
}
