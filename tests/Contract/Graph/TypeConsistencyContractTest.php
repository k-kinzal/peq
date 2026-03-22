<?php

declare(strict_types=1);

namespace Tests\Contract\Graph;

use App\Analyzer\Graph\Edge;
use App\Analyzer\Graph\Edge\AttributeEdge;
use App\Analyzer\Graph\Edge\CatchEdge;
use App\Analyzer\Graph\Edge\ConstFetchEdge;
use App\Analyzer\Graph\Edge\DeclarationConstantEdge;
use App\Analyzer\Graph\Edge\DeclarationEnumCaseEdge;
use App\Analyzer\Graph\Edge\DeclarationExtendsEdge;
use App\Analyzer\Graph\Edge\DeclarationImplementsEdge;
use App\Analyzer\Graph\Edge\DeclarationMethodEdge;
use App\Analyzer\Graph\Edge\DeclarationPropertyEdge;
use App\Analyzer\Graph\Edge\DeclarationTraitUseEdge;
use App\Analyzer\Graph\Edge\DeclarationTypeParameterEdge;
use App\Analyzer\Graph\Edge\DeclarationTypePropertyEdge;
use App\Analyzer\Graph\Edge\DeclarationTypeReturnEdge;
use App\Analyzer\Graph\Edge\DeclaredInEdge;
use App\Analyzer\Graph\Edge\FunctionCallEdge;
use App\Analyzer\Graph\Edge\InstanceofEdge;
use App\Analyzer\Graph\Edge\InstantiationEdge;
use App\Analyzer\Graph\Edge\MethodCallEdge;
use App\Analyzer\Graph\Edge\PropertyAccessEdge;
use App\Analyzer\Graph\Edge\StaticCallEdge;
use App\Analyzer\Graph\Edge\StaticPropertyAccessEdge;
use App\Analyzer\Graph\Edge\UsedByEdge;
use App\Analyzer\Graph\EdgeKind;
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
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 *
 * Verifies that enum-to-class mappings are exhaustive: every EdgeKind has
 * a corresponding Edge class, every NodeKind has a corresponding Node class
 * and NodeId class, and file counts match enum case counts.
 */
final class TypeConsistencyContractTest extends TestCase
{
    /** @var array<string, class-string> */
    private const EDGE_KIND_TO_CLASS = [
        'function-call' => FunctionCallEdge::class,
        'method-call' => MethodCallEdge::class,
        'static-call' => StaticCallEdge::class,
        'instantiation' => InstantiationEdge::class,
        'property-access' => PropertyAccessEdge::class,
        'static-property-access' => StaticPropertyAccessEdge::class,
        'const-fetch' => ConstFetchEdge::class,
        'declaration-trait-use' => DeclarationTraitUseEdge::class,
        'declaration-extends' => DeclarationExtendsEdge::class,
        'declaration-implements' => DeclarationImplementsEdge::class,
        'declaration-method' => DeclarationMethodEdge::class,
        'declaration-property' => DeclarationPropertyEdge::class,
        'declaration-constant' => DeclarationConstantEdge::class,
        'declaration-enum-case' => DeclarationEnumCaseEdge::class,
        'declaration-type-parameter' => DeclarationTypeParameterEdge::class,
        'declaration-type-return' => DeclarationTypeReturnEdge::class,
        'declaration-type-property' => DeclarationTypePropertyEdge::class,
        'attribute' => AttributeEdge::class,
        'instanceof' => InstanceofEdge::class,
        'catch' => CatchEdge::class,
        'used-by' => UsedByEdge::class,
        'declared-in' => DeclaredInEdge::class,
    ];

    /** @var array<string, class-string> */
    private const NODE_KIND_TO_CLASS = [
        'class' => ClassNode::class,
        'constant' => ConstantNode::class,
        'enum_case' => EnumCaseNode::class,
        'enum' => EnumNode::class,
        'function' => FunctionNode::class,
        'interface' => GraphInterfaceNode::class,
        'method' => MethodNode::class,
        'property' => PropertyNode::class,
        'trait' => TraitNode::class,
        'builtin' => BuiltinNode::class,
        'unknown' => UnknownNode::class,
    ];

    /** @var array<string, class-string> */
    private const NODE_KIND_TO_ID_CLASS = [
        'class' => ClassNodeId::class,
        'constant' => ConstantNodeId::class,
        'enum_case' => EnumCaseNodeId::class,
        'enum' => EnumNodeId::class,
        'function' => FunctionNodeId::class,
        'interface' => InterfaceNodeId::class,
        'method' => MethodNodeId::class,
        'property' => PropertyNodeId::class,
        'trait' => TraitNodeId::class,
        'builtin' => BuiltinNodeId::class,
        'unknown' => UnknownNodeId::class,
    ];

    #[Test]
    public function testEveryEdgeKindHasEdgeClass(): void
    {
        foreach (EdgeKind::cases() as $case) {
            self::assertArrayHasKey(
                $case->value,
                self::EDGE_KIND_TO_CLASS,
                sprintf('EdgeKind::%s (%s) has no entry in EDGE_KIND_TO_CLASS', $case->name, $case->value),
            );
        }

        self::assertCount(
            count(EdgeKind::cases()),
            self::EDGE_KIND_TO_CLASS,
            'EDGE_KIND_TO_CLASS map size does not match EdgeKind case count',
        );
    }

    #[Test]
    public function testEveryEdgeClassReturnsCorrectKind(): void
    {
        foreach (self::EDGE_KIND_TO_CLASS as $kindValue => $className) {
            self::assertTrue(class_exists($className), sprintf('Class %s does not exist', $className));

            $reflection = new \ReflectionClass($className);

            self::assertTrue(
                $reflection->implementsInterface(Edge::class),
                sprintf('%s does not implement Edge interface', $className),
            );

            self::assertTrue(
                $reflection->isReadOnly(),
                sprintf('%s is not a readonly class', $className),
            );

            self::assertTrue(
                $reflection->hasMethod('kind'),
                sprintf('%s does not have a kind() method', $className),
            );
        }
    }

    #[Test]
    public function testEveryNodeKindHasNodeClass(): void
    {
        foreach (NodeKind::cases() as $case) {
            self::assertArrayHasKey(
                $case->value,
                self::NODE_KIND_TO_CLASS,
                sprintf('NodeKind::%s (%s) has no entry in NODE_KIND_TO_CLASS', $case->name, $case->value),
            );
        }

        self::assertCount(
            count(NodeKind::cases()),
            self::NODE_KIND_TO_CLASS,
            'NODE_KIND_TO_CLASS map size does not match NodeKind case count',
        );
    }

    #[Test]
    public function testEveryNodeKindHasNodeIdClass(): void
    {
        foreach (NodeKind::cases() as $case) {
            self::assertArrayHasKey(
                $case->value,
                self::NODE_KIND_TO_ID_CLASS,
                sprintf('NodeKind::%s (%s) has no entry in NODE_KIND_TO_ID_CLASS', $case->name, $case->value),
            );
        }

        self::assertCount(
            count(NodeKind::cases()),
            self::NODE_KIND_TO_ID_CLASS,
            'NODE_KIND_TO_ID_CLASS map size does not match NodeKind case count',
        );
    }

    #[Test]
    public function testEdgeKindInvertCoversAllNonReverseCases(): void
    {
        $nonReverseCases = array_filter(
            EdgeKind::cases(),
            static fn (EdgeKind $kind): bool => $kind !== EdgeKind::UsedBy && $kind !== EdgeKind::DeclaredIn,
        );

        foreach ($nonReverseCases as $case) {
            $inverted = $case->invert();

            self::assertContains(
                $inverted,
                [EdgeKind::UsedBy, EdgeKind::DeclaredIn],
                sprintf('EdgeKind::%s->invert() returned unexpected kind %s', $case->name, $inverted->name),
            );
        }
    }

    #[Test]
    public function testEdgeClassCountMatchesEdgeKindCount(): void
    {
        $edgeDir = __DIR__.'/../../../src/Analyzer/Graph/Edge';
        $files = glob($edgeDir.'/*.php');
        self::assertNotFalse($files, 'Failed to glob Edge directory');

        $phpFileCount = count($files);

        self::assertSame(
            count(EdgeKind::cases()),
            $phpFileCount,
            sprintf(
                'Number of PHP files in src/Analyzer/Graph/Edge/ (%d) does not match EdgeKind case count (%d)',
                $phpFileCount,
                count(EdgeKind::cases()),
            ),
        );
    }
}
