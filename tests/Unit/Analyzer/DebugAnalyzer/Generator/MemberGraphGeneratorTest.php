<?php

declare(strict_types=1);

namespace Tests\Unit\Analyzer\DebugAnalyzer\Generator;

use App\Analyzer\DebugAnalyzer\Generator\ClassLikeGraphGenerator;
use App\Analyzer\DebugAnalyzer\Generator\GeneratedGraph;
use App\Analyzer\DebugAnalyzer\Generator\GraphGenerator;
use App\Analyzer\DebugAnalyzer\Generator\LeafGraphGenerator;
use App\Analyzer\DebugAnalyzer\Generator\MemberGraphGenerator;
use App\Analyzer\DebugAnalyzer\Generator\NameGenerator;
use App\Analyzer\DebugAnalyzer\Generator\NodeGenerator;
use App\Analyzer\DebugAnalyzer\Generator\NodeIdGenerator;
use App\Analyzer\DebugAnalyzer\Generator\RandomSource;
use App\Analyzer\Graph\Edge;
use App\Analyzer\Graph\EdgeKind;
use App\Analyzer\Graph\Node;
use App\Analyzer\Graph\NodeId\MethodNodeId;
use App\Analyzer\Graph\NodeKind;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[UsesClass(\App\Analyzer\Graph\EdgeIdentity::class)]
#[CoversClass(MemberGraphGenerator::class)]
#[UsesClass(ClassLikeGraphGenerator::class)]
#[UsesClass(GeneratedGraph::class)]
#[UsesClass(GraphGenerator::class)]
#[UsesClass(LeafGraphGenerator::class)]
#[UsesClass(NameGenerator::class)]
#[UsesClass(NodeGenerator::class)]
#[UsesClass(NodeIdGenerator::class)]
#[UsesClass(\App\Analyzer\Graph\AuthoredEdge::class)]
#[UsesClass(Edge\Usage\ConstFetchEdge::class)]
#[UsesClass(Edge\Declaration\ConstantEdge::class)]
#[UsesClass(Edge\Declaration\EnumCaseEdge::class)]
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
#[UsesClass(\App\Analyzer\Graph\NodeId\EnumCaseNodeId::class)]
#[UsesClass(\App\Analyzer\Graph\NodeId\EnumNodeId::class)]
#[UsesClass(\App\Analyzer\Graph\NodeId\FunctionNodeId::class)]
#[UsesClass(\App\Analyzer\Graph\NodeId\InterfaceNodeId::class)]
#[UsesClass(MethodNodeId::class)]
#[UsesClass(\App\Analyzer\Graph\NodeId\PropertyNodeId::class)]
#[UsesClass(\App\Analyzer\Graph\NodeId\TraitNodeId::class)]
#[UsesClass(Node\BuiltinNode::class)]
#[UsesClass(Node\ClassNode::class)]
#[UsesClass(Node\ConstantNode::class)]
#[UsesClass(Node\EnumCaseNode::class)]
#[UsesClass(Node\EnumNode::class)]
#[UsesClass(Node\FunctionNode::class)]
#[UsesClass(Node\GraphInterfaceNode::class)]
#[UsesClass(Node\MethodNode::class)]
#[UsesClass(Node\PropertyNode::class)]
#[UsesClass(Node\TraitNode::class)]
#[UsesClass(\App\Analyzer\Graph\QualifiedName::class)]
#[UsesClass(RandomSource::class)]
#[Small]
final class MemberGraphGeneratorTest extends TestCase
{
    #[DataProvider('providerGeneratorsDrawingFromSeed42')]
    public function testMethodGraphIsRootedAtAMethod(MemberGraphGenerator $members, GraphGenerator $graphs, NodeGenerator $nodes): void
    {
        self::assertSame(NodeKind::Method, $members->methodGraph($graphs, null, 2)->root->kind());
    }

    #[DataProvider('providerGeneratorsDrawingFromSeed42')]
    public function testMethodGraphReusesAnIdentifierItIsGiven(MemberGraphGenerator $members, GraphGenerator $graphs, NodeGenerator $nodes): void
    {
        $result = $members->methodGraph($graphs, MethodNodeId::of('App\Domain\Invoice', 'total'), 1);

        self::assertSame('App\Domain\Invoice::total', $result->root->id()->toString());
    }

    #[DataProvider('providerGeneratorsDrawingFromSeed42')]
    public function testMethodGraphStopsAtDepthZero(MemberGraphGenerator $members, GraphGenerator $graphs, NodeGenerator $nodes): void
    {
        self::assertCount(1, $members->methodGraph($graphs, null, 0)->graph->nodes());
    }

    #[DataProvider('providerGeneratorsDrawingFromSeed42')]
    public function testMethodGraphAlwaysCommitsToAReturnType(MemberGraphGenerator $members, GraphGenerator $graphs, NodeGenerator $nodes): void
    {
        $result = $members->methodGraph($graphs, null, 1);
        $kinds = array_map(static fn ($edge): EdgeKind => $edge->kind(), $result->graph->forwardEdges());

        self::assertContains(EdgeKind::DeclarationTypeReturn, $kinds);
    }

    #[DataProvider('providerGeneratorsDrawingFromSeed42')]
    public function testFunctionGraphIsRootedAtAFunction(MemberGraphGenerator $members, GraphGenerator $graphs, NodeGenerator $nodes): void
    {
        self::assertSame(NodeKind::Function, $members->functionGraph($graphs, null, 2)->root->kind());
    }

    #[DataProvider('providerGeneratorsDrawingFromSeed42')]
    public function testFunctionGraphStopsAtDepthZero(MemberGraphGenerator $members, GraphGenerator $graphs, NodeGenerator $nodes): void
    {
        self::assertCount(1, $members->functionGraph($graphs, null, 0)->graph->nodes());
    }

    #[DataProvider('providerGeneratorsDrawingFromSeed42')]
    public function testPropertyGraphIsRootedAtAProperty(MemberGraphGenerator $members, GraphGenerator $graphs, NodeGenerator $nodes): void
    {
        self::assertSame(NodeKind::Property, $members->propertyGraph($graphs, null, 2)->root->kind());
    }

    #[DataProvider('providerGeneratorsDrawingFromSeed42')]
    public function testPropertyGraphAlwaysCommitsToADeclaredType(MemberGraphGenerator $members, GraphGenerator $graphs, NodeGenerator $nodes): void
    {
        $result = $members->propertyGraph($graphs, null, 1);
        $kinds = array_map(static fn ($edge): EdgeKind => $edge->kind(), $result->graph->forwardEdges());

        self::assertContains(EdgeKind::DeclarationTypeProperty, $kinds);
    }

    #[DataProvider('providerGeneratorsDrawingFromSeed42')]
    public function testPropertyGraphStopsAtDepthZero(MemberGraphGenerator $members, GraphGenerator $graphs, NodeGenerator $nodes): void
    {
        self::assertCount(1, $members->propertyGraph($graphs, null, 0)->graph->nodes());
    }

    #[DataProvider('providerGeneratorsDrawingFromSeed42')]
    public function testCallableSignatureAddsTheDeclaredTypesToTheGraphItIsGiven(MemberGraphGenerator $members, GraphGenerator $graphs, NodeGenerator $nodes): void
    {
        $start = GeneratedGraph::rootedAt($nodes->methodNode());
        $grown = $members->callableSignature($graphs, $start, 2);

        self::assertGreaterThan(count($start->graph->nodes()), count($grown->graph->nodes()));
    }

    #[DataProvider('providerGeneratorsDrawingFromSeed42')]
    public function testCallableSignatureKeepsTheCallableAsTheRoot(MemberGraphGenerator $members, GraphGenerator $graphs, NodeGenerator $nodes): void
    {
        $start = GeneratedGraph::rootedAt($nodes->methodNode());

        self::assertSame($start->root, $members->callableSignature($graphs, $start, 2)->root);
    }

    #[DataProvider('providerGeneratorsDrawingFromSeed42')]
    public function testMethodCallsOnlyEverAddsToTheGraphItIsGiven(MemberGraphGenerator $members, GraphGenerator $graphs, NodeGenerator $nodes): void
    {
        $start = GeneratedGraph::rootedAt($nodes->methodNode());
        $grown = $members->methodCalls($graphs, $start, 2);

        self::assertGreaterThanOrEqual(count($start->graph->nodes()), count($grown->graph->nodes()));
    }

    #[DataProvider('providerGeneratorsDrawingFromSeed42')]
    public function testMethodAccessesOnlyEverAddsToTheGraphItIsGiven(MemberGraphGenerator $members, GraphGenerator $graphs, NodeGenerator $nodes): void
    {
        $start = GeneratedGraph::rootedAt($nodes->methodNode());
        $grown = $members->methodAccesses($graphs, $start, 2);

        self::assertGreaterThanOrEqual(count($start->graph->nodes()), count($grown->graph->nodes()));
    }

    #[DataProvider('providerGeneratorsDrawingFromSeed42')]
    public function testEveryMemberGraphKeepsTheGraphInvariants(MemberGraphGenerator $members, GraphGenerator $graphs, NodeGenerator $nodes): void
    {
        $result = $members->methodGraph($graphs, null, 2);

        $graph = $result->graph;
        $edges = array_merge([], ...array_map(static fn (Node $node): array => $graph->edges($node->id()), $graph->nodes()));

        self::assertSame([], array_values(array_filter($edges, static fn (Edge $edge): bool => $graph->edge($edge->to(), $edge->from()) === null)));
        self::assertSame([], array_values(array_filter($edges, static fn (Edge $edge): bool => $graph->node($edge->from()) === null || $graph->node($edge->to()) === null)));
    }

    /**
     * @return iterable<string, array{MemberGraphGenerator, GraphGenerator, NodeGenerator}>
     */
    public static function providerGeneratorsDrawingFromSeed42(): iterable
    {
        $random = new RandomSource(42);
        $ids = new NodeIdGenerator(new NameGenerator($random), $random);
        $nodes = new NodeGenerator($ids, $random);
        $classLikes = new ClassLikeGraphGenerator($nodes, $ids, $random);
        $members = new MemberGraphGenerator($nodes, $ids, $random);
        $graphs = new GraphGenerator($classLikes, $members, new LeafGraphGenerator($nodes), $ids, $random);

        yield 'drawing from seed 42' => [$members, $graphs, $nodes];
    }
}
