<?php

declare(strict_types=1);

namespace Tests\Unit\Analyzer\DebugAnalyzer\Generator;

use App\Analyzer\DebugAnalyzer\Generator\ClassLikeGraphGenerator;
use App\Analyzer\DebugAnalyzer\Generator\GraphGenerator;
use App\Analyzer\DebugAnalyzer\Generator\LeafGraphGenerator;
use App\Analyzer\DebugAnalyzer\Generator\MemberGraphGenerator;
use App\Analyzer\DebugAnalyzer\Generator\NameGenerator;
use App\Analyzer\DebugAnalyzer\Generator\NodeGenerator;
use App\Analyzer\DebugAnalyzer\Generator\NodeIdGenerator;
use App\Analyzer\DebugAnalyzer\Generator\RandomSource;
use App\Analyzer\Graph\Edge;
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

/**
 * @internal
 */
#[CoversClass(GraphGenerator::class)]
#[UsesClass(ClassLikeGraphGenerator::class)]
#[UsesClass(\App\Analyzer\DebugAnalyzer\Generator\GeneratedGraph::class)]
#[UsesClass(LeafGraphGenerator::class)]
#[UsesClass(MemberGraphGenerator::class)]
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
#[UsesClass(Edge\Usage\StaticPropertyAccessEdge::class)]
#[UsesClass(Edge\Inverse\UsedByEdge::class)]
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
#[UsesClass(Edge\Usage\StaticCallEdge::class)]
#[UsesClass(RandomSource::class)]
#[Small]
final class GraphGeneratorTest extends TestCase
{
    #[DataProvider('providerGeneratorsDrawingFromSeed42')]
    public function testGraphIsRootedAtSomethingPhpDeclaresAtTheTopOfAFile(GraphGenerator $graphs): void
    {
        $graph = $graphs->graph(3);

        self::assertContains($graph->nodes()[0]->kind(), [NodeKind::Klass, NodeKind::Interface, NodeKind::Trait, NodeKind::Function]);
    }

    #[DataProvider('providerGeneratorsDrawingFromSeed42')]
    public function testGraphProducesMoreThanItsRootAtAUsefulDepth(GraphGenerator $graphs): void
    {
        self::assertGreaterThan(1, count($graphs->graph(3)->nodes()));
    }

    #[DataProvider('providerGeneratorsDrawingFromSeed42')]
    public function testGraphProducesRelationsReadableInBothDirections(GraphGenerator $graphs): void
    {
        $graph = $graphs->graph(3);
        $edges = array_merge([], ...array_map(static fn (\App\Analyzer\Graph\Node $node): array => $graph->edges($node->id()), $graph->nodes()));

        self::assertSame([], array_values(array_filter($edges, static fn (Edge $edge): bool => $graph->edge($edge->to(), $edge->from()) === null)));
    }

    #[DataProvider('providerGeneratorsDrawingFromSeed42')]
    public function testGraphProducesNoDanglingRelation(GraphGenerator $graphs): void
    {
        $graph = $graphs->graph(3);
        $edges = array_merge([], ...array_map(static fn (\App\Analyzer\Graph\Node $node): array => $graph->edges($node->id()), $graph->nodes()));

        self::assertSame([], array_values(array_filter($edges, static fn (Edge $edge): bool => $graph->node($edge->from()) === null || $graph->node($edge->to()) === null)));
    }

    #[DataProvider('providerGeneratorsDrawingFromSeed42')]
    public function testGraphNamesEachSymbolOnlyOnce(GraphGenerator $graphs): void
    {
        $names = array_map(static fn (\App\Analyzer\Graph\Node $node): string => $node->id()->toString(), $graphs->graph(3)->nodes());

        self::assertSame($names, array_values(array_unique($names)));
    }

    public function testTheSameSeedProducesTheSameGraph(): void
    {
        $spell = static fn (Edge $edge): string => $edge->from()->toString().' -['.$edge->kind()->value.']-> '.$edge->to()->toString();
        $first = new RandomSource(7);
        $firstIds = new NodeIdGenerator(new NameGenerator($first), $first);
        $firstNodes = new NodeGenerator($firstIds, $first);
        $again = new RandomSource(7);
        $againIds = new NodeIdGenerator(new NameGenerator($again), $again);
        $againNodes = new NodeGenerator($againIds, $again);

        self::assertSame(
            array_map($spell, (new GraphGenerator(new ClassLikeGraphGenerator($firstNodes, $firstIds, $first), new MemberGraphGenerator($firstNodes, $firstIds, $first), new LeafGraphGenerator($firstNodes), $firstIds, $first))->graph(3)->authoredEdges()),
            array_map($spell, (new GraphGenerator(new ClassLikeGraphGenerator($againNodes, $againIds, $again), new MemberGraphGenerator($againNodes, $againIds, $again), new LeafGraphGenerator($againNodes), $againIds, $again))->graph(3)->authoredEdges()),
        );
    }

    #[DataProvider('providerTypePositionIdentifiers')]
    public function testTypeGraphIsRootedAtWhateverTypeItWasGiven(
        BuiltinNodeId|ClassNodeId|EnumNodeId|InterfaceNodeId $symbol,
        NodeKind $kind,
    ): void {
        $random = new RandomSource(42);
        $ids = new NodeIdGenerator(new NameGenerator($random), $random);
        $nodes = new NodeGenerator($ids, $random);
        $generator = new GraphGenerator(new ClassLikeGraphGenerator($nodes, $ids, $random), new MemberGraphGenerator($nodes, $ids, $random), new LeafGraphGenerator($nodes), $ids, $random);

        self::assertSame($kind, $generator->typeGraph($symbol, 1)->root->kind());
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

    #[DataProvider('providerGeneratorsDrawingFromSeed42')]
    public function testTypeGraphDrawsATypeWhenGivenNone(GraphGenerator $graphs): void
    {
        $result = $graphs->typeGraph(null, 1);

        self::assertContains($result->root->kind(), [NodeKind::Builtin, NodeKind::Klass, NodeKind::Interface, NodeKind::Enum]);
    }

    #[DataProvider('providerGeneratorsDrawingFromSeed42')]
    public function testClassGraphIsRoutedToTheGeneratorThatOwnsClasses(GraphGenerator $graphs): void
    {
        self::assertSame(NodeKind::Klass, $graphs->classGraph(null, 1)->root->kind());
    }

    #[DataProvider('providerGeneratorsDrawingFromSeed42')]
    public function testMethodGraphIsRoutedToTheGeneratorThatOwnsMembers(GraphGenerator $graphs): void
    {
        self::assertSame(NodeKind::Method, $graphs->methodGraph(null, 1)->root->kind());
    }

    #[DataProvider('providerGeneratorsDrawingFromSeed42')]
    public function testConstantGraphIsRoutedToTheGeneratorThatOwnsLeaves(GraphGenerator $graphs): void
    {
        self::assertSame(NodeKind::Constant, $graphs->constantGraph()->root->kind());
    }

    #[DataProvider('providerGeneratorsDrawingFromSeed42')]
    public function testInterfaceGraphIsRoutedToTheGeneratorThatOwnsClassLikes(GraphGenerator $graphs): void
    {
        self::assertSame(NodeKind::Interface, $graphs->interfaceGraph(null, 1)->root->kind());
    }

    #[DataProvider('providerGeneratorsDrawingFromSeed42')]
    public function testTraitGraphIsRoutedToTheGeneratorThatOwnsClassLikes(GraphGenerator $graphs): void
    {
        self::assertSame(NodeKind::Trait, $graphs->traitGraph(null, 1)->root->kind());
    }

    #[DataProvider('providerGeneratorsDrawingFromSeed42')]
    public function testEnumGraphIsRoutedToTheGeneratorThatOwnsClassLikes(GraphGenerator $graphs): void
    {
        self::assertSame(NodeKind::Enum, $graphs->enumGraph(null, 1)->root->kind());
    }

    #[DataProvider('providerGeneratorsDrawingFromSeed42')]
    public function testFunctionGraphIsRoutedToTheGeneratorThatOwnsMembers(GraphGenerator $graphs): void
    {
        self::assertSame(NodeKind::Function, $graphs->functionGraph(null, 1)->root->kind());
    }

    #[DataProvider('providerGeneratorsDrawingFromSeed42')]
    public function testPropertyGraphIsRoutedToTheGeneratorThatOwnsMembers(GraphGenerator $graphs): void
    {
        self::assertSame(NodeKind::Property, $graphs->propertyGraph(null, 1)->root->kind());
    }

    #[DataProvider('providerGeneratorsDrawingFromSeed42')]
    public function testEnumCaseGraphIsRoutedToTheGeneratorThatOwnsLeaves(GraphGenerator $graphs): void
    {
        self::assertSame(NodeKind::EnumCase, $graphs->enumCaseGraph()->root->kind());
    }

    #[DataProvider('providerGeneratorsDrawingFromSeed42')]
    public function testBuiltinGraphIsRoutedToTheGeneratorThatOwnsLeaves(GraphGenerator $graphs): void
    {
        self::assertSame(NodeKind::Builtin, $graphs->builtinGraph()->root->kind());
    }

    /**
     * @return iterable<string, array{GraphGenerator}>
     */
    public static function providerGeneratorsDrawingFromSeed42(): iterable
    {
        $random = new RandomSource(42);
        $ids = new NodeIdGenerator(new NameGenerator($random), $random);
        $nodes = new NodeGenerator($ids, $random);
        $classLikes = new ClassLikeGraphGenerator($nodes, $ids, $random);
        $members = new MemberGraphGenerator($nodes, $ids, $random);
        $graphs = new GraphGenerator($classLikes, $members, new LeafGraphGenerator($nodes), $ids, $random);

        yield 'drawing from seed 42' => [$graphs];
    }

    #[DataProvider('providerTopLevelSymbolsByFirstDraw')]
    public function testGraphIsRootedAtTheTopLevelSymbolTheFirstDrawSelects(int $seed, NodeKind $kind, string $root): void
    {
        $random = new RandomSource($seed);
        $ids = new NodeIdGenerator(new NameGenerator($random), $random);
        $nodes = new NodeGenerator($ids, $random);
        $generator = new GraphGenerator(new ClassLikeGraphGenerator($nodes, $ids, $random), new MemberGraphGenerator($nodes, $ids, $random), new LeafGraphGenerator($nodes), $ids, $random);
        $graph = $generator->graph(2);

        self::assertSame($kind, $graph->nodes()[0]->kind());
        self::assertSame($root, $graph->nodes()[0]->id()->toString());
    }

    #[DataProvider('providerTopLevelSymbolsByFirstDraw')]
    public function testGraphHoldsNothingButTheRootAtDepthOne(int $seed, NodeKind $kind, string $root): void
    {
        $random = new RandomSource($seed);
        $ids = new NodeIdGenerator(new NameGenerator($random), $random);
        $nodes = new NodeGenerator($ids, $random);
        $generator = new GraphGenerator(new ClassLikeGraphGenerator($nodes, $ids, $random), new MemberGraphGenerator($nodes, $ids, $random), new LeafGraphGenerator($nodes), $ids, $random);
        $graph = $generator->graph(1);

        self::assertSame([$root], array_map(static fn (\App\Analyzer\Graph\Node $node): string => $node->id()->toString(), $graph->nodes()));
        self::assertSame($kind, $graph->nodes()[0]->kind());
    }

    /**
     * @return iterable<string, array{int, NodeKind, string}>
     */
    public static function providerTopLevelSymbolsByFirstDraw(): iterable
    {
        yield 'seed 1 draws a class' => [1, NodeKind::Klass, 'NihilVitaeVero\IpsumBeatae\UllamOdioClass'];

        yield 'seed 2 draws a function' => [2, NodeKind::Function, 'DictaEsse\saepeNemoFunction'];

        yield 'seed 3 draws a trait' => [3, NodeKind::Trait, 'TemporaOmnisTempora\SimiliqueOfficiaVeroTrait'];

        yield 'seed 4 draws an interface' => [4, NodeKind::Interface, 'AmetAut\NemoNatus\UllamSedSed\AdVelitInterface'];
    }

    public function testGraphReachesOneLevelOfRelationsAtDepthTwo(): void
    {
        $random = new RandomSource(3);
        $ids = new NodeIdGenerator(new NameGenerator($random), $random);
        $nodes = new NodeGenerator($ids, $random);
        $generator = new GraphGenerator(new ClassLikeGraphGenerator($nodes, $ids, $random), new MemberGraphGenerator($nodes, $ids, $random), new LeafGraphGenerator($nodes), $ids, $random);

        $written = array_map(static fn (Edge $edge): string => $edge->from()->toString().' -['.$edge->kind()->value.']-> '.$edge->to()->toString(), $generator->graph(2)->authoredEdges());
        sort($written);

        self::assertSame([
            'TemporaOmnisTempora\SimiliqueOfficiaVeroTrait -[declaration-method]-> ErrorIllumNemo\ModiMagniClass::solutaLaboreMethod',
            'TemporaOmnisTempora\SimiliqueOfficiaVeroTrait -[declaration-property]-> BeataeVeroAut\AutTempora\NemoCommodiClass::ipsumQuasTemporaProperty',
            'TemporaOmnisTempora\SimiliqueOfficiaVeroTrait -[declaration-property]-> NihilIurePorro\AtqueDictaSuntClass::voluptasRerumProperty',
        ], $written);
    }
}
