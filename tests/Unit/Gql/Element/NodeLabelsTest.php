<?php

declare(strict_types=1);

namespace Tests\Unit\Gql\Element;

use App\Analyzer\Graph\Edge;
use App\Analyzer\Graph\Edge\Declaration\ExtendsEdge;
use App\Analyzer\Graph\Edge\Declaration\MethodEdge;
use App\Analyzer\Graph\Edge\Usage\MethodCallEdge;
use App\Analyzer\Graph\FileMeta;
use App\Analyzer\Graph\Graph;
use App\Analyzer\Graph\Node;
use App\Analyzer\Graph\Node\ClassNode;
use App\Analyzer\Graph\Node\MethodNode;
use App\Analyzer\Graph\Node\UnknownNode;
use App\Analyzer\Graph\NodeId\ClassNodeId;
use App\Analyzer\Graph\NodeId\MethodNodeId;
use App\Analyzer\Graph\NodeId\UnknownNodeId;
use App\Analyzer\Graph\NodeKind;
use App\Analyzer\Graph\NodePrecedence;
use App\Analyzer\Graph\QualifiedName;
use App\Gql\Element\NodeLabels;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Tests\Fixture\Gql\SampleGraph;

/**
 * @internal
 */
#[CoversClass(NodeLabels::class)]
#[UsesClass(ExtendsEdge::class)]
#[UsesClass(MethodEdge::class)]
#[UsesClass(MethodCallEdge::class)]
#[UsesClass(FileMeta::class)]
#[UsesClass(Graph::class)]
#[UsesClass(ClassNode::class)]
#[UsesClass(MethodNode::class)]
#[UsesClass(UnknownNode::class)]
#[UsesClass(ClassNodeId::class)]
#[UsesClass(MethodNodeId::class)]
#[UsesClass(UnknownNodeId::class)]
#[UsesClass(NodePrecedence::class)]
#[UsesClass(QualifiedName::class)]
#[UsesClass(Edge::class)]
#[UsesClass(Node::class)]
#[UsesClass(NodeKind::class)]
#[Small]
final class NodeLabelsTest extends TestCase
{
    #[DataProvider('providerKindsAndTheLabelsTheyCarry')]
    public function testForKindReadsTheFamiliesAKindOfSymbolBelongsTo(NodeKind $kind, string $labels): void
    {
        self::assertSame($labels, implode(',', NodeLabels::forKind($kind)));
    }

    /**
     * @return iterable<string, array{NodeKind, string}>
     */
    public static function providerKindsAndTheLabelsTheyCarry(): iterable
    {
        yield 'a class' => [NodeKind::Klass, 'Class,ClassLike'];

        yield 'an interface' => [NodeKind::Interface, 'Interface,ClassLike'];

        yield 'a trait' => [NodeKind::Trait, 'Trait,ClassLike'];

        yield 'an enum' => [NodeKind::Enum, 'Enum,ClassLike'];

        yield 'a method, which is a member and callable at once' => [NodeKind::Method, 'Method,Member,Callable'];

        yield 'a function, which is callable without being a member' => [NodeKind::Function, 'Function,Callable'];

        yield 'a property' => [NodeKind::Property, 'Property,Member'];

        yield 'a constant' => [NodeKind::Constant, 'Constant,Member'];

        yield 'an enum case' => [NodeKind::EnumCase, 'EnumCase,Member'];

        yield 'something PHP itself provides' => [NodeKind::Builtin, 'Builtin'];

        yield 'something analysis never identified' => [NodeKind::Unknown, 'Unknown'];
    }

    public function testOfSaysThatASymbolAnalysisFoundWasFound(): void
    {
        self::assertContains('Resolved', NodeLabels::of(SampleGraph::show()));
    }

    public function testOfSaysThatASymbolAnalysisNeverFoundWasNot(): void
    {
        self::assertSame(
            ['Unknown', 'Unresolved'],
            NodeLabels::of(new UnknownNode(new UnknownNodeId('App\Missing'))),
        );
    }

    public function testAllOffersTheFamiliesAPatternSelectsBy(): void
    {
        self::assertContains('Callable', NodeLabels::all());
    }

    public function testAllOffersEachLabelOnlyOnce(): void
    {
        self::assertSame(array_unique(NodeLabels::all()), NodeLabels::all());
    }

    public function testAllOffersWhetherASymbolWasFoundAsSomethingToSelectBy(): void
    {
        self::assertContains('Unresolved', NodeLabels::all());
    }
}
