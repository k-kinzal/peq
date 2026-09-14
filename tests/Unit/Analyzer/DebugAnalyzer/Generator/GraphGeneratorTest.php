<?php

declare(strict_types=1);

namespace Tests\Unit\Analyzer\DebugAnalyzer\Generator;

use App\Analyzer\DebugAnalyzer\Generator\GraphGenerator;
use App\Analyzer\Graph\NodeId\BuiltinNodeId;
use App\Analyzer\Graph\NodeId\ClassNodeId;
use App\Analyzer\Graph\NodeId\EnumNodeId;
use App\Analyzer\Graph\NodeId\InterfaceNodeId;
use App\Analyzer\Graph\NodeKind;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Tests\Fixture\Analyzer\DebugAnalyzer\SeededGenerators;
use Tests\Fixture\Graph\GraphInvariants;

/**
 * @internal
 */
#[CoversClass(GraphGenerator::class)]
#[UsesClass(\App\Analyzer\DebugAnalyzer\Generator\ClassLikeGraphGenerator::class)]
#[UsesClass(\App\Analyzer\DebugAnalyzer\Generator\GeneratedGraph::class)]
#[UsesClass(\App\Analyzer\DebugAnalyzer\Generator\LeafGraphGenerator::class)]
#[UsesClass(\App\Analyzer\DebugAnalyzer\Generator\MemberGraphGenerator::class)]
#[UsesClass(\App\Analyzer\DebugAnalyzer\Generator\NameGenerator::class)]
#[UsesClass(\App\Analyzer\DebugAnalyzer\Generator\FakerRandomSource::class)]
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
#[UsesClass(\App\Analyzer\Graph\Edge\Usage\StaticPropertyAccessEdge::class)]
#[UsesClass(\App\Analyzer\Graph\Edge\Inverse\UsedByEdge::class)]
#[UsesClass(\App\Analyzer\Graph\FileMeta::class)]
#[UsesClass(\App\Analyzer\Graph\Graph::class)]
#[UsesClass(BuiltinNodeId::class)]
#[UsesClass(ClassNodeId::class)]
#[UsesClass(\App\Analyzer\Graph\NodeId\ConstantNodeId::class)]
#[UsesClass(\App\Analyzer\Graph\NodeId\EnumCaseNodeId::class)]
#[UsesClass(EnumNodeId::class)]
#[UsesClass(\App\Analyzer\Graph\NodeId\FunctionNodeId::class)]
#[UsesClass(InterfaceNodeId::class)]
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
final class GraphGeneratorTest extends TestCase
{
    public function testGraphIsRootedAtSomethingPhpDeclaresAtTheTopOfAFile(): void
    {
        $graph = SeededGenerators::graphs()->graph(3);

        self::assertContains($graph->nodes()[0]->kind(), [NodeKind::Klass, NodeKind::Interface, NodeKind::Trait, NodeKind::Function]);
    }

    public function testGraphProducesMoreThanItsRootAtAUsefulDepth(): void
    {
        self::assertGreaterThan(1, count(SeededGenerators::graphs()->graph(3)->nodes()));
    }

    public function testGraphProducesRelationsReadableInBothDirections(): void
    {
        GraphInvariants::assertBidirectional(SeededGenerators::graphs()->graph(3));
    }

    public function testGraphProducesNoDanglingRelation(): void
    {
        GraphInvariants::assertEndpointsExist(SeededGenerators::graphs()->graph(3));
    }

    public function testGraphNamesEachSymbolOnlyOnce(): void
    {
        GraphInvariants::assertNodeUniqueness(SeededGenerators::graphs()->graph(3));
    }

    public function testTheSameSeedProducesTheSameGraph(): void
    {
        self::assertSame(
            count(SeededGenerators::graphs(7)->graph(3)->nodes()),
            count(SeededGenerators::graphs(7)->graph(3)->nodes()),
        );
    }

    #[DataProvider('providerTypePositionIdentifiers')]
    public function testTypeGraphIsRootedAtWhateverTypeItWasGiven(
        BuiltinNodeId|ClassNodeId|EnumNodeId|InterfaceNodeId $symbol,
        NodeKind $kind,
    ): void {
        self::assertSame($kind, SeededGenerators::graphs()->typeGraph($symbol, 1)->root->kind());
    }

    /**
     * @return iterable<string, array{BuiltinNodeId|ClassNodeId|EnumNodeId|InterfaceNodeId, NodeKind}>
     */
    public static function providerTypePositionIdentifiers(): iterable
    {
        yield 'a class' => [ClassNodeId::of('App\Domain\Invoice'), NodeKind::Klass];

        yield 'an interface' => [InterfaceNodeId::of('App\Domain\Payable'), NodeKind::Interface];

        yield 'an enum' => [EnumNodeId::of('App\Domain\InvoiceState'), NodeKind::Enum];

        yield 'a builtin type' => [BuiltinNodeId::of('int'), NodeKind::Builtin];
    }

    public function testTypeGraphDrawsATypeWhenGivenNone(): void
    {
        $result = SeededGenerators::graphs()->typeGraph(null, 1);

        self::assertContains($result->root->kind(), [NodeKind::Builtin, NodeKind::Klass, NodeKind::Interface, NodeKind::Enum]);
    }

    public function testClassGraphIsRoutedToTheGeneratorThatOwnsClasses(): void
    {
        self::assertSame(NodeKind::Klass, SeededGenerators::graphs()->classGraph(null, 1)->root->kind());
    }

    public function testMethodGraphIsRoutedToTheGeneratorThatOwnsMembers(): void
    {
        self::assertSame(NodeKind::Method, SeededGenerators::graphs()->methodGraph(null, 1)->root->kind());
    }

    public function testConstantGraphIsRoutedToTheGeneratorThatOwnsLeaves(): void
    {
        self::assertSame(NodeKind::Constant, SeededGenerators::graphs()->constantGraph()->root->kind());
    }

    public function testInterfaceGraphIsRoutedToTheGeneratorThatOwnsClassLikes(): void
    {
        self::assertSame(NodeKind::Interface, SeededGenerators::graphs()->interfaceGraph(null, 1)->root->kind());
    }

    public function testTraitGraphIsRoutedToTheGeneratorThatOwnsClassLikes(): void
    {
        self::assertSame(NodeKind::Trait, SeededGenerators::graphs()->traitGraph(null, 1)->root->kind());
    }

    public function testEnumGraphIsRoutedToTheGeneratorThatOwnsClassLikes(): void
    {
        self::assertSame(NodeKind::Enum, SeededGenerators::graphs()->enumGraph(null, 1)->root->kind());
    }

    public function testFunctionGraphIsRoutedToTheGeneratorThatOwnsMembers(): void
    {
        self::assertSame(NodeKind::Function, SeededGenerators::graphs()->functionGraph(null, 1)->root->kind());
    }

    public function testPropertyGraphIsRoutedToTheGeneratorThatOwnsMembers(): void
    {
        self::assertSame(NodeKind::Property, SeededGenerators::graphs()->propertyGraph(null, 1)->root->kind());
    }

    public function testEnumCaseGraphIsRoutedToTheGeneratorThatOwnsLeaves(): void
    {
        self::assertSame(NodeKind::EnumCase, SeededGenerators::graphs()->enumCaseGraph()->root->kind());
    }

    public function testBuiltinGraphIsRoutedToTheGeneratorThatOwnsLeaves(): void
    {
        self::assertSame(NodeKind::Builtin, SeededGenerators::graphs()->builtinGraph()->root->kind());
    }
}
