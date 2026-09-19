<?php

declare(strict_types=1);

namespace Tests\Unit\Analyzer\Graph;

use App\Analyzer\Graph\Node;
use App\Analyzer\Graph\Node\BuiltinNode;
use App\Analyzer\Graph\Node\ClassNode;
use App\Analyzer\Graph\Node\ConstantNode;
use App\Analyzer\Graph\Node\EnumCaseNode;
use App\Analyzer\Graph\Node\EnumNode;
use App\Analyzer\Graph\Node\FunctionNode;
use App\Analyzer\Graph\Node\GraphInterfaceNode;
use App\Analyzer\Graph\Node\MethodNode;
use App\Analyzer\Graph\Node\PropertyNode;
use App\Analyzer\Graph\Node\TraitNode;
use App\Analyzer\Graph\Node\UnknownNode;
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

/**
 * @internal
 */
#[CoversClass(BuiltinNode::class)]
#[CoversClass(ClassNode::class)]
#[CoversClass(ConstantNode::class)]
#[CoversClass(EnumCaseNode::class)]
#[CoversClass(EnumNode::class)]
#[CoversClass(FunctionNode::class)]
#[CoversClass(GraphInterfaceNode::class)]
#[CoversClass(MethodNode::class)]
#[CoversClass(PropertyNode::class)]
#[CoversClass(TraitNode::class)]
#[CoversClass(UnknownNode::class)]
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
final class NodeTest extends TestCase
{
    #[DataProvider('providerEveryKindOfNode')]
    public function testIdIsTheIdentifierTheNodeIsKeyedBy(Node $node, NodeKind $kind, string $identifier): void
    {
        self::assertSame($identifier, $node->id()->toString());
        self::assertSame($kind, $node->kind());
    }

    #[DataProvider('providerEveryKindOfNode')]
    public function testKindNamesTheSymbolTheNodeStandsFor(Node $node, NodeKind $kind, string $identifier): void
    {
        self::assertSame($kind, $node->kind());
        self::assertSame($identifier, $node->id()->toString());
    }

    #[DataProvider('providerEveryKindOfNode')]
    public function testResolvedIsAnsweredWithoutConsultingAnythingElse(Node $node, NodeKind $kind, string $identifier): void
    {
        self::assertTrue($node->resolved());
        self::assertSame($kind, $node->kind());
        self::assertSame($identifier, $node->id()->toString());
    }

    #[DataProvider('providerEveryKindOfNode')]
    public function testMetaIsAbsentUntilAnalysisSuppliesIt(Node $node, NodeKind $kind, string $identifier): void
    {
        self::assertNull($node->meta());
        self::assertSame($identifier, $node->id()->toString());
        self::assertNotSame(NodeKind::Unknown, $kind === NodeKind::Unknown ? NodeKind::Klass : $kind);
    }

    #[DataProvider('providerEveryKindOfNode')]
    public function testDeclarationIsAbsentUntilAnalysisReadsIt(Node $node, NodeKind $kind, string $identifier): void
    {
        self::assertNull($node->declaration());
        self::assertSame($identifier, $node->id()->toString());
        self::assertSame($kind, $node->kind());
    }

    /**
     * @return iterable<string, array{Node, NodeKind, string}>
     */
    public static function providerEveryKindOfNode(): iterable
    {
        yield 'class' => [new ClassNode(ClassNodeId::of('App\Domain\Invoice'), true), NodeKind::Klass, 'App\Domain\Invoice'];

        yield 'interface' => [new GraphInterfaceNode(InterfaceNodeId::of('App\Domain\Payable'), true), NodeKind::Interface, 'App\Domain\Payable'];

        yield 'trait' => [new TraitNode(TraitNodeId::of('App\Domain\Timestamped'), true), NodeKind::Trait, 'App\Domain\Timestamped'];

        yield 'enum' => [new EnumNode(EnumNodeId::of('App\Domain\InvoiceState'), true), NodeKind::Enum, 'App\Domain\InvoiceState'];

        yield 'enum case' => [new EnumCaseNode(EnumCaseNodeId::of('App\Domain\InvoiceState', 'OPEN'), true), NodeKind::EnumCase, 'App\Domain\InvoiceState::OPEN'];

        yield 'method' => [new MethodNode(MethodNodeId::of('App\Domain\Invoice', 'total'), true), NodeKind::Method, 'App\Domain\Invoice::total'];

        yield 'property' => [new PropertyNode(PropertyNodeId::of('App\Domain\Invoice', 'lines'), true), NodeKind::Property, 'App\Domain\Invoice::lines'];

        yield 'constant' => [new ConstantNode(ConstantNodeId::of('App\Domain\Invoice', 'MAX_ITEMS'), true), NodeKind::Constant, 'App\Domain\Invoice::MAX_ITEMS'];

        yield 'function' => [new FunctionNode(FunctionNodeId::of('App\Domain\formatMoney'), true), NodeKind::Function, 'App\Domain\formatMoney'];

        yield 'builtin' => [new BuiltinNode(BuiltinNodeId::of('int'), true), NodeKind::Builtin, 'int'];

        yield 'unresolved' => [new UnknownNode(new UnknownNodeId('App\Domain\Missing'), true), NodeKind::Unknown, 'App\Domain\Missing'];
    }

    public function testEveryImplementationReportsADistinctKind(): void
    {
        $kinds = array_map(
            static fn (array $case): NodeKind => $case[1],
            [...self::providerEveryKindOfNode()],
        );

        self::assertSame(array_values($kinds), array_values(array_unique($kinds, SORT_REGULAR)));
    }
}
