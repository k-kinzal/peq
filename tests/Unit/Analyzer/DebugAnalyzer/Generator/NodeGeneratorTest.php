<?php

declare(strict_types=1);

namespace Tests\Unit\Analyzer\DebugAnalyzer\Generator;

use App\Analyzer\DebugAnalyzer\Generator\NodeGenerator;
use App\Analyzer\Graph\Node\BuiltinNode;
use App\Analyzer\Graph\Node\ClassNode;
use App\Analyzer\Graph\Node\EnumNode;
use App\Analyzer\Graph\Node\GraphInterfaceNode;
use App\Analyzer\Graph\NodeId\ClassNodeId;
use App\Analyzer\Graph\NodeKind;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Tests\Fixture\Analyzer\SeededGenerators;

/**
 * @internal
 */
#[CoversClass(NodeGenerator::class)]
#[UsesClass(\App\Analyzer\DebugAnalyzer\Generator\NameGenerator::class)]
#[UsesClass(\App\Analyzer\DebugAnalyzer\Generator\NodeIdGenerator::class)]
#[UsesClass(\App\Analyzer\Graph\FileMeta::class)]
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
#[UsesClass(\App\Analyzer\Graph\NodeId\UnknownNodeId::class)]
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
#[Small]
final class NodeGeneratorTest extends TestCase
{
    #[DataProvider('providerEveryKindOfSymbol')]
    public function testNodeBuildsANodeOfTheKindItWasAskedFor(NodeKind $kind): void
    {
        self::assertSame($kind, SeededGenerators::nodes()->node($kind)->kind());
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

    public function testNodeDrawsAKindWhenGivenNone(): void
    {
        self::assertContains(SeededGenerators::nodes()->node()->kind(), NodeKind::cases());
    }

    public function testClassNodeReusesAnIdentifierItIsGiven(): void
    {
        $id = ClassNodeId::of('App\Domain\Invoice');

        self::assertSame($id, SeededGenerators::nodes()->classNode($id)->id());
    }

    public function testClassNodeDrawsAnIdentifierWhenGivenNone(): void
    {
        self::assertStringEndsWith('Class', SeededGenerators::nodes()->classNode()->id()->className);
    }

    public function testInterfaceNodeStandsForAnInterface(): void
    {
        self::assertSame(NodeKind::Interface, SeededGenerators::nodes()->interfaceNode()->kind());
    }

    public function testTraitNodeStandsForATrait(): void
    {
        self::assertSame(NodeKind::Trait, SeededGenerators::nodes()->traitNode()->kind());
    }

    public function testEnumNodeStandsForAnEnum(): void
    {
        self::assertSame(NodeKind::Enum, SeededGenerators::nodes()->enumNode()->kind());
    }

    public function testEnumCaseNodeStandsForAnEnumCase(): void
    {
        self::assertSame(NodeKind::EnumCase, SeededGenerators::nodes()->enumCaseNode()->kind());
    }

    public function testMethodNodeStandsForAMethod(): void
    {
        self::assertSame(NodeKind::Method, SeededGenerators::nodes()->methodNode()->kind());
    }

    public function testPropertyNodeStandsForAProperty(): void
    {
        self::assertSame(NodeKind::Property, SeededGenerators::nodes()->propertyNode()->kind());
    }

    public function testFunctionNodeStandsForAFunction(): void
    {
        self::assertSame(NodeKind::Function, SeededGenerators::nodes()->functionNode()->kind());
    }

    public function testConstantNodeStandsForAConstant(): void
    {
        self::assertSame(NodeKind::Constant, SeededGenerators::nodes()->constantNode()->kind());
    }

    public function testBuiltinNodeStandsForABuiltinType(): void
    {
        self::assertSame(NodeKind::Builtin, SeededGenerators::nodes()->builtinNode()->kind());
    }

    public function testUnknownNodeStandsForAnUnresolvedSymbol(): void
    {
        self::assertSame(NodeKind::Unknown, SeededGenerators::nodes()->unknownNode()->kind());
    }

    public function testTypeNodeBuildsSomethingThatCanStandInATypePosition(): void
    {
        $node = SeededGenerators::nodes()->typeNode();

        self::assertContains($node::class, [BuiltinNode::class, ClassNode::class, EnumNode::class, GraphInterfaceNode::class]);
    }

    public function testEveryGeneratedNodeCarriesASourceLocation(): void
    {
        self::assertNotNull(SeededGenerators::nodes()->classNode()->meta());
    }

    public function testTheSameSeedProducesTheSameNode(): void
    {
        self::assertSame(
            SeededGenerators::nodes(7)->methodNode()->id()->toString(),
            SeededGenerators::nodes(7)->methodNode()->id()->toString(),
        );
    }
}
