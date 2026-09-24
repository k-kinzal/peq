<?php

declare(strict_types=1);

namespace Tests\Unit\Analyzer\Graph\Resolution;

use App\Analyzer\Graph\Declaration\Modifiers;
use App\Analyzer\Graph\Declaration\SymbolDeclaration;
use App\Analyzer\Graph\Declaration\Visibility;
use App\Analyzer\Graph\Edge\Declaration\ExtendsEdge;
use App\Analyzer\Graph\Edge\Declaration\ImplementsEdge;
use App\Analyzer\Graph\Edge\Usage\MethodCallEdge;
use App\Analyzer\Graph\Edge\Usage\PossibleCallEdge;
use App\Analyzer\Graph\FileMeta;
use App\Analyzer\Graph\Graph;
use App\Analyzer\Graph\Node\ClassNode;
use App\Analyzer\Graph\Node\GraphInterfaceNode;
use App\Analyzer\Graph\Node\MethodNode;
use App\Analyzer\Graph\NodeId\ClassNodeId;
use App\Analyzer\Graph\NodeId\InterfaceNodeId;
use App\Analyzer\Graph\NodeId\MethodNodeId;
use App\Analyzer\Graph\Resolution\CallDispatch;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(CallDispatch::class)]
#[Medium]
final class CallDispatchTest extends TestCase
{
    public function testEnrichOnlyIncludesImplementationsSatisfyingTheReceiverConstraintAreCandidates(): void
    {
        $graph = (new \App\Analyzer\NativeAnalyzer\NativeAnalyzer())->analyze(dirname(__DIR__, 4).'/Fixture/Source/Dip.php');

        CallDispatch::enrich($graph);
        $edges = array_values(array_filter($graph->forwardEdges(), static fn ($edge): bool => $edge->kind() === \App\Analyzer\Graph\EdgeKind::PossibleCall && $edge->from()->toString() === 'Tests\Fixture\Source\Dip\Controller::action'));
        $targets = array_map(static fn ($edge): string => $edge->to()->toString(), $edges);

        self::assertSame(['Tests\Fixture\Source\Dip\BaseService::execute', 'Tests\Fixture\Source\Dip\BaseService::execute'], $targets);
    }

    public function testEnrichUsesTheDeclaredOwnerWhenNoReceiverConstraintWasRecorded(): void
    {
        $graph = new Graph();
        $base = new ClassNode(ClassNodeId::of('Base'), true);
        $child = new ClassNode(ClassNodeId::of('Child'), true);
        $caller = new MethodNode(MethodNodeId::of('Controller', 'action'), true);
        $declared = new MethodNode(MethodNodeId::of('Base', 'run'), true);
        $body = new MethodNode(MethodNodeId::of('Child', 'run'), true);
        $meta = new FileMeta('/source.php', 1, 1);
        $call = new MethodCallEdge($caller, $declared, $meta);
        $graph->addNodes([$base, $child, $caller, $declared, $body]);
        $graph->addEdges([new ExtendsEdge($child, $base, $meta), $call]);

        CallDispatch::enrich($graph);
        $possible = array_values(array_filter($graph->forwardEdges(), static fn ($edge): bool => $edge instanceof PossibleCallEdge));

        self::assertCount(1, $possible);
        self::assertSame('Child::run', $possible[0]->to()->toString());
        self::assertSame('Base', $possible[0]->receiverType);
        self::assertSame('Child', $possible[0]->implementationType);
    }

    public function testEnrichExcludesMissingUnresolvedPrivateAndAbstractBodies(): void
    {
        $graph = new Graph();
        $port = new GraphInterfaceNode(InterfaceNodeId::of('Port'), true);
        $caller = new MethodNode(MethodNodeId::of('Controller', 'action'), true);
        $declared = new MethodNode(MethodNodeId::of('Port', 'run'), true);
        $meta = new FileMeta('/source.php', 1, 1);
        $graph->addNodes([$port, $caller, $declared]);
        $classes = array_map(static fn (string $name): ClassNode => new ClassNode(ClassNodeId::of($name), true), ['Missing', 'Unresolved', 'PrivateBody', 'AbstractBody', 'Concrete']);
        $graph->addNodes($classes);
        $graph->addEdges(array_map(static fn (ClassNode $class): ImplementsEdge => new ImplementsEdge($class, $port, $meta), $classes));
        $graph->addNodes([
            new MethodNode(MethodNodeId::of('Unresolved', 'run')),
            new MethodNode(MethodNodeId::of('PrivateBody', 'run'), true, null, new SymbolDeclaration(visibility: Visibility::Private)),
            new MethodNode(MethodNodeId::of('AbstractBody', 'run'), true, null, new SymbolDeclaration(modifiers: new Modifiers(abstract: true))),
            new MethodNode(MethodNodeId::of('Concrete', 'run'), true),
        ]);
        $graph->addEdge(new MethodCallEdge($caller, $declared, $meta));

        CallDispatch::enrich($graph);
        $possible = array_values(array_filter($graph->forwardEdges(), static fn ($edge): bool => $edge instanceof PossibleCallEdge));

        self::assertSame(['Concrete::run'], array_map(static fn ($edge): string => $edge->to()->toString(), $possible));
    }

    public function testEnrichDoesNotTreatAnInterfaceRedeclarationAsAnImplementation(): void
    {
        $graph = new Graph();
        $port = new GraphInterfaceNode(InterfaceNodeId::of('Port'), true);
        $specialized = new GraphInterfaceNode(InterfaceNodeId::of('Specialized'), true);
        $incomplete = new ClassNode(ClassNodeId::of('Incomplete'), true);
        $caller = new MethodNode(MethodNodeId::of('Controller', 'action'), true);
        $declared = new MethodNode(MethodNodeId::of('Port', 'run'), true);
        $contract = new MethodNode(MethodNodeId::of('Specialized', 'run'), true);
        $meta = new FileMeta('/source.php', 1, 1);
        $graph->addNodes([$port, $specialized, $incomplete, $caller, $declared, $contract]);
        $graph->addEdges([new ExtendsEdge($specialized, $port, $meta), new ImplementsEdge($incomplete, $specialized, $meta), new MethodCallEdge($caller, $declared, $meta)]);

        CallDispatch::enrich($graph);

        self::assertSame([], array_values(array_filter($graph->forwardEdges(), static fn ($edge): bool => $edge instanceof PossibleCallEdge)));
    }
}
