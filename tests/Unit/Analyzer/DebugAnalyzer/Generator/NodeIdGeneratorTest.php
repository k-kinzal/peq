<?php

declare(strict_types=1);

namespace Tests\Unit\Analyzer\DebugAnalyzer\Generator;

use App\Analyzer\DebugAnalyzer\Generator\NodeIdGenerator;
use App\Analyzer\Graph\Direction;
use App\Analyzer\Graph\NodeId\BuiltinNodeId;
use App\Analyzer\Graph\NodeId\ClassNodeId;
use App\Analyzer\Graph\NodeId\ConstantNodeId;
use App\Analyzer\Graph\NodeId\EnumCaseNodeId;
use App\Analyzer\Graph\NodeId\EnumNodeId;
use App\Analyzer\Graph\NodeId\FunctionNodeId;
use App\Analyzer\Graph\NodeId\InterfaceNodeId;
use App\Analyzer\Graph\NodeId\MethodNodeId;
use App\Analyzer\Graph\NodeId\PropertyNodeId;
use App\Analyzer\Graph\NodeId\TraitNodeId;
use App\Analyzer\Graph\NodeId\UnknownNodeId;
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
#[CoversClass(NodeIdGenerator::class)]
#[UsesClass(\App\Analyzer\DebugAnalyzer\Generator\NameGenerator::class)]
#[UsesClass(\App\Analyzer\Graph\EdgeKind::class)]
#[UsesClass(\App\Analyzer\Graph\FileMeta::class)]
#[UsesClass(BuiltinNodeId::class)]
#[UsesClass(ClassNodeId::class)]
#[UsesClass(ConstantNodeId::class)]
#[UsesClass(EnumCaseNodeId::class)]
#[UsesClass(EnumNodeId::class)]
#[UsesClass(FunctionNodeId::class)]
#[UsesClass(InterfaceNodeId::class)]
#[UsesClass(MethodNodeId::class)]
#[UsesClass(PropertyNodeId::class)]
#[UsesClass(TraitNodeId::class)]
#[UsesClass(UnknownNodeId::class)]
#[UsesClass(\App\Analyzer\Graph\QualifiedName::class)]
#[Small]
final class NodeIdGeneratorTest extends TestCase
{
    public function testNodeKindDrawsOneOfTheKindsTheGraphCanHold(): void
    {
        self::assertContains(SeededGenerators::ids()->nodeKind(), NodeKind::cases());
    }

    public function testEdgeKindNeverDrawsAKindTheGraphDerivesForItself(): void
    {
        self::assertSame(Direction::Uses, SeededGenerators::ids()->edgeKind()->direction());
    }

    /**
     * @param class-string $expected
     */
    #[DataProvider('providerEveryKindAndItsIdentifierType')]
    public function testNodeIdBuildsTheIdentifierTypeThatKindUses(NodeKind $kind, string $expected): void
    {
        self::assertInstanceOf($expected, SeededGenerators::ids()->nodeId($kind));
    }

    /**
     * @return iterable<string, array{NodeKind, class-string}>
     */
    public static function providerEveryKindAndItsIdentifierType(): iterable
    {
        yield 'class' => [NodeKind::Klass, ClassNodeId::class];

        yield 'interface' => [NodeKind::Interface, InterfaceNodeId::class];

        yield 'trait' => [NodeKind::Trait, TraitNodeId::class];

        yield 'enum' => [NodeKind::Enum, EnumNodeId::class];

        yield 'method' => [NodeKind::Method, MethodNodeId::class];

        yield 'property' => [NodeKind::Property, PropertyNodeId::class];

        yield 'function' => [NodeKind::Function, FunctionNodeId::class];

        yield 'constant' => [NodeKind::Constant, ConstantNodeId::class];

        yield 'enum case' => [NodeKind::EnumCase, EnumCaseNodeId::class];

        yield 'builtin' => [NodeKind::Builtin, BuiltinNodeId::class];

        yield 'unresolved' => [NodeKind::Unknown, UnknownNodeId::class];
    }

    public function testNodeIdDrawsAKindWhenGivenNone(): void
    {
        self::assertNotSame('', SeededGenerators::ids()->nodeId()->toString());
    }

    public function testClassNodeIdNamesAClass(): void
    {
        self::assertStringEndsWith('Class', SeededGenerators::ids()->classNodeId()->className);
    }

    public function testInterfaceNodeIdNamesAnInterface(): void
    {
        self::assertStringEndsWith('Interface', SeededGenerators::ids()->interfaceNodeId()->interfaceName);
    }

    public function testTraitNodeIdNamesATrait(): void
    {
        self::assertStringEndsWith('Trait', SeededGenerators::ids()->traitNodeId()->traitName);
    }

    public function testEnumNodeIdNamesAnEnum(): void
    {
        self::assertStringEndsWith('Enum', SeededGenerators::ids()->enumNodeId()->enumName);
    }

    public function testMethodNodeIdNamesAMethodOfAClass(): void
    {
        self::assertStringContainsString('::', SeededGenerators::ids()->methodNodeId()->toString());
    }

    public function testPropertyNodeIdNamesAPropertyOfAClass(): void
    {
        self::assertStringEndsWith('Property', SeededGenerators::ids()->propertyNodeId()->propertyName);
    }

    public function testFunctionNodeIdNamesAFunction(): void
    {
        self::assertStringEndsWith('Function', SeededGenerators::ids()->functionNodeId()->functionName);
    }

    public function testConstantNodeIdNamesAConstantOfAClass(): void
    {
        self::assertStringEndsWith('_CONST', SeededGenerators::ids()->constantNodeId()->constantName);
    }

    public function testEnumCaseNodeIdNamesACaseOfAnEnum(): void
    {
        self::assertStringEndsWith('_CASE', SeededGenerators::ids()->enumCaseNodeId()->caseName);
    }

    public function testBuiltinNodeIdNamesABuiltinType(): void
    {
        self::assertNotSame('', SeededGenerators::ids()->builtinNodeId()->name);
    }

    public function testUnknownNodeIdNamesAnUnresolvedSymbol(): void
    {
        self::assertNotSame('', SeededGenerators::ids()->unknownNodeId()->name);
    }

    public function testTypeNodeIdBuildsSomethingThatCanStandInATypePosition(): void
    {
        $id = SeededGenerators::ids()->typeNodeId();

        self::assertContains($id::class, [BuiltinNodeId::class, ClassNodeId::class, EnumNodeId::class, InterfaceNodeId::class]);
    }

    public function testFileMetaPointsAtALineOfAGeneratedPhpFile(): void
    {
        $meta = SeededGenerators::ids()->fileMeta();

        self::assertStringEndsWith('.php', $meta->path);
        self::assertGreaterThan(0, $meta->line);
    }

    public function testTheSameSeedProducesTheSameIdentifier(): void
    {
        self::assertSame(
            SeededGenerators::ids(7)->methodNodeId()->toString(),
            SeededGenerators::ids(7)->methodNodeId()->toString(),
        );
    }
}
