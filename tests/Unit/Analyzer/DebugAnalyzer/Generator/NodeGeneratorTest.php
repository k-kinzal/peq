<?php

declare(strict_types=1);

namespace Tests\Unit\Analyzer\DebugAnalyzer\Generator;

use App\Analyzer\DebugAnalyzer\Generator\NameGenerator;
use App\Analyzer\DebugAnalyzer\Generator\NodeGenerator;
use App\Analyzer\DebugAnalyzer\Generator\NodeIdGenerator;
use App\Analyzer\DebugAnalyzer\Generator\RandomSource;
use App\Analyzer\Graph\Node\BuiltinNode;
use App\Analyzer\Graph\Node\ClassNode;
use App\Analyzer\Graph\Node\EnumNode;
use App\Analyzer\Graph\Node\GraphInterfaceNode;
use App\Analyzer\Graph\NodeId\BuiltinNodeId;
use App\Analyzer\Graph\NodeId\ClassNodeId;
use App\Analyzer\Graph\NodeId\EnumNodeId;
use App\Analyzer\Graph\NodeId\InterfaceNodeId;
use App\Analyzer\Graph\NodeId\UnknownNodeId;
use App\Analyzer\Graph\NodeKind;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(NodeGenerator::class)]
#[UsesClass(NameGenerator::class)]
#[UsesClass(NodeIdGenerator::class)]
#[UsesClass(\App\Analyzer\Graph\FileMeta::class)]
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
#[UsesClass(UnknownNodeId::class)]
#[UsesClass(BuiltinNode::class)]
#[UsesClass(ClassNode::class)]
#[UsesClass(\App\Analyzer\Graph\Node\ConstantNode::class)]
#[UsesClass(\App\Analyzer\Graph\Node\EnumCaseNode::class)]
#[UsesClass(EnumNode::class)]
#[UsesClass(\App\Analyzer\Graph\Node\FunctionNode::class)]
#[UsesClass(GraphInterfaceNode::class)]
#[UsesClass(\App\Analyzer\Graph\Node\MethodNode::class)]
#[UsesClass(\App\Analyzer\Graph\Node\PropertyNode::class)]
#[UsesClass(\App\Analyzer\Graph\Node\TraitNode::class)]
#[UsesClass(\App\Analyzer\Graph\Node\UnknownNode::class)]
#[UsesClass(\App\Analyzer\Graph\QualifiedName::class)]
#[UsesClass(RandomSource::class)]
#[UsesClass(\App\Analyzer\Graph\Declaration\Modifiers::class)]
#[UsesClass(\App\Analyzer\Graph\Declaration\SymbolDeclaration::class)]
#[UsesClass(\App\Analyzer\Graph\NodeId\ClosureNodeId::class)]
#[UsesClass(\App\Analyzer\Graph\Node\ClosureNode::class)]
#[Small]
final class NodeGeneratorTest extends TestCase
{
    #[DataProvider('providerEveryKindOfSymbol')]
    public function testNodeBuildsANodeOfTheKindItWasAskedFor(NodeKind $kind): void
    {
        self::assertSame($kind, (new NodeGenerator(new NodeIdGenerator(new NameGenerator(new RandomSource(42)), new RandomSource(42)), new RandomSource(42)))->node($kind)->kind());
    }

    /**
     * @return iterable<string, array{NodeKind}>
     */
    public static function providerEveryKindOfSymbol(): iterable
    {
        foreach (NodeKind::cases() as $kind) {
            yield $kind->value => [$kind];
        }
    }

    #[DataProvider('providerNodeGenerator')]
    public function testNodeDrawsAKindWhenGivenNone(NodeGenerator $nodes): void
    {
        self::assertContains($nodes->node()->kind(), NodeKind::cases());
    }

    #[DataProvider('providerNodeGenerator')]
    public function testClassNodeReusesAnIdentifierItIsGiven(NodeGenerator $nodes): void
    {
        $id = ClassNodeId::of('App\Domain\Invoice');

        self::assertSame($id, $nodes->classNode($id)->id());
    }

    #[DataProvider('providerNodeGenerator')]
    public function testClassNodeDrawsAnIdentifierWhenGivenNone(NodeGenerator $nodes): void
    {
        self::assertStringEndsWith('Class', $nodes->classNode()->id()->className);
    }

    #[DataProvider('providerNodeGenerator')]
    public function testInterfaceNodeStandsForAnInterface(NodeGenerator $nodes): void
    {
        self::assertSame(NodeKind::Interface, $nodes->interfaceNode()->kind());
    }

    #[DataProvider('providerNodeGenerator')]
    public function testTraitNodeStandsForATrait(NodeGenerator $nodes): void
    {
        self::assertSame(NodeKind::Trait, $nodes->traitNode()->kind());
    }

    #[DataProvider('providerNodeGenerator')]
    public function testEnumNodeStandsForAnEnum(NodeGenerator $nodes): void
    {
        self::assertSame(NodeKind::Enum, $nodes->enumNode()->kind());
    }

    #[DataProvider('providerNodeGenerator')]
    public function testEnumCaseNodeStandsForAnEnumCase(NodeGenerator $nodes): void
    {
        self::assertSame(NodeKind::EnumCase, $nodes->enumCaseNode()->kind());
    }

    #[DataProvider('providerNodeGenerator')]
    public function testMethodNodeStandsForAMethod(NodeGenerator $nodes): void
    {
        self::assertSame(NodeKind::Method, $nodes->methodNode()->kind());
    }

    #[DataProvider('providerNodeGenerator')]
    public function testPropertyNodeStandsForAProperty(NodeGenerator $nodes): void
    {
        self::assertSame(NodeKind::Property, $nodes->propertyNode()->kind());
    }

    #[DataProvider('providerNodeGenerator')]
    public function testFunctionNodeStandsForAFunction(NodeGenerator $nodes): void
    {
        self::assertSame(NodeKind::Function, $nodes->functionNode()->kind());
    }

    #[DataProvider('providerNodeGenerator')]
    public function testConstantNodeStandsForAConstant(NodeGenerator $nodes): void
    {
        self::assertSame(NodeKind::Constant, $nodes->constantNode()->kind());
    }

    #[DataProvider('providerNodeGenerator')]
    public function testBuiltinNodeStandsForABuiltinType(NodeGenerator $nodes): void
    {
        self::assertSame(NodeKind::Builtin, $nodes->builtinNode()->kind());
    }

    #[DataProvider('providerNodeGenerator')]
    public function testUnknownNodeStandsForAnUnresolvedSymbol(NodeGenerator $nodes): void
    {
        self::assertSame(NodeKind::Unknown, $nodes->unknownNode()->kind());
    }

    #[DataProvider('providerNodeGenerator')]
    public function testTypeNodeBuildsSomethingThatCanStandInATypePosition(NodeGenerator $nodes): void
    {
        $node = $nodes->typeNode();

        self::assertContains($node::class, [BuiltinNode::class, ClassNode::class, EnumNode::class, GraphInterfaceNode::class]);
    }

    #[DataProvider('providerNodeGenerator')]
    public function testEveryGeneratedNodeCarriesASourceLocation(NodeGenerator $nodes): void
    {
        self::assertNotNull($nodes->classNode()->meta());
    }

    /**
     * @return iterable<string, array{NodeGenerator}>
     */
    public static function providerNodeGenerator(): iterable
    {
        $random = new RandomSource(42);

        yield 'drawing from seed 42' => [new NodeGenerator(new NodeIdGenerator(new NameGenerator($random), $random), $random)];
    }

    public function testTheSameSeedProducesTheSameNode(): void
    {
        $first = new RandomSource(7);
        $again = new RandomSource(7);

        self::assertSame(
            (new NodeGenerator(new NodeIdGenerator(new NameGenerator($first), $first), $first))->methodNode()->id()->toString(),
            (new NodeGenerator(new NodeIdGenerator(new NameGenerator($again), $again), $again))->methodNode()->id()->toString(),
        );
    }

    public function testUnknownNodeReusesAnIdentifierItIsGiven(): void
    {
        $random = new RandomSource(7);
        $nodes = new NodeGenerator(new NodeIdGenerator(new NameGenerator($random), $random), $random);

        self::assertSame('App\Domain\Missing', $nodes->unknownNode(new UnknownNodeId('App\Domain\Missing'))->id()->toString());
    }

    public function testUnknownNodeDrawsTheIdentifierOfItsSeedWhenGivenNone(): void
    {
        $random = new RandomSource(7);
        $nodes = new NodeGenerator(new NodeIdGenerator(new NameGenerator($random), $random), $random);

        self::assertSame('SaepeSaepe', $nodes->unknownNode()->id()->toString());
    }

    /**
     * @param class-string $expected
     */
    #[DataProvider('providerEveryTypePosition')]
    public function testTypeNodeBuildsTheNodeOfTheTypeItIsGiven(BuiltinNodeId|ClassNodeId|EnumNodeId|InterfaceNodeId $nodeId, string $expected): void
    {
        $random = new RandomSource(7);
        $node = (new NodeGenerator(new NodeIdGenerator(new NameGenerator($random), $random), $random))->typeNode($nodeId);

        self::assertInstanceOf($expected, $node);
        self::assertSame($nodeId, $node->id());
    }

    /**
     * @return iterable<string, array{BuiltinNodeId|ClassNodeId|EnumNodeId|InterfaceNodeId, class-string}>
     */
    public static function providerEveryTypePosition(): iterable
    {
        yield 'a builtin type' => [BuiltinNodeId::of('int'), BuiltinNode::class];

        yield 'a class' => [ClassNodeId::of('App\Domain\Invoice'), ClassNode::class];

        yield 'an interface' => [InterfaceNodeId::of('App\Domain\Payable'), GraphInterfaceNode::class];

        yield 'an enum' => [EnumNodeId::of('App\Domain\InvoiceState'), EnumNode::class];
    }

    public function testTypeNodeDrawsTheTypeOfItsSeedWhenGivenNone(): void
    {
        $random = new RandomSource(7);
        $node = (new NodeGenerator(new NodeIdGenerator(new NameGenerator($random), $random), $random))->typeNode();

        self::assertInstanceOf(EnumNode::class, $node);
        self::assertSame('AdRerumHarum\EnimDolor\ModiMinusEnum', $node->id()->toString());
    }

    public function testClosureNodePreservesAnExplicitLocationIdentity(): void
    {
        $random = new RandomSource(1);
        $ids = new NodeIdGenerator(new NameGenerator($random), $random);
        $generator = new NodeGenerator($ids, $random);
        $id = new \App\Analyzer\Graph\NodeId\ClosureNodeId('run', 2, 3);
        $node = $generator->closureNode($id);
        self::assertSame($id, $node->id());
        self::assertSame('run', $node->owner->id()->toString());
        self::assertSame(NodeKind::Closure, $node->kind());
    }
}
