<?php

declare(strict_types=1);

namespace Tests\Unit\Analyzer\DebugAnalyzer\Generator;

use App\Analyzer\DebugAnalyzer\Generator\GeneratedGraph;
use App\Analyzer\DebugAnalyzer\Generator\MemberGraphGenerator;
use App\Analyzer\Graph\EdgeKind;
use App\Analyzer\Graph\NodeId\MethodNodeId;
use App\Analyzer\Graph\NodeKind;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Tests\Fixture\Analyzer\SeededGenerators;
use Tests\Fixture\Graph\GraphInvariants;

/**
 * @internal
 */
#[CoversClass(MemberGraphGenerator::class)]
#[UsesClass(\App\Analyzer\DebugAnalyzer\Generator\ClassLikeGraphGenerator::class)]
#[UsesClass(GeneratedGraph::class)]
#[UsesClass(\App\Analyzer\DebugAnalyzer\Generator\GraphGenerator::class)]
#[UsesClass(\App\Analyzer\DebugAnalyzer\Generator\LeafGraphGenerator::class)]
#[UsesClass(\App\Analyzer\DebugAnalyzer\Generator\NameGenerator::class)]
#[UsesClass(\App\Analyzer\DebugAnalyzer\Generator\NodeGenerator::class)]
#[UsesClass(\App\Analyzer\DebugAnalyzer\Generator\NodeIdGenerator::class)]
#[UsesClass(\App\Analyzer\Graph\AuthoredEdge::class)]
#[UsesClass(\App\Analyzer\Graph\Edge\ConstFetchEdge::class)]
#[UsesClass(\App\Analyzer\Graph\Edge\DeclarationConstantEdge::class)]
#[UsesClass(\App\Analyzer\Graph\Edge\DeclarationEnumCaseEdge::class)]
#[UsesClass(\App\Analyzer\Graph\Edge\DeclarationExtendsEdge::class)]
#[UsesClass(\App\Analyzer\Graph\Edge\DeclarationImplementsEdge::class)]
#[UsesClass(\App\Analyzer\Graph\Edge\DeclarationMethodEdge::class)]
#[UsesClass(\App\Analyzer\Graph\Edge\DeclarationPropertyEdge::class)]
#[UsesClass(\App\Analyzer\Graph\Edge\DeclarationTraitUseEdge::class)]
#[UsesClass(\App\Analyzer\Graph\Edge\DeclarationTypeParameterEdge::class)]
#[UsesClass(\App\Analyzer\Graph\Edge\DeclarationTypePropertyEdge::class)]
#[UsesClass(\App\Analyzer\Graph\Edge\DeclarationTypeReturnEdge::class)]
#[UsesClass(\App\Analyzer\Graph\Edge\DeclaredInEdge::class)]
#[UsesClass(\App\Analyzer\Graph\Edge\FunctionCallEdge::class)]
#[UsesClass(\App\Analyzer\Graph\Edge\InstantiationEdge::class)]
#[UsesClass(\App\Analyzer\Graph\Edge\MethodCallEdge::class)]
#[UsesClass(\App\Analyzer\Graph\Edge\PropertyAccessEdge::class)]
#[UsesClass(\App\Analyzer\Graph\Edge\StaticCallEdge::class)]
#[UsesClass(\App\Analyzer\Graph\Edge\StaticPropertyAccessEdge::class)]
#[UsesClass(\App\Analyzer\Graph\Edge\UsedByEdge::class)]
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
#[UsesClass(\App\Analyzer\Graph\Node\BuiltinNode::class)]
#[UsesClass(\App\Analyzer\Graph\Node\ClassNode::class)]
#[UsesClass(\App\Analyzer\Graph\Node\ConstantNode::class)]
#[UsesClass(\App\Analyzer\Graph\Node\EnumCaseNode::class)]
#[UsesClass(\App\Analyzer\Graph\Node\EnumNode::class)]
#[UsesClass(\App\Analyzer\Graph\Node\FunctionNode::class)]
#[UsesClass(\App\Analyzer\Graph\Node\GraphInterfaceNode::class)]
#[UsesClass(\App\Analyzer\Graph\Node\MethodNode::class)]
#[UsesClass(\App\Analyzer\Graph\Node\PropertyNode::class)]
#[UsesClass(\App\Analyzer\Graph\Node\TraitNode::class)]
#[UsesClass(\App\Analyzer\Graph\QualifiedName::class)]
#[Small]
final class MemberGraphGeneratorTest extends TestCase
{
    public function testMethodGraphIsRootedAtAMethod(): void
    {
        self::assertSame(NodeKind::Method, SeededGenerators::members()->methodGraph(SeededGenerators::graphs(), null, 2)->root->kind());
    }

    public function testMethodGraphReusesAnIdentifierItIsGiven(): void
    {
        $result = SeededGenerators::members()->methodGraph(SeededGenerators::graphs(), MethodNodeId::of('App\Domain\Invoice', 'total'), 1);

        self::assertSame('App\Domain\Invoice::total', $result->root->id()->toString());
    }

    public function testMethodGraphStopsAtDepthZero(): void
    {
        self::assertCount(1, SeededGenerators::members()->methodGraph(SeededGenerators::graphs(), null, 0)->graph->nodes());
    }

    public function testMethodGraphAlwaysCommitsToAReturnType(): void
    {
        $result = SeededGenerators::members()->methodGraph(SeededGenerators::graphs(), null, 1);
        $kinds = array_map(static fn ($edge): EdgeKind => $edge->kind(), $result->graph->authoredEdges());

        self::assertContains(EdgeKind::DeclarationTypeReturn, $kinds);
    }

    public function testFunctionGraphIsRootedAtAFunction(): void
    {
        self::assertSame(NodeKind::Function, SeededGenerators::members()->functionGraph(SeededGenerators::graphs(), null, 2)->root->kind());
    }

    public function testFunctionGraphStopsAtDepthZero(): void
    {
        self::assertCount(1, SeededGenerators::members()->functionGraph(SeededGenerators::graphs(), null, 0)->graph->nodes());
    }

    public function testPropertyGraphIsRootedAtAProperty(): void
    {
        self::assertSame(NodeKind::Property, SeededGenerators::members()->propertyGraph(SeededGenerators::graphs(), null, 2)->root->kind());
    }

    public function testPropertyGraphAlwaysCommitsToADeclaredType(): void
    {
        $result = SeededGenerators::members()->propertyGraph(SeededGenerators::graphs(), null, 1);
        $kinds = array_map(static fn ($edge): EdgeKind => $edge->kind(), $result->graph->authoredEdges());

        self::assertContains(EdgeKind::DeclarationTypeProperty, $kinds);
    }

    public function testPropertyGraphStopsAtDepthZero(): void
    {
        self::assertCount(1, SeededGenerators::members()->propertyGraph(SeededGenerators::graphs(), null, 0)->graph->nodes());
    }

    public function testCallableSignatureAddsTheDeclaredTypesToTheGraphItIsGiven(): void
    {
        $start = GeneratedGraph::rootedAt(SeededGenerators::nodes()->methodNode());
        $grown = SeededGenerators::members()->callableSignature(SeededGenerators::graphs(), $start, 2);

        self::assertGreaterThan(count($start->graph->nodes()), count($grown->graph->nodes()));
    }

    public function testCallableSignatureKeepsTheCallableAsTheRoot(): void
    {
        $start = GeneratedGraph::rootedAt(SeededGenerators::nodes()->methodNode());

        self::assertSame($start->root, SeededGenerators::members()->callableSignature(SeededGenerators::graphs(), $start, 2)->root);
    }

    public function testMethodCallsOnlyEverAddsToTheGraphItIsGiven(): void
    {
        $start = GeneratedGraph::rootedAt(SeededGenerators::nodes()->methodNode());
        $grown = SeededGenerators::members()->methodCalls(SeededGenerators::graphs(), $start, 2);

        self::assertGreaterThanOrEqual(count($start->graph->nodes()), count($grown->graph->nodes()));
    }

    public function testMethodAccessesOnlyEverAddsToTheGraphItIsGiven(): void
    {
        $start = GeneratedGraph::rootedAt(SeededGenerators::nodes()->methodNode());
        $grown = SeededGenerators::members()->methodAccesses(SeededGenerators::graphs(), $start, 2);

        self::assertGreaterThanOrEqual(count($start->graph->nodes()), count($grown->graph->nodes()));
    }

    public function testEveryMemberGraphKeepsTheGraphInvariants(): void
    {
        $result = SeededGenerators::members()->methodGraph(SeededGenerators::graphs(), null, 2);

        GraphInvariants::assertBidirectional($result->graph);
        GraphInvariants::assertEndpointsExist($result->graph);
    }
}
