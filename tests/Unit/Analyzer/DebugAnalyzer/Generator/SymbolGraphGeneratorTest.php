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
use App\Analyzer\Graph\Node;
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
#[CoversClass(GraphGenerator::class)]
#[CoversClass(ClassLikeGraphGenerator::class)]
#[CoversClass(GeneratedGraph::class)]
#[CoversClass(LeafGraphGenerator::class)]
#[CoversClass(MemberGraphGenerator::class)]
#[CoversClass(NameGenerator::class)]
#[CoversClass(NodeGenerator::class)]
#[CoversClass(NodeIdGenerator::class)]
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
final class SymbolGraphGeneratorTest extends TestCase
{
    public function testClassGraphIsRootedAtTheClassItIsGiven(): void
    {
        $random = new RandomSource(42);
        $ids = new NodeIdGenerator(new NameGenerator($random), $random);
        $nodes = new NodeGenerator($ids, $random);
        $generator = new GraphGenerator(new ClassLikeGraphGenerator($nodes, $ids, $random), new MemberGraphGenerator($nodes, $ids, $random), new LeafGraphGenerator($nodes), $ids, $random);
        $result = $generator->classGraph(ClassNodeId::of('App\Domain\Invoice'), 1);

        self::assertSame('App\Domain\Invoice', $result->root->id()->toString());
        self::assertSame(NodeKind::Klass, $result->root->kind());
    }

    public function testInterfaceGraphIsRootedAtTheInterfaceItIsGiven(): void
    {
        $random = new RandomSource(42);
        $ids = new NodeIdGenerator(new NameGenerator($random), $random);
        $nodes = new NodeGenerator($ids, $random);
        $generator = new GraphGenerator(new ClassLikeGraphGenerator($nodes, $ids, $random), new MemberGraphGenerator($nodes, $ids, $random), new LeafGraphGenerator($nodes), $ids, $random);
        $result = $generator->interfaceGraph(InterfaceNodeId::of('App\Domain\Payable'), 1);

        self::assertSame('App\Domain\Payable', $result->root->id()->toString());
        self::assertSame(NodeKind::Interface, $result->root->kind());
    }

    public function testTraitGraphIsRootedAtTheTraitItIsGiven(): void
    {
        $random = new RandomSource(42);
        $ids = new NodeIdGenerator(new NameGenerator($random), $random);
        $nodes = new NodeGenerator($ids, $random);
        $generator = new GraphGenerator(new ClassLikeGraphGenerator($nodes, $ids, $random), new MemberGraphGenerator($nodes, $ids, $random), new LeafGraphGenerator($nodes), $ids, $random);
        $result = $generator->traitGraph(TraitNodeId::of('App\Domain\Timestamped'), 1);

        self::assertSame('App\Domain\Timestamped', $result->root->id()->toString());
        self::assertSame(NodeKind::Trait, $result->root->kind());
    }

    public function testEnumGraphIsRootedAtTheEnumItIsGiven(): void
    {
        $random = new RandomSource(42);
        $ids = new NodeIdGenerator(new NameGenerator($random), $random);
        $nodes = new NodeGenerator($ids, $random);
        $generator = new GraphGenerator(new ClassLikeGraphGenerator($nodes, $ids, $random), new MemberGraphGenerator($nodes, $ids, $random), new LeafGraphGenerator($nodes), $ids, $random);
        $result = $generator->enumGraph(EnumNodeId::of('App\Domain\InvoiceState'), 1);

        self::assertSame('App\Domain\InvoiceState', $result->root->id()->toString());
        self::assertSame(NodeKind::Enum, $result->root->kind());
    }

    public function testMethodGraphIsRootedAtTheMethodItIsGiven(): void
    {
        $random = new RandomSource(42);
        $ids = new NodeIdGenerator(new NameGenerator($random), $random);
        $nodes = new NodeGenerator($ids, $random);
        $generator = new GraphGenerator(new ClassLikeGraphGenerator($nodes, $ids, $random), new MemberGraphGenerator($nodes, $ids, $random), new LeafGraphGenerator($nodes), $ids, $random);
        $result = $generator->methodGraph(MethodNodeId::of('App\Domain\Invoice', 'total'), 1);

        self::assertSame('App\Domain\Invoice::total', $result->root->id()->toString());
        self::assertSame(NodeKind::Method, $result->root->kind());
    }

    public function testFunctionGraphIsRootedAtTheFunctionItIsGiven(): void
    {
        $random = new RandomSource(42);
        $ids = new NodeIdGenerator(new NameGenerator($random), $random);
        $nodes = new NodeGenerator($ids, $random);
        $generator = new GraphGenerator(new ClassLikeGraphGenerator($nodes, $ids, $random), new MemberGraphGenerator($nodes, $ids, $random), new LeafGraphGenerator($nodes), $ids, $random);
        $result = $generator->functionGraph(FunctionNodeId::of('App\Domain\formatMoney'), 1);

        self::assertSame('App\Domain\formatMoney', $result->root->id()->toString());
        self::assertSame(NodeKind::Function, $result->root->kind());
    }

    public function testPropertyGraphIsRootedAtThePropertyItIsGiven(): void
    {
        $random = new RandomSource(42);
        $ids = new NodeIdGenerator(new NameGenerator($random), $random);
        $nodes = new NodeGenerator($ids, $random);
        $generator = new GraphGenerator(new ClassLikeGraphGenerator($nodes, $ids, $random), new MemberGraphGenerator($nodes, $ids, $random), new LeafGraphGenerator($nodes), $ids, $random);
        $result = $generator->propertyGraph(PropertyNodeId::of('App\Domain\Invoice', 'lines'), 1);

        self::assertSame('App\Domain\Invoice::lines', $result->root->id()->toString());
        self::assertSame(NodeKind::Property, $result->root->kind());
    }

    public function testConstantGraphIsRootedAtTheConstantItIsGiven(): void
    {
        $random = new RandomSource(42);
        $ids = new NodeIdGenerator(new NameGenerator($random), $random);
        $nodes = new NodeGenerator($ids, $random);
        $generator = new GraphGenerator(new ClassLikeGraphGenerator($nodes, $ids, $random), new MemberGraphGenerator($nodes, $ids, $random), new LeafGraphGenerator($nodes), $ids, $random);
        $result = $generator->constantGraph(ConstantNodeId::of('App\Domain\Invoice', 'MAX_ITEMS'));

        self::assertSame('App\Domain\Invoice::MAX_ITEMS', $result->root->id()->toString());
        self::assertSame(NodeKind::Constant, $result->root->kind());
    }

    public function testEnumCaseGraphIsRootedAtTheEnumCaseItIsGiven(): void
    {
        $random = new RandomSource(42);
        $ids = new NodeIdGenerator(new NameGenerator($random), $random);
        $nodes = new NodeGenerator($ids, $random);
        $generator = new GraphGenerator(new ClassLikeGraphGenerator($nodes, $ids, $random), new MemberGraphGenerator($nodes, $ids, $random), new LeafGraphGenerator($nodes), $ids, $random);
        $result = $generator->enumCaseGraph(EnumCaseNodeId::of('App\Domain\InvoiceState', 'Paid'));

        self::assertSame('App\Domain\InvoiceState::Paid', $result->root->id()->toString());
        self::assertSame(NodeKind::EnumCase, $result->root->kind());
    }

    public function testBuiltinGraphIsRootedAtTheBuiltinTypeItIsGiven(): void
    {
        $random = new RandomSource(42);
        $ids = new NodeIdGenerator(new NameGenerator($random), $random);
        $nodes = new NodeGenerator($ids, $random);
        $generator = new GraphGenerator(new ClassLikeGraphGenerator($nodes, $ids, $random), new MemberGraphGenerator($nodes, $ids, $random), new LeafGraphGenerator($nodes), $ids, $random);
        $result = $generator->builtinGraph(BuiltinNodeId::of('int'));

        self::assertSame('int', $result->root->id()->toString());
        self::assertSame(NodeKind::Builtin, $result->root->kind());
    }

    public function testTypeGraphIsRootedAtTheTypeItIsGiven(): void
    {
        $random = new RandomSource(42);
        $ids = new NodeIdGenerator(new NameGenerator($random), $random);
        $nodes = new NodeGenerator($ids, $random);
        $generator = new GraphGenerator(new ClassLikeGraphGenerator($nodes, $ids, $random), new MemberGraphGenerator($nodes, $ids, $random), new LeafGraphGenerator($nodes), $ids, $random);
        $result = $generator->typeGraph(EnumNodeId::of('App\Domain\InvoiceState'), 1);

        self::assertSame('App\Domain\InvoiceState', $result->root->id()->toString());
        self::assertSame(NodeKind::Enum, $result->root->kind());
    }

    /**
     * @param Closure(GraphGenerator, int): GeneratedGraph<Node> $operation
     */
    #[DataProvider('providerEveryOperation')]
    public function testEveryOperationIsRootedAtTheKindOfSymbolItNames(Closure $operation, NodeKind $kind): void
    {
        $random = new RandomSource(42);
        $ids = new NodeIdGenerator(new NameGenerator($random), $random);
        $nodes = new NodeGenerator($ids, $random);
        $generator = new GraphGenerator(new ClassLikeGraphGenerator($nodes, $ids, $random), new MemberGraphGenerator($nodes, $ids, $random), new LeafGraphGenerator($nodes), $ids, $random);

        self::assertSame($kind, $operation($generator, 2)->root->kind());
    }

    /**
     * @param Closure(GraphGenerator, int): GeneratedGraph<Node> $operation
     */
    #[DataProvider('providerEveryOperation')]
    public function testEveryOperationRecordsItsRootInTheGraphItReturns(Closure $operation, NodeKind $kind): void
    {
        $random = new RandomSource(42);
        $ids = new NodeIdGenerator(new NameGenerator($random), $random);
        $nodes = new NodeGenerator($ids, $random);
        $result = $operation(new GraphGenerator(new ClassLikeGraphGenerator($nodes, $ids, $random), new MemberGraphGenerator($nodes, $ids, $random), new LeafGraphGenerator($nodes), $ids, $random), 2);

        self::assertSame($result->root, $result->graph->nodeNamed($result->root->id()->toString()));
        self::assertSame($kind, $result->root->kind());
    }

    /**
     * @param Closure(GraphGenerator, int): GeneratedGraph<Node> $operation
     */
    #[DataProvider('providerEveryOperation')]
    public function testEveryOperationProducesRelationsReadableFromBothEndsAndHeldByTheGraph(Closure $operation, NodeKind $kind): void
    {
        $random = new RandomSource(42);
        $ids = new NodeIdGenerator(new NameGenerator($random), $random);
        $nodes = new NodeGenerator($ids, $random);
        $graph = $operation(new GraphGenerator(new ClassLikeGraphGenerator($nodes, $ids, $random), new MemberGraphGenerator($nodes, $ids, $random), new LeafGraphGenerator($nodes), $ids, $random), 2)->graph;
        $edges = array_merge([], ...array_map(static fn (Node $node): array => $graph->edges($node->id()), $graph->nodes()));

        self::assertSame([], array_values(array_filter($edges, static fn (Edge $edge): bool => $graph->edge($edge->to(), $edge->from()) === null)));
        self::assertSame([], array_values(array_filter($edges, static fn (Edge $edge): bool => $graph->node($edge->from()) === null || $graph->node($edge->to()) === null)));
        self::assertContains($kind, NodeKind::cases());
    }

    /**
     * @param Closure(GraphGenerator, int): GeneratedGraph<Node> $operation
     */
    #[DataProvider('providerEveryOperation')]
    public function testEveryOperationStopsAtDepthZero(Closure $operation, NodeKind $kind): void
    {
        $random = new RandomSource(42);
        $ids = new NodeIdGenerator(new NameGenerator($random), $random);
        $nodes = new NodeGenerator($ids, $random);
        $result = $operation(new GraphGenerator(new ClassLikeGraphGenerator($nodes, $ids, $random), new MemberGraphGenerator($nodes, $ids, $random), new LeafGraphGenerator($nodes), $ids, $random), 0);

        self::assertSame([$result->root], $result->graph->nodes());
        self::assertSame($kind, $result->root->kind());
    }

    /**
     * @param Closure(GraphGenerator, int): GeneratedGraph<Node> $operation
     */
    #[DataProvider('providerEveryOperation')]
    public function testEveryOperationDrawsTheSameGraphAgainForTheSameSeed(Closure $operation, NodeKind $kind): void
    {
        $spell = static fn (Edge $edge): string => $edge->from()->toString().' -['.$edge->kind()->value.']-> '.$edge->to()->toString();
        $firstRandom = new RandomSource(7);
        $firstIds = new NodeIdGenerator(new NameGenerator($firstRandom), $firstRandom);
        $firstNodes = new NodeGenerator($firstIds, $firstRandom);
        $first = $operation(new GraphGenerator(new ClassLikeGraphGenerator($firstNodes, $firstIds, $firstRandom), new MemberGraphGenerator($firstNodes, $firstIds, $firstRandom), new LeafGraphGenerator($firstNodes), $firstIds, $firstRandom), 2);
        $againRandom = new RandomSource(7);
        $againIds = new NodeIdGenerator(new NameGenerator($againRandom), $againRandom);
        $againNodes = new NodeGenerator($againIds, $againRandom);
        $again = $operation(new GraphGenerator(new ClassLikeGraphGenerator($againNodes, $againIds, $againRandom), new MemberGraphGenerator($againNodes, $againIds, $againRandom), new LeafGraphGenerator($againNodes), $againIds, $againRandom), 2);

        self::assertSame($first->root->id()->toString(), $again->root->id()->toString());
        self::assertSame(array_map($spell, $first->graph->forwardEdges()), array_map($spell, $again->graph->forwardEdges()));
        self::assertSame($kind, $again->root->kind());
    }

    /**
     * @return iterable<string, array{Closure(GraphGenerator, int): GeneratedGraph<Node>, NodeKind}>
     */
    public static function providerEveryOperation(): iterable
    {
        yield 'a class' => [static fn (GraphGenerator $generator, int $depth): GeneratedGraph => $generator->classGraph(null, $depth), NodeKind::Klass];

        yield 'an interface' => [static fn (GraphGenerator $generator, int $depth): GeneratedGraph => $generator->interfaceGraph(null, $depth), NodeKind::Interface];

        yield 'a trait' => [static fn (GraphGenerator $generator, int $depth): GeneratedGraph => $generator->traitGraph(null, $depth), NodeKind::Trait];

        yield 'an enum' => [static fn (GraphGenerator $generator, int $depth): GeneratedGraph => $generator->enumGraph(null, $depth), NodeKind::Enum];

        yield 'a method' => [static fn (GraphGenerator $generator, int $depth): GeneratedGraph => $generator->methodGraph(null, $depth), NodeKind::Method];

        yield 'a function' => [static fn (GraphGenerator $generator, int $depth): GeneratedGraph => $generator->functionGraph(null, $depth), NodeKind::Function];

        yield 'a property' => [static fn (GraphGenerator $generator, int $depth): GeneratedGraph => $generator->propertyGraph(null, $depth), NodeKind::Property];

        yield 'a constant' => [static fn (GraphGenerator $generator): GeneratedGraph => $generator->constantGraph(), NodeKind::Constant];

        yield 'an enum case' => [static fn (GraphGenerator $generator): GeneratedGraph => $generator->enumCaseGraph(), NodeKind::EnumCase];

        yield 'a builtin type' => [static fn (GraphGenerator $generator): GeneratedGraph => $generator->builtinGraph(), NodeKind::Builtin];
    }

    /**
     * @param Closure(GraphGenerator): GeneratedGraph<Node> $operation
     * @param list<string>                                  $relations
     */
    #[DataProvider('providerRecordedGraphs')]
    public function testEveryOperationDrawsTheGraphRecordedForItsSeed(Closure $operation, int $seed, string $root, array $relations): void
    {
        $random = new RandomSource($seed);
        $ids = new NodeIdGenerator(new NameGenerator($random), $random);
        $nodes = new NodeGenerator($ids, $random);
        $generator = new GraphGenerator(new ClassLikeGraphGenerator($nodes, $ids, $random), new MemberGraphGenerator($nodes, $ids, $random), new LeafGraphGenerator($nodes), $ids, $random);
        $result = $operation($generator);
        $written = array_map(static fn (Edge $edge): string => $edge->from()->toString().' -['.$edge->kind()->value.']-> '.$edge->to()->toString(), $result->graph->forwardEdges());
        sort($written);

        self::assertSame($root, $result->root->id()->toString());
        self::assertSame($relations, $written);
    }

    /**
     * @return iterable<string, array{Closure(GraphGenerator): GeneratedGraph<Node>, int, string, list<string>}>
     */
    public static function providerRecordedGraphs(): iterable
    {
        yield 'classGraph at seed 7' => [static fn (GraphGenerator $generator): GeneratedGraph => $generator->classGraph(null, 1), 7, 'SaepeAdRerum\SedEnimDolor\ModiMinusClass', [
            'SaepeAdRerum\SedEnimDolor\ModiMinusClass -[declaration-constant]-> LaboreIllumSed\ModiSuntClass::CULPA_HARUM_CONST',
            'SaepeAdRerum\SedEnimDolor\ModiMinusClass -[declaration-constant]-> VoluptasMagniRerum\RerumOmnisClass::MAGNI_FUGIT_AUT_CONST',
            'SaepeAdRerum\SedEnimDolor\ModiMinusClass -[declaration-implements]-> EsseFacereRerum\HarumIdInterface',
            'SaepeAdRerum\SedEnimDolor\ModiMinusClass -[declaration-implements]-> SequiNatus\FugaEsse\EiusIpsumQuod\RationeRationeHarumInterface',
            'SaepeAdRerum\SedEnimDolor\ModiMinusClass -[declaration-implements]-> SolutaHarum\SedFacere\EsseQuasIureInterface',
            'SaepeAdRerum\SedEnimDolor\ModiMinusClass -[declaration-implements]-> SolutaIdIpsum\NihilOmnis\QuodEiusInterface',
            'SaepeAdRerum\SedEnimDolor\ModiMinusClass -[declaration-implements]-> TemporaNobisSoluta\FugaSaepeSaepe\SedNihilAut\SaepeLaboreVelitInterface',
            'SaepeAdRerum\SedEnimDolor\ModiMinusClass -[declaration-method]-> AliasAdLabore\OdioSintMagniClass::porroVeroMethod',
            'SaepeAdRerum\SedEnimDolor\ModiMinusClass -[declaration-method]-> AliasIllum\ErrorDicta\SolutaPorroSaepe\AdTemporaClass::velitSolutaRationeMethod',
            'SaepeAdRerum\SedEnimDolor\ModiMinusClass -[declaration-method]-> AnimiRatione\UllamNobisSuntClass::iureBeataeBeataeMethod',
            'SaepeAdRerum\SedEnimDolor\ModiMinusClass -[declaration-method]-> QuodSoluta\EsseVitaeTempora\IureQuod\VelitIdClass::modiVeroCulpaMethod',
            'SaepeAdRerum\SedEnimDolor\ModiMinusClass -[declaration-property]-> SedAutNobis\RationeVitaeNobis\AdModiDolorClass::dolorAtqueNihilProperty',
            'SaepeAdRerum\SedEnimDolor\ModiMinusClass -[declaration-trait-use]-> LaboreAtque\CommodiFacereId\IpsumIpsumFacereTrait',
            'SaepeAdRerum\SedEnimDolor\ModiMinusClass -[declaration-trait-use]-> MinusQuodTempora\HicOfficiaMinusTrait',
            'SaepeAdRerum\SedEnimDolor\ModiMinusClass -[declaration-trait-use]-> SolutaUllamAlias\SintOmnisTrait',
        ]];

        yield 'interfaceGraph at seed 7' => [static fn (GraphGenerator $generator): GeneratedGraph => $generator->interfaceGraph(null, 1), 7, 'SaepeAdRerum\SedEnimDolor\ModiMinusInterface', [
            'SaepeAdRerum\SedEnimDolor\ModiMinusInterface -[declaration-constant]-> SedAutNobis\RationeVitaeNobis\AdModiDolorClass::DOLOR_ATQUE_NIHIL_CONST',
            'SaepeAdRerum\SedEnimDolor\ModiMinusInterface -[declaration-method]-> AliasAdLabore\OdioSintMagniClass::porroVeroMethod',
            'SaepeAdRerum\SedEnimDolor\ModiMinusInterface -[declaration-method]-> AliasIllum\ErrorDicta\SolutaPorroSaepe\AdTemporaClass::velitSolutaRationeMethod',
            'SaepeAdRerum\SedEnimDolor\ModiMinusInterface -[declaration-method]-> AnimiRatione\UllamNobisSuntClass::iureBeataeBeataeMethod',
            'SaepeAdRerum\SedEnimDolor\ModiMinusInterface -[declaration-method]-> QuodSoluta\EsseVitaeTempora\IureQuod\VelitIdClass::modiVeroCulpaMethod',
        ]];

        yield 'traitGraph at seed 7' => [static fn (GraphGenerator $generator): GeneratedGraph => $generator->traitGraph(null, 1), 7, 'SaepeAdRerum\SedEnimDolor\ModiMinusTrait', [
            'SaepeAdRerum\SedEnimDolor\ModiMinusTrait -[declaration-method]-> AliasAdLabore\OdioSintMagniClass::porroVeroMethod',
            'SaepeAdRerum\SedEnimDolor\ModiMinusTrait -[declaration-method]-> AliasIllum\ErrorDicta\SolutaPorroSaepe\AdTemporaClass::velitSolutaRationeMethod',
            'SaepeAdRerum\SedEnimDolor\ModiMinusTrait -[declaration-method]-> AnimiRatione\UllamNobisSuntClass::iureBeataeBeataeMethod',
            'SaepeAdRerum\SedEnimDolor\ModiMinusTrait -[declaration-method]-> QuodSoluta\EsseVitaeTempora\IureQuod\VelitIdClass::modiVeroCulpaMethod',
            'SaepeAdRerum\SedEnimDolor\ModiMinusTrait -[declaration-property]-> SedAutNobis\RationeVitaeNobis\AdModiDolorClass::dolorAtqueNihilProperty',
        ]];

        yield 'enumGraph at seed 7' => [static fn (GraphGenerator $generator): GeneratedGraph => $generator->enumGraph(null, 1), 7, 'SaepeAdRerum\SedEnimDolor\ModiMinusEnum', [
            'SaepeAdRerum\SedEnimDolor\ModiMinusEnum -[declaration-enum-case]-> AliasAdLabore\OdioSintMagniEnum::PORRO_VERO_CASE',
            'SaepeAdRerum\SedEnimDolor\ModiMinusEnum -[declaration-enum-case]-> AliasIllum\ErrorDicta\SolutaPorroSaepe\AdTemporaEnum::VELIT_SOLUTA_RATIONE_CASE',
            'SaepeAdRerum\SedEnimDolor\ModiMinusEnum -[declaration-enum-case]-> AnimiRatione\UllamNobisSuntEnum::IURE_BEATAE_BEATAE_CASE',
            'SaepeAdRerum\SedEnimDolor\ModiMinusEnum -[declaration-enum-case]-> QuodSoluta\EsseVitaeTempora\IureQuod\VelitIdEnum::MODI_VERO_CULPA_CASE',
        ]];

        yield 'methodGraph at seed 7' => [static fn (GraphGenerator $generator): GeneratedGraph => $generator->methodGraph(null, 1), 7, 'SaepeAdRerum\SedEnimDolor\ModiMinusClass::sedUtFugitMethod', [
            'SaepeAdRerum\SedEnimDolor\ModiMinusClass::sedUtFugitMethod -[const-fetch]-> IllumDictaTempora\SolutaSuntSequi\RerumFuga\ErrorEiusIpsumClass::PORRO_RATIONE_RATIONE_CONST',
            'SaepeAdRerum\SedEnimDolor\ModiMinusClass::sedUtFugitMethod -[declaration-type-parameter]-> NihilNihilQuia\EnimErrorSunt\AnimiRatione\UllamNobisSunt',
            'SaepeAdRerum\SedEnimDolor\ModiMinusClass::sedUtFugitMethod -[declaration-type-parameter]-> NobisBeatae\AliasAdLabore\OdioSintMagni\PorroVeroClass',
            'SaepeAdRerum\SedEnimDolor\ModiMinusClass::sedUtFugitMethod -[declaration-type-parameter]-> OfficiaQuod\SequiEsseVitaeInterface',
            'SaepeAdRerum\SedEnimDolor\ModiMinusClass::sedUtFugitMethod -[declaration-type-parameter]-> SedAutNobis\RationeVitaeNobis\AdModiDolor',
            'SaepeAdRerum\SedEnimDolor\ModiMinusClass::sedUtFugitMethod -[declaration-type-parameter]-> VelitUllam\IllumSed\ModiSunt\CulpaHarumClass',
            'SaepeAdRerum\SedEnimDolor\ModiMinusClass::sedUtFugitMethod -[declaration-type-return]-> VelitError\IpsumSolutaPorro\RationeAdTemporaEnum',
            'SaepeAdRerum\SedEnimDolor\ModiMinusClass::sedUtFugitMethod -[function-call]-> FugaSedNihil\SaepeSaepeLabore\velitAliasFunction',
            'SaepeAdRerum\SedEnimDolor\ModiMinusClass::sedUtFugitMethod -[instantiation]-> SedFacere\EsseQuasIureClass',
            'SaepeAdRerum\SedEnimDolor\ModiMinusClass::sedUtFugitMethod -[property-access]-> CulpaDolorVero\NobisNemo\AutIure\SuntVoluptasPorroClass::sedIdProperty',
            'SaepeAdRerum\SedEnimDolor\ModiMinusClass::sedUtFugitMethod -[static-call]-> RerumIllumRerum\SequiMagni\AutAmetClass::facereVitaeIllumMethod',
        ]];

        yield 'functionGraph at seed 7' => [static fn (GraphGenerator $generator): GeneratedGraph => $generator->functionGraph(null, 1), 7, 'SaepeAdRerum\SedEnimDolor\modiMinusFunction', [
            'SaepeAdRerum\SedEnimDolor\modiMinusFunction -[declaration-type-parameter]-> NihilNihilQuia\EnimErrorSunt\AnimiRatione\UllamNobisSunt',
            'SaepeAdRerum\SedEnimDolor\modiMinusFunction -[declaration-type-parameter]-> NobisBeatae\AliasAdLabore\OdioSintMagni\PorroVeroClass',
            'SaepeAdRerum\SedEnimDolor\modiMinusFunction -[declaration-type-parameter]-> OfficiaQuod\SequiEsseVitaeInterface',
            'SaepeAdRerum\SedEnimDolor\modiMinusFunction -[declaration-type-parameter]-> SedAutNobis\RationeVitaeNobis\AdModiDolor',
            'SaepeAdRerum\SedEnimDolor\modiMinusFunction -[declaration-type-parameter]-> VelitUllam\IllumSed\ModiSunt\CulpaHarumClass',
            'SaepeAdRerum\SedEnimDolor\modiMinusFunction -[declaration-type-return]-> AliasIllum\ErrorDicta\SolutaPorroSaepe\AdTemporaEnum',
        ]];

        yield 'propertyGraph at seed 7' => [static fn (GraphGenerator $generator): GeneratedGraph => $generator->propertyGraph(null, 1), 7, 'SaepeAdRerum\SedEnimDolor\ModiMinusClass::sedUtFugitProperty', [
            'SaepeAdRerum\SedEnimDolor\ModiMinusClass::sedUtFugitProperty -[declaration-type-property]-> VelitError\IpsumSolutaPorro\RationeAdTemporaEnum',
        ]];

        yield 'typeGraph at seed 7' => [static fn (GraphGenerator $generator): GeneratedGraph => $generator->typeGraph(null, 1), 7, 'AdRerumHarum\EnimDolor\ModiMinusEnum', [
            'AdRerumHarum\EnimDolor\ModiMinusEnum -[declaration-enum-case]-> AliasAdLabore\OdioSintMagniEnum::PORRO_VERO_CASE',
            'AdRerumHarum\EnimDolor\ModiMinusEnum -[declaration-enum-case]-> AliasIllum\ErrorDicta\SolutaPorroSaepe\AdTemporaEnum::VELIT_SOLUTA_RATIONE_CASE',
            'AdRerumHarum\EnimDolor\ModiMinusEnum -[declaration-enum-case]-> AnimiRatione\UllamNobisSuntEnum::IURE_BEATAE_BEATAE_CASE',
            'AdRerumHarum\EnimDolor\ModiMinusEnum -[declaration-enum-case]-> QuodSoluta\EsseVitaeTempora\IureQuod\VelitIdEnum::MODI_VERO_CULPA_CASE',
        ]];

        yield 'constantGraph at seed 7' => [static fn (GraphGenerator $generator): GeneratedGraph => $generator->constantGraph(), 7, 'SaepeAdRerum\SedEnimDolor\ModiMinusClass::SED_UT_FUGIT_CONST', []];

        yield 'enumCaseGraph at seed 7' => [static fn (GraphGenerator $generator): GeneratedGraph => $generator->enumCaseGraph(), 7, 'SaepeAdRerum\SedEnimDolor\ModiMinusEnum::SED_UT_FUGIT_CASE', []];

        yield 'builtinGraph at seed 7' => [static fn (GraphGenerator $generator): GeneratedGraph => $generator->builtinGraph(), 7, 'SaepeAdRerum\SedEnimDolor\ModiMinus', []];

        yield 'classGraph at seed 11' => [static fn (GraphGenerator $generator): GeneratedGraph => $generator->classGraph(null, 1), 11, 'NatusVitaeMinus\QuodNatusClass', [
            'NatusVitaeMinus\QuodNatusClass -[declaration-constant]-> SuntSequiOfficia\ModiIureClass::ILLUM_OFFICIA_IPSUM_CONST',
            'NatusVitaeMinus\QuodNatusClass -[declaration-extends]-> VitaeAd\IureSimilique\CulpaAtqueSaepeClass',
            'NatusVitaeMinus\QuodNatusClass -[declaration-implements]-> AliasFacere\HicPorroUllamInterface',
            'NatusVitaeMinus\QuodNatusClass -[declaration-implements]-> EiusSaepe\OfficiaAnimiEnim\NihilIpsum\OfficiaBeataeInterface',
            'NatusVitaeMinus\QuodNatusClass -[declaration-implements]-> IpsumErrorSunt\SuntEnim\HarumIpsumInterface',
            'NatusVitaeMinus\QuodNatusClass -[declaration-implements]-> MinusNihilRerum\BeataeQuodInterface',
            'NatusVitaeMinus\QuodNatusClass -[declaration-implements]-> QuiaVeroVitae\QuiaIllumInterface',
            'NatusVitaeMinus\QuodNatusClass -[declaration-property]-> IllumSuntVoluptas\SequiUllamNihilClass::vitaeSaepeNatusProperty',
            'NatusVitaeMinus\QuodNatusClass -[declaration-property]-> RerumPorroAtque\SolutaLaboreClass::quodAdProperty',
            'NatusVitaeMinus\QuodNatusClass -[declaration-trait-use]-> FacereNobis\UtHicEsse\OmnisHicHicTrait',
            'NatusVitaeMinus\QuodNatusClass -[declaration-trait-use]-> SimiliqueNihilEsse\OfficiaModiVero\EarumOmnis\IureAliasAutTrait',
            'NatusVitaeMinus\QuodNatusClass -[declaration-trait-use]-> UllamHicNihil\AmetTemporaFugitTrait',
        ]];

        yield 'interfaceGraph at seed 11' => [static fn (GraphGenerator $generator): GeneratedGraph => $generator->interfaceGraph(null, 1), 11, 'NatusVitaeMinus\QuodNatusInterface', [
            'NatusVitaeMinus\QuodNatusInterface -[declaration-constant]-> IllumSuntVoluptas\SequiUllamNihilClass::VITAE_SAEPE_NATUS_CONST',
            'NatusVitaeMinus\QuodNatusInterface -[declaration-constant]-> RerumPorroAtque\SolutaLaboreClass::QUOD_AD_CONST',
            'NatusVitaeMinus\QuodNatusInterface -[declaration-extends]-> SuntSequiOfficia\ModiIureInterface',
        ]];

        yield 'traitGraph at seed 11' => [static fn (GraphGenerator $generator): GeneratedGraph => $generator->traitGraph(null, 1), 11, 'NatusVitaeMinus\QuodNatusTrait', [
            'NatusVitaeMinus\QuodNatusTrait -[declaration-property]-> IllumSuntVoluptas\SequiUllamNihilClass::vitaeSaepeNatusProperty',
            'NatusVitaeMinus\QuodNatusTrait -[declaration-property]-> RerumPorroAtque\SolutaLaboreClass::quodAdProperty',
        ]];

        yield 'enumGraph at seed 11' => [static fn (GraphGenerator $generator): GeneratedGraph => $generator->enumGraph(null, 1), 11, 'NatusVitaeMinus\QuodNatusEnum', []];

        yield 'methodGraph at seed 27 two levels deep' => [static fn (GraphGenerator $generator): GeneratedGraph => $generator->methodGraph(null, 2), 27, 'AdIllum\IllumNemoQuod\QuiaOmnis\UllamVoluptasClass::quasSaepeMethod', [
            'AdIllum\IllumNemoQuod\QuiaOmnis\UllamVoluptasClass::quasSaepeMethod -[declaration-type-return]-> ErrorMinus\NobisLabore\AnimiAutEnum',
            'AdIllum\IllumNemoQuod\QuiaOmnis\UllamVoluptasClass::quasSaepeMethod -[method-call]-> NobisSuntAlias\IpsumNihilClass::officiaMagniTemporaMethod',
            'AdIllum\IllumNemoQuod\QuiaOmnis\UllamVoluptasClass::quasSaepeMethod -[static-property-access]-> OmnisFugit\OfficiaSintEarumClass::commodiAtqueEnimProperty',
            'ErrorMinus\NobisLabore\AnimiAutEnum -[declaration-enum-case]-> UllamEnimVitae\AmetUllam\IpsumPorroVoluptas\MinusIureQuiaEnum::SAEPE_DOLOR_CASE',
            'NobisSuntAlias\IpsumNihilClass::officiaMagniTemporaMethod -[declaration-type-return]-> AmetOfficia\UllamQuiaFacere\VelitFugitClass',
            'NobisSuntAlias\IpsumNihilClass::officiaMagniTemporaMethod -[function-call]-> OfficiaMinus\RerumError\AutRerumDolor\ametHicFunction',
            'NobisSuntAlias\IpsumNihilClass::officiaMagniTemporaMethod -[instantiation]-> MagniVitae\VelitQuia\IureRationeClass',
            'NobisSuntAlias\IpsumNihilClass::officiaMagniTemporaMethod -[static-call]-> VelitSolutaUt\SintEsseClass::fugitAutMethod',
            'NobisSuntAlias\IpsumNihilClass::officiaMagniTemporaMethod -[static-property-access]-> NemoPorro\EarumSint\PorroPorroSaepe\VeroLaboreFacereClass::omnisUllamProperty',
            'OmnisFugit\OfficiaSintEarumClass::commodiAtqueEnimProperty -[declaration-type-property]-> QuiaLabore\OdioAnimi\HicIureRerumEnum',
        ]];

        yield 'methodGraph at seed 11' => [static fn (GraphGenerator $generator): GeneratedGraph => $generator->methodGraph(null, 1), 11, 'NatusVitaeMinus\QuodNatusClass::sintQuasMethod', [
            'NatusVitaeMinus\QuodNatusClass::sintQuasMethod -[declaration-type-return]-> IllumSuntVoluptas\SequiUllamNihilClass',
            'NatusVitaeMinus\QuodNatusClass::sintQuasMethod -[function-call]-> PorroAtque\SolutaLabore\quodAdFunction',
            'NatusVitaeMinus\QuodNatusClass::sintQuasMethod -[property-access]-> SuntSequiOfficia\ModiIureClass::illumOfficiaIpsumProperty',
            'NatusVitaeMinus\QuodNatusClass::sintQuasMethod -[static-property-access]-> VitaeAd\IureSimilique\CulpaAtqueSaepeClass::magniVeroProperty',
        ]];

        yield 'functionGraph at seed 11' => [static fn (GraphGenerator $generator): GeneratedGraph => $generator->functionGraph(null, 1), 11, 'NatusVitaeMinus\quodNatusFunction', [
            'NatusVitaeMinus\quodNatusFunction -[declaration-type-parameter]-> AtqueVitaeSoluta\NihilQuod\IureMinus\SimiliqueVelitEsse',
            'NatusVitaeMinus\quodNatusFunction -[declaration-type-parameter]-> SaepeOmnisMagni\IllumVoluptasQuod\NatusQuodAlias',
            'NatusVitaeMinus\quodNatusFunction -[declaration-type-parameter]-> UtUllamNemo\NatusFugaClass',
            'NatusVitaeMinus\quodNatusFunction -[declaration-type-return]-> DictaIllum\VoluptasQuas\UllamNihilVoluptas\SaepeNatusClass',
            'NatusVitaeMinus\quodNatusFunction -[function-call]-> SimiliqueVelit\RationeBeatae\esseTemporaFugaFunction',
        ]];

        yield 'propertyGraph at seed 11' => [static fn (GraphGenerator $generator): GeneratedGraph => $generator->propertyGraph(null, 1), 11, 'NatusVitaeMinus\QuodNatusClass::sintQuasProperty', [
            'NatusVitaeMinus\QuodNatusClass::sintQuasProperty -[declaration-type-property]-> IllumSuntVoluptas\SequiUllamNihilClass',
        ]];

        yield 'typeGraph at seed 11' => [static fn (GraphGenerator $generator): GeneratedGraph => $generator->typeGraph(null, 1), 11, 'VitaeMinus\QuodNatus\SintQuasEnum', [
            'VitaeMinus\QuodNatus\SintQuasEnum -[declaration-enum-case]-> IllumSuntVoluptas\SequiUllamNihilEnum::VITAE_SAEPE_NATUS_CASE',
            'VitaeMinus\QuodNatus\SintQuasEnum -[declaration-enum-case]-> RerumPorroAtque\SolutaLaboreEnum::QUOD_AD_CASE',
        ]];

        yield 'constantGraph at seed 11' => [static fn (GraphGenerator $generator): GeneratedGraph => $generator->constantGraph(), 11, 'NatusVitaeMinus\QuodNatusClass::SINT_QUAS_CONST', []];

        yield 'enumCaseGraph at seed 11' => [static fn (GraphGenerator $generator): GeneratedGraph => $generator->enumCaseGraph(), 11, 'NatusVitaeMinus\QuodNatusEnum::SINT_QUAS_CASE', []];

        yield 'builtinGraph at seed 11' => [static fn (GraphGenerator $generator): GeneratedGraph => $generator->builtinGraph(), 11, 'NatusVitaeMinus\QuodNatus', []];
    }

    /**
     * @param Closure(GraphGenerator, ClassLikeGraphGenerator, MemberGraphGenerator): GeneratedGraph<Node> $byDefault
     * @param Closure(GraphGenerator, ClassLikeGraphGenerator, MemberGraphGenerator): GeneratedGraph<Node> $atFiveLevels
     */
    #[DataProvider('providerEveryOperationTakingADepth')]
    public function testEveryOperationGeneratesFiveLevelsUnlessToldOtherwise(int $seed, Closure $byDefault, Closure $atFiveLevels): void
    {
        $spell = static fn (Edge $edge): string => $edge->from()->toString().' -['.$edge->kind()->value.']-> '.$edge->to()->toString();
        $firstRandom = new RandomSource($seed);
        $firstIds = new NodeIdGenerator(new NameGenerator($firstRandom), $firstRandom);
        $firstNodes = new NodeGenerator($firstIds, $firstRandom);
        $firstClassLikes = new ClassLikeGraphGenerator($firstNodes, $firstIds, $firstRandom);
        $firstMembers = new MemberGraphGenerator($firstNodes, $firstIds, $firstRandom);
        $first = $byDefault(new GraphGenerator($firstClassLikes, $firstMembers, new LeafGraphGenerator($firstNodes), $firstIds, $firstRandom), $firstClassLikes, $firstMembers);
        $againRandom = new RandomSource($seed);
        $againIds = new NodeIdGenerator(new NameGenerator($againRandom), $againRandom);
        $againNodes = new NodeGenerator($againIds, $againRandom);
        $againClassLikes = new ClassLikeGraphGenerator($againNodes, $againIds, $againRandom);
        $againMembers = new MemberGraphGenerator($againNodes, $againIds, $againRandom);
        $again = $atFiveLevels(new GraphGenerator($againClassLikes, $againMembers, new LeafGraphGenerator($againNodes), $againIds, $againRandom), $againClassLikes, $againMembers);

        self::assertSame($again->root->id()->toString(), $first->root->id()->toString());
        self::assertSame(array_map($spell, $again->graph->forwardEdges()), array_map($spell, $first->graph->forwardEdges()));
    }

    /**
     * @return iterable<string, array{int, Closure(GraphGenerator, ClassLikeGraphGenerator, MemberGraphGenerator): GeneratedGraph<Node>, Closure(GraphGenerator, ClassLikeGraphGenerator, MemberGraphGenerator): GeneratedGraph<Node>}>
     */
    public static function providerEveryOperationTakingADepth(): iterable
    {
        yield 'a class' => [130, static fn (GraphGenerator $generator): GeneratedGraph => $generator->classGraph(), static fn (GraphGenerator $generator): GeneratedGraph => $generator->classGraph(null, 5)];

        yield 'an interface' => [240, static fn (GraphGenerator $generator): GeneratedGraph => $generator->interfaceGraph(), static fn (GraphGenerator $generator): GeneratedGraph => $generator->interfaceGraph(null, 5)];

        yield 'a trait' => [142, static fn (GraphGenerator $generator): GeneratedGraph => $generator->traitGraph(), static fn (GraphGenerator $generator): GeneratedGraph => $generator->traitGraph(null, 5)];

        yield 'an enum' => [7, static fn (GraphGenerator $generator): GeneratedGraph => $generator->enumGraph(), static fn (GraphGenerator $generator): GeneratedGraph => $generator->enumGraph(null, 5)];

        yield 'a method' => [169, static fn (GraphGenerator $generator): GeneratedGraph => $generator->methodGraph(), static fn (GraphGenerator $generator): GeneratedGraph => $generator->methodGraph(null, 5)];

        yield 'a function' => [204, static fn (GraphGenerator $generator): GeneratedGraph => $generator->functionGraph(), static fn (GraphGenerator $generator): GeneratedGraph => $generator->functionGraph(null, 5)];

        yield 'a property' => [246, static fn (GraphGenerator $generator): GeneratedGraph => $generator->propertyGraph(), static fn (GraphGenerator $generator): GeneratedGraph => $generator->propertyGraph(null, 5)];

        yield 'a type' => [209, static fn (GraphGenerator $generator): GeneratedGraph => $generator->typeGraph(), static fn (GraphGenerator $generator): GeneratedGraph => $generator->typeGraph(null, 5)];

        yield 'a class drawn by the class-like generator itself' => [130, static fn (GraphGenerator $generator, ClassLikeGraphGenerator $classLikes): GeneratedGraph => $classLikes->classGraph($generator), static fn (GraphGenerator $generator, ClassLikeGraphGenerator $classLikes): GeneratedGraph => $classLikes->classGraph($generator, null, 5)];

        yield 'an interface drawn by the class-like generator itself' => [240, static fn (GraphGenerator $generator, ClassLikeGraphGenerator $classLikes): GeneratedGraph => $classLikes->interfaceGraph($generator), static fn (GraphGenerator $generator, ClassLikeGraphGenerator $classLikes): GeneratedGraph => $classLikes->interfaceGraph($generator, null, 5)];

        yield 'a trait drawn by the class-like generator itself' => [142, static fn (GraphGenerator $generator, ClassLikeGraphGenerator $classLikes): GeneratedGraph => $classLikes->traitGraph($generator), static fn (GraphGenerator $generator, ClassLikeGraphGenerator $classLikes): GeneratedGraph => $classLikes->traitGraph($generator, null, 5)];

        yield 'an enum drawn by the class-like generator itself' => [7, static fn (GraphGenerator $generator, ClassLikeGraphGenerator $classLikes): GeneratedGraph => $classLikes->enumGraph($generator), static fn (GraphGenerator $generator, ClassLikeGraphGenerator $classLikes): GeneratedGraph => $classLikes->enumGraph($generator, null, 5)];

        yield 'a method drawn by the member generator itself' => [169, static fn (GraphGenerator $generator, ClassLikeGraphGenerator $classLikes, MemberGraphGenerator $members): GeneratedGraph => $members->methodGraph($generator), static fn (GraphGenerator $generator, ClassLikeGraphGenerator $classLikes, MemberGraphGenerator $members): GeneratedGraph => $members->methodGraph($generator, null, 5)];

        yield 'a function drawn by the member generator itself' => [204, static fn (GraphGenerator $generator, ClassLikeGraphGenerator $classLikes, MemberGraphGenerator $members): GeneratedGraph => $members->functionGraph($generator), static fn (GraphGenerator $generator, ClassLikeGraphGenerator $classLikes, MemberGraphGenerator $members): GeneratedGraph => $members->functionGraph($generator, null, 5)];

        yield 'a property drawn by the member generator itself' => [246, static fn (GraphGenerator $generator, ClassLikeGraphGenerator $classLikes, MemberGraphGenerator $members): GeneratedGraph => $members->propertyGraph($generator), static fn (GraphGenerator $generator, ClassLikeGraphGenerator $classLikes, MemberGraphGenerator $members): GeneratedGraph => $members->propertyGraph($generator, null, 5)];
    }

    public function testTheWholeGraphGeneratesFiveLevelsUnlessToldOtherwise(): void
    {
        $spell = static fn (Edge $edge): string => $edge->from()->toString().' -['.$edge->kind()->value.']-> '.$edge->to()->toString();
        $firstRandom = new RandomSource(293);
        $firstIds = new NodeIdGenerator(new NameGenerator($firstRandom), $firstRandom);
        $firstNodes = new NodeGenerator($firstIds, $firstRandom);
        $byDefault = (new GraphGenerator(new ClassLikeGraphGenerator($firstNodes, $firstIds, $firstRandom), new MemberGraphGenerator($firstNodes, $firstIds, $firstRandom), new LeafGraphGenerator($firstNodes), $firstIds, $firstRandom))->graph();
        $againRandom = new RandomSource(293);
        $againIds = new NodeIdGenerator(new NameGenerator($againRandom), $againRandom);
        $againNodes = new NodeGenerator($againIds, $againRandom);
        $atFiveLevels = (new GraphGenerator(new ClassLikeGraphGenerator($againNodes, $againIds, $againRandom), new MemberGraphGenerator($againNodes, $againIds, $againRandom), new LeafGraphGenerator($againNodes), $againIds, $againRandom))->graph(5);

        self::assertNotSame([], $byDefault->forwardEdges());
        self::assertSame(array_map($spell, $atFiveLevels->forwardEdges()), array_map($spell, $byDefault->forwardEdges()));
    }
}
