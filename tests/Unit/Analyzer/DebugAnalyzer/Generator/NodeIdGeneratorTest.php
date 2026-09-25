<?php

declare(strict_types=1);

namespace Tests\Unit\Analyzer\DebugAnalyzer\Generator;

use App\Analyzer\DebugAnalyzer\Generator\NameGenerator;
use App\Analyzer\DebugAnalyzer\Generator\NodeIdGenerator;
use App\Analyzer\DebugAnalyzer\Generator\RandomSource;
use App\Analyzer\Graph\Direction;
use App\Analyzer\Graph\EdgeKind;
use App\Analyzer\Graph\Node;
use App\Analyzer\Graph\NodeId;
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
use Closure;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(NodeIdGenerator::class)]
#[UsesClass(NameGenerator::class)]
#[UsesClass(EdgeKind::class)]
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
#[UsesClass(RandomSource::class)]
#[UsesClass(NodeId\ClosureNodeId::class)]
#[Small]
final class NodeIdGeneratorTest extends TestCase
{
    #[DataProvider('providerNodeIdGenerator')]
    public function testNodeKindDrawsOneOfTheKindsTheGraphCanHold(NodeIdGenerator $ids): void
    {
        self::assertContains($ids->nodeKind(), NodeKind::cases());
    }

    #[DataProvider('providerNodeIdGenerator')]
    public function testEdgeKindNeverDrawsAKindTheGraphDerivesForItself(NodeIdGenerator $ids): void
    {
        self::assertSame(Direction::Uses, $ids->edgeKind()->direction());
    }

    /**
     * @param class-string $expected
     */
    #[DataProvider('providerEveryKindAndItsIdentifierType')]
    public function testNodeIdBuildsTheIdentifierTypeThatKindUses(NodeKind $kind, string $expected): void
    {
        self::assertInstanceOf($expected, (new NodeIdGenerator(new NameGenerator(new RandomSource(42)), new RandomSource(42)))->nodeId($kind));
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

    #[DataProvider('providerNodeIdGenerator')]
    public function testNodeIdDrawsAKindWhenGivenNone(NodeIdGenerator $ids): void
    {
        self::assertNotSame('', $ids->nodeId()->toString());
    }

    #[DataProvider('providerNodeIdGenerator')]
    public function testClassNodeIdNamesAClass(NodeIdGenerator $ids): void
    {
        self::assertStringEndsWith('Class', $ids->classNodeId()->className);
    }

    #[DataProvider('providerNodeIdGenerator')]
    public function testInterfaceNodeIdNamesAnInterface(NodeIdGenerator $ids): void
    {
        self::assertStringEndsWith('Interface', $ids->interfaceNodeId()->interfaceName);
    }

    #[DataProvider('providerNodeIdGenerator')]
    public function testTraitNodeIdNamesATrait(NodeIdGenerator $ids): void
    {
        self::assertStringEndsWith('Trait', $ids->traitNodeId()->traitName);
    }

    #[DataProvider('providerNodeIdGenerator')]
    public function testEnumNodeIdNamesAnEnum(NodeIdGenerator $ids): void
    {
        self::assertStringEndsWith('Enum', $ids->enumNodeId()->enumName);
    }

    #[DataProvider('providerNodeIdGenerator')]
    public function testMethodNodeIdNamesAMethodOfAClass(NodeIdGenerator $ids): void
    {
        self::assertStringContainsString('::', $ids->methodNodeId()->toString());
    }

    #[DataProvider('providerNodeIdGenerator')]
    public function testPropertyNodeIdNamesAPropertyOfAClass(NodeIdGenerator $ids): void
    {
        self::assertStringEndsWith('Property', $ids->propertyNodeId()->propertyName);
    }

    #[DataProvider('providerNodeIdGenerator')]
    public function testFunctionNodeIdNamesAFunction(NodeIdGenerator $ids): void
    {
        self::assertStringEndsWith('Function', $ids->functionNodeId()->functionName);
    }

    #[DataProvider('providerNodeIdGenerator')]
    public function testConstantNodeIdNamesAConstantOfAClass(NodeIdGenerator $ids): void
    {
        self::assertStringEndsWith('_CONST', $ids->constantNodeId()->constantName);
    }

    #[DataProvider('providerNodeIdGenerator')]
    public function testEnumCaseNodeIdNamesACaseOfAnEnum(NodeIdGenerator $ids): void
    {
        self::assertStringEndsWith('_CASE', $ids->enumCaseNodeId()->caseName);
    }

    #[DataProvider('providerNodeIdGenerator')]
    public function testBuiltinNodeIdNamesABuiltinType(NodeIdGenerator $ids): void
    {
        self::assertNotSame('', $ids->builtinNodeId()->name);
    }

    #[DataProvider('providerNodeIdGenerator')]
    public function testUnknownNodeIdNamesAnUnresolvedSymbol(NodeIdGenerator $ids): void
    {
        self::assertNotSame('', $ids->unknownNodeId()->name);
    }

    #[DataProvider('providerNodeIdGenerator')]
    public function testTypeNodeIdBuildsSomethingThatCanStandInATypePosition(NodeIdGenerator $ids): void
    {
        $id = $ids->typeNodeId();

        self::assertContains($id::class, [BuiltinNodeId::class, ClassNodeId::class, EnumNodeId::class, InterfaceNodeId::class]);
    }

    #[DataProvider('providerNodeIdGenerator')]
    public function testFileMetaPointsAtALineOfAGeneratedPhpFile(NodeIdGenerator $ids): void
    {
        $meta = $ids->fileMeta();

        self::assertStringEndsWith('.php', $meta->path);
        self::assertGreaterThan(0, $meta->line);
    }

    /**
     * @return iterable<string, array{NodeIdGenerator}>
     */
    public static function providerNodeIdGenerator(): iterable
    {
        $random = new RandomSource(42);

        yield 'drawing from seed 42' => [new NodeIdGenerator(new NameGenerator($random), $random)];
    }

    public function testTheSameSeedProducesTheSameIdentifier(): void
    {
        $first = new RandomSource(7);
        $again = new RandomSource(7);

        self::assertSame(
            (new NodeIdGenerator(new NameGenerator($first), $first))->methodNodeId()->toString(),
            (new NodeIdGenerator(new NameGenerator($again), $again))->methodNodeId()->toString(),
        );
    }

    /**
     * @param Closure(NodeIdGenerator): NodeId<Node> $identifier
     * @param class-string                           $type
     */
    #[DataProvider('providerIdentifiersDrawnFromSeedSeven')]
    public function testEveryIdentifierIsSpelledFromTheDrawsOfItsSeed(Closure $identifier, string $type, string $expected): void
    {
        $random = new RandomSource(7);
        $drawn = $identifier(new NodeIdGenerator(new NameGenerator($random), $random));

        self::assertInstanceOf($type, $drawn);
        self::assertSame($expected, $drawn->toString());
    }

    /**
     * @return iterable<string, array{Closure(NodeIdGenerator): NodeId<Node>, class-string, string}>
     */
    public static function providerIdentifiersDrawnFromSeedSeven(): iterable
    {
        yield 'nodeId' => [static fn (NodeIdGenerator $ids): NodeId => $ids->nodeId(), BuiltinNodeId::class, 'AdRerumHarum\EnimDolor\ModiMinus'];

        yield 'classNodeId' => [static fn (NodeIdGenerator $ids): NodeId => $ids->classNodeId(), ClassNodeId::class, 'SaepeAdRerum\SedEnimDolor\ModiMinusClass'];

        yield 'interfaceNodeId' => [static fn (NodeIdGenerator $ids): NodeId => $ids->interfaceNodeId(), InterfaceNodeId::class, 'SaepeAdRerum\SedEnimDolor\ModiMinusInterface'];

        yield 'traitNodeId' => [static fn (NodeIdGenerator $ids): NodeId => $ids->traitNodeId(), TraitNodeId::class, 'SaepeAdRerum\SedEnimDolor\ModiMinusTrait'];

        yield 'enumNodeId' => [static fn (NodeIdGenerator $ids): NodeId => $ids->enumNodeId(), EnumNodeId::class, 'SaepeAdRerum\SedEnimDolor\ModiMinusEnum'];

        yield 'methodNodeId' => [static fn (NodeIdGenerator $ids): NodeId => $ids->methodNodeId(), MethodNodeId::class, 'SaepeAdRerum\SedEnimDolor\ModiMinusClass::sedUtFugitMethod'];

        yield 'propertyNodeId' => [static fn (NodeIdGenerator $ids): NodeId => $ids->propertyNodeId(), PropertyNodeId::class, 'SaepeAdRerum\SedEnimDolor\ModiMinusClass::sedUtFugitProperty'];

        yield 'functionNodeId' => [static fn (NodeIdGenerator $ids): NodeId => $ids->functionNodeId(), FunctionNodeId::class, 'SaepeAdRerum\SedEnimDolor\modiMinusFunction'];

        yield 'constantNodeId' => [static fn (NodeIdGenerator $ids): NodeId => $ids->constantNodeId(), ConstantNodeId::class, 'SaepeAdRerum\SedEnimDolor\ModiMinusClass::SED_UT_FUGIT_CONST'];

        yield 'enumCaseNodeId' => [static fn (NodeIdGenerator $ids): NodeId => $ids->enumCaseNodeId(), EnumCaseNodeId::class, 'SaepeAdRerum\SedEnimDolor\ModiMinusEnum::SED_UT_FUGIT_CASE'];

        yield 'builtinNodeId' => [static fn (NodeIdGenerator $ids): NodeId => $ids->builtinNodeId(), BuiltinNodeId::class, 'SaepeAdRerum\SedEnimDolor\ModiMinus'];

        yield 'unknownNodeId' => [static fn (NodeIdGenerator $ids): NodeId => $ids->unknownNodeId(), UnknownNodeId::class, 'SaepeSaepe'];

        yield 'typeNodeId' => [static fn (NodeIdGenerator $ids): NodeId => $ids->typeNodeId(), EnumNodeId::class, 'AdRerumHarum\EnimDolor\ModiMinusEnum'];
    }

    public function testNodeKindDrawsTheKindsOfItsSeedInOrder(): void
    {
        $random = new RandomSource(7);
        $ids = new NodeIdGenerator(new NameGenerator($random), $random);

        self::assertSame(
            ['builtin', 'constant', 'closure', 'property', 'function', 'enum', 'builtin', 'property', 'interface', 'interface', 'method', 'class'],
            array_map(static fn (): string => $ids->nodeKind()->value, range(1, 12)),
        );
    }

    public function testNodeKindReachesEveryKindTheGraphCanHold(): void
    {
        $random = new RandomSource(7);
        $ids = new NodeIdGenerator(new NameGenerator($random), $random);
        $drawn = array_unique(array_map(static fn (): string => $ids->nodeKind()->value, range(1, 300)));

        self::assertSame([], array_values(array_diff(array_map(static fn (NodeKind $kind): string => $kind->value, NodeKind::cases()), $drawn)));
    }

    public function testEdgeKindDrawsTheKindsOfItsSeedInOrder(): void
    {
        $random = new RandomSource(7);
        $ids = new NodeIdGenerator(new NameGenerator($random), $random);

        self::assertSame(
            ['instanceof', 'callable-reference', 'declaration-type-parameter', 'static-property-access', 'declaration-enum-case', 'method-call', 'declaration-trait-use', 'static-property-access', 'instantiation', 'declaration-type-return', 'declaration-type-property', 'function-call'],
            array_map(static fn (): string => $ids->edgeKind()->value, range(1, 12)),
        );
    }

    public function testEdgeKindReachesEveryKindSourceCodeWrites(): void
    {
        $random = new RandomSource(7);
        $ids = new NodeIdGenerator(new NameGenerator($random), $random);
        $drawn = array_unique(array_map(static fn (): string => $ids->edgeKind()->value, range(1, 300)));
        $authored = array_map(static fn (EdgeKind $kind): string => $kind->value, array_filter(EdgeKind::cases(), static fn (EdgeKind $kind): bool => $kind->direction() === Direction::Uses));

        self::assertSame([], array_values(array_diff($authored, $drawn)));
        self::assertSame([], array_values(array_diff($drawn, $authored)));
    }

    public function testTypeNodeIdDrawsTheTypesOfItsSeedInOrder(): void
    {
        $random = new RandomSource(7);
        $ids = new NodeIdGenerator(new NameGenerator($random), $random);

        self::assertSame(
            [EnumNodeId::class, BuiltinNodeId::class, InterfaceNodeId::class, BuiltinNodeId::class, InterfaceNodeId::class, ClassNodeId::class, BuiltinNodeId::class, BuiltinNodeId::class, InterfaceNodeId::class, BuiltinNodeId::class, InterfaceNodeId::class, EnumNodeId::class],
            array_map(static fn (): string => $ids->typeNodeId()::class, range(1, 12)),
        );
    }

    public function testFileMetaPointsWhereTheDrawsOfItsSeedSay(): void
    {
        $random = new RandomSource(7);
        $meta = (new NodeIdGenerator(new NameGenerator($random), $random))->fileMeta();

        self::assertSame('/saepe/saepe/ad/HarumSed.php', $meta->path);
        self::assertSame(85, $meta->line);
        self::assertSame(1, $meta->column);
    }

    public function testFileMetaDrawsLinesFromTheFirstToTheHundredth(): void
    {
        $random = new RandomSource(7);
        $ids = new NodeIdGenerator(new NameGenerator($random), $random);
        $lines = array_map(static fn (): int => $ids->fileMeta()->line, range(1, 400));

        self::assertSame(1, min($lines));
        self::assertSame(100, max($lines));
    }

    public function testClosureNodeIdCarriesTheContainingFunctionAndPosition(): void
    {
        $random = new RandomSource(1);
        $generator = new NodeIdGenerator(new NameGenerator($random), $random);
        $id = $generator->closureNodeId();
        self::assertGreaterThanOrEqual(1, $id->line);
        self::assertLessThanOrEqual(100, $id->line);
        self::assertSame(1, $id->column);
        self::assertStringContainsString('{closure@', $id->toString());
    }
}
