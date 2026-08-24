<?php

declare(strict_types=1);

namespace Tests\Unit\Analyzer\DebugAnalyzer\Generator;

use App\Analyzer\Graph\NodeKind;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Tests\Fixture\Analyzer\SeededGenerators;
use Tests\Fixture\Graph\GraphInvariants;

/**
 * @internal
 */
#[CoversClass(\App\Analyzer\DebugAnalyzer\Generator\GraphGenerator::class)]
#[UsesClass(\App\Analyzer\DebugAnalyzer\Generator\ClassLikeGraphGenerator::class)]
#[UsesClass(\App\Analyzer\DebugAnalyzer\Generator\GeneratedGraph::class)]
#[UsesClass(\App\Analyzer\DebugAnalyzer\Generator\LeafGraphGenerator::class)]
#[UsesClass(\App\Analyzer\DebugAnalyzer\Generator\MemberGraphGenerator::class)]
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
final class SymbolGraphGeneratorTest extends TestCase
{
    public function testClassGraphIsRootedAtAClass(): void
    {
        self::assertSame(NodeKind::Klass, SeededGenerators::symbolGraph('classGraph')->root->kind());
    }

    public function testInterfaceGraphIsRootedAtAnInterface(): void
    {
        self::assertSame(NodeKind::Interface, SeededGenerators::symbolGraph('interfaceGraph')->root->kind());
    }

    public function testTraitGraphIsRootedAtATrait(): void
    {
        self::assertSame(NodeKind::Trait, SeededGenerators::symbolGraph('traitGraph')->root->kind());
    }

    public function testEnumGraphIsRootedAtAnEnum(): void
    {
        self::assertSame(NodeKind::Enum, SeededGenerators::symbolGraph('enumGraph')->root->kind());
    }

    public function testMethodGraphIsRootedAtAMethod(): void
    {
        self::assertSame(NodeKind::Method, SeededGenerators::symbolGraph('methodGraph')->root->kind());
    }

    public function testFunctionGraphIsRootedAtAFunction(): void
    {
        self::assertSame(NodeKind::Function, SeededGenerators::symbolGraph('functionGraph')->root->kind());
    }

    public function testPropertyGraphIsRootedAtAProperty(): void
    {
        self::assertSame(NodeKind::Property, SeededGenerators::symbolGraph('propertyGraph')->root->kind());
    }

    public function testConstantGraphIsRootedAtAClassConstant(): void
    {
        self::assertSame(NodeKind::Constant, SeededGenerators::symbolGraph('constantGraph')->root->kind());
    }

    public function testEnumCaseGraphIsRootedAtAnEnumCase(): void
    {
        self::assertSame(NodeKind::EnumCase, SeededGenerators::symbolGraph('enumCaseGraph')->root->kind());
    }

    public function testBuiltinGraphIsRootedAtABuiltinType(): void
    {
        self::assertSame(NodeKind::Builtin, SeededGenerators::symbolGraph('builtinGraph')->root->kind());
    }

    public function testTypeGraphIsRootedAtSomethingThatCanStandInATypePosition(): void
    {
        $root = SeededGenerators::graphs()->typeGraph(null, 1)->root;

        self::assertContains($root->kind(), [NodeKind::Builtin, NodeKind::Klass, NodeKind::Interface, NodeKind::Enum]);
    }

    #[DataProvider('providerEverySymbolItCanGenerate')]
    public function testEveryOperationIsRootedAtTheKindOfSymbolItNames(string $operation, NodeKind $kind): void
    {
        self::assertSame($kind, SeededGenerators::symbolGraph($operation)->root->kind());
    }

    #[DataProvider('providerEverySymbolItCanGenerate')]
    public function testEveryOperationRecordsItsRootInTheGraphItReturns(string $operation, NodeKind $kind): void
    {
        $result = SeededGenerators::symbolGraph($operation);

        self::assertSame($kind, $result->graph->nodeNamed($result->root->id()->toString())?->kind());
    }

    #[DataProvider('providerEverySymbolItCanGenerate')]
    public function testEveryOperationProducesAGraphWhoseRelationsAreReadableBothWays(string $operation, NodeKind $kind): void
    {
        $result = SeededGenerators::symbolGraph($operation);

        GraphInvariants::assertBidirectional($result->graph);
        self::assertSame($kind, $result->root->kind());
    }

    #[DataProvider('providerEverySymbolItCanGenerate')]
    public function testEveryOperationProducesAGraphWithNoDanglingRelation(string $operation, NodeKind $kind): void
    {
        $result = SeededGenerators::symbolGraph($operation);

        GraphInvariants::assertEndpointsExist($result->graph);
        self::assertSame($kind, $result->root->kind());
    }

    #[DataProvider('providerEverySymbolItCanGenerate')]
    public function testEveryOperationStopsAtDepthZero(string $operation, NodeKind $kind): void
    {
        $result = SeededGenerators::symbolGraph($operation, depth: 0);

        self::assertCount(1, $result->graph->nodes());
        self::assertSame($kind, $result->root->kind());
    }

    /**
     * @return iterable<string, array{string, NodeKind}>
     */
    public static function providerEverySymbolItCanGenerate(): iterable
    {
        yield 'a class' => ['classGraph', NodeKind::Klass];

        yield 'an interface' => ['interfaceGraph', NodeKind::Interface];

        yield 'a trait' => ['traitGraph', NodeKind::Trait];

        yield 'an enum' => ['enumGraph', NodeKind::Enum];

        yield 'a method' => ['methodGraph', NodeKind::Method];

        yield 'a function' => ['functionGraph', NodeKind::Function];

        yield 'a property' => ['propertyGraph', NodeKind::Property];

        yield 'a constant' => ['constantGraph', NodeKind::Constant];

        yield 'an enum case' => ['enumCaseGraph', NodeKind::EnumCase];

        yield 'a builtin type' => ['builtinGraph', NodeKind::Builtin];
    }
}
