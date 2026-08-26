<?php

declare(strict_types=1);

namespace Tests\Unit\Analyzer\DebugAnalyzer\Generator;

use App\Analyzer\DebugAnalyzer\Generator\ClassLikeGraphGenerator;
use App\Analyzer\DebugAnalyzer\Generator\GeneratedGraph;
use App\Analyzer\Graph\EdgeKind;
use App\Analyzer\Graph\NodeId\ClassNodeId;
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
#[CoversClass(ClassLikeGraphGenerator::class)]
#[UsesClass(GeneratedGraph::class)]
#[UsesClass(\App\Analyzer\DebugAnalyzer\Generator\GraphGenerator::class)]
#[UsesClass(\App\Analyzer\DebugAnalyzer\Generator\LeafGraphGenerator::class)]
#[UsesClass(\App\Analyzer\DebugAnalyzer\Generator\MemberGraphGenerator::class)]
#[UsesClass(\App\Analyzer\DebugAnalyzer\Generator\NameGenerator::class)]
#[UsesClass(\App\Analyzer\DebugAnalyzer\Generator\NodeGenerator::class)]
#[UsesClass(\App\Analyzer\DebugAnalyzer\Generator\NodeIdGenerator::class)]
#[UsesClass(\App\Analyzer\Graph\AuthoredEdge::class)]
#[UsesClass(\App\Analyzer\Graph\Edge\Usage\ConstFetchEdge::class)]
#[UsesClass(\App\Analyzer\Graph\Edge\Declaration\ConstantEdge::class)]
#[UsesClass(\App\Analyzer\Graph\Edge\Declaration\EnumCaseEdge::class)]
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
#[UsesClass(ClassNodeId::class)]
#[UsesClass(\App\Analyzer\Graph\NodeId\ConstantNodeId::class)]
#[UsesClass(\App\Analyzer\Graph\NodeId\EnumCaseNodeId::class)]
#[UsesClass(\App\Analyzer\Graph\NodeId\EnumNodeId::class)]
#[UsesClass(\App\Analyzer\Graph\NodeId\FunctionNodeId::class)]
#[UsesClass(\App\Analyzer\Graph\NodeId\InterfaceNodeId::class)]
#[UsesClass(\App\Analyzer\Graph\NodeId\MethodNodeId::class)]
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
final class ClassLikeGraphGeneratorTest extends TestCase
{
    public function testClassGraphIsRootedAtAClass(): void
    {
        self::assertSame(NodeKind::Klass, SeededGenerators::classLikes()->classGraph(SeededGenerators::graphs(), null, 2)->root->kind());
    }

    public function testClassGraphReusesAnIdentifierItIsGiven(): void
    {
        $result = SeededGenerators::classLikes()->classGraph(SeededGenerators::graphs(), ClassNodeId::of('App\Domain\Invoice'), 1);

        self::assertSame('App\Domain\Invoice', $result->root->id()->toString());
    }

    public function testClassGraphStopsAtDepthZero(): void
    {
        self::assertCount(1, SeededGenerators::classLikes()->classGraph(SeededGenerators::graphs(), null, 0)->graph->nodes());
    }

    public function testClassMembersOnlyEverAddsToTheGraphItIsGiven(): void
    {
        $start = GeneratedGraph::rootedAt(SeededGenerators::nodes()->classNode());
        $grown = SeededGenerators::classLikes()->classMembers(SeededGenerators::graphs(), $start, 2);

        self::assertGreaterThanOrEqual(count($start->graph->nodes()), count($grown->graph->nodes()));
    }

    public function testClassMembersKeepsTheClassAsTheRoot(): void
    {
        $start = GeneratedGraph::rootedAt(SeededGenerators::nodes()->classNode());

        self::assertSame($start->root, SeededGenerators::classLikes()->classMembers(SeededGenerators::graphs(), $start, 2)->root);
    }

    public function testClassInheritanceOnlyEverAddsToTheGraphItIsGiven(): void
    {
        $start = GeneratedGraph::rootedAt(SeededGenerators::nodes()->classNode());
        $grown = SeededGenerators::classLikes()->classInheritance(SeededGenerators::graphs(), $start, 2);

        self::assertGreaterThanOrEqual(count($start->graph->nodes()), count($grown->graph->nodes()));
    }

    public function testInterfaceGraphIsRootedAtAnInterface(): void
    {
        self::assertSame(NodeKind::Interface, SeededGenerators::classLikes()->interfaceGraph(SeededGenerators::graphs(), null, 2)->root->kind());
    }

    public function testInterfaceGraphStopsAtDepthZero(): void
    {
        self::assertCount(1, SeededGenerators::classLikes()->interfaceGraph(SeededGenerators::graphs(), null, 0)->graph->nodes());
    }

    public function testTraitGraphIsRootedAtATrait(): void
    {
        self::assertSame(NodeKind::Trait, SeededGenerators::classLikes()->traitGraph(SeededGenerators::graphs(), null, 2)->root->kind());
    }

    public function testTraitGraphStopsAtDepthZero(): void
    {
        self::assertCount(1, SeededGenerators::classLikes()->traitGraph(SeededGenerators::graphs(), null, 0)->graph->nodes());
    }

    public function testEnumGraphIsRootedAtAnEnum(): void
    {
        self::assertSame(NodeKind::Enum, SeededGenerators::classLikes()->enumGraph(SeededGenerators::graphs(), null, 2)->root->kind());
    }

    public function testEnumGraphRelatesEveryCaseItGeneratesToTheEnum(): void
    {
        $result = SeededGenerators::classLikes()->enumGraph(SeededGenerators::graphs(), null, 2);
        $kinds = array_map(static fn ($edge): EdgeKind => $edge->kind(), $result->graph->authoredEdges());

        self::assertSame($kinds, array_values(array_filter($kinds, static fn (EdgeKind $kind): bool => $kind === EdgeKind::DeclarationEnumCase)));
    }

    public function testMemberCountNeverAsksForMoreMembersThanAReadableGraphHolds(): void
    {
        self::assertLessThanOrEqual(5, SeededGenerators::classLikes()->memberCount());
    }

    public function testMemberCountCanAskForNoMembersAtAll(): void
    {
        self::assertGreaterThanOrEqual(0, SeededGenerators::classLikes()->memberCount());
    }

    public function testEveryClassLikeGraphKeepsTheGraphInvariants(): void
    {
        $result = SeededGenerators::classLikes()->classGraph(SeededGenerators::graphs(), null, 2);

        GraphInvariants::assertBidirectional($result->graph);
        GraphInvariants::assertEndpointsExist($result->graph);
    }
}
