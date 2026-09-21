<?php

declare(strict_types=1);

namespace Tests\Unit\Gql\Element;

use App\Analyzer\Graph\Declaration\AttributeUsage;
use App\Analyzer\Graph\Declaration\Modifiers;
use App\Analyzer\Graph\Declaration\Parameter;
use App\Analyzer\Graph\Declaration\Signature;
use App\Analyzer\Graph\Declaration\SymbolDeclaration;
use App\Analyzer\Graph\FileMeta;
use App\Analyzer\Graph\Node\MethodNode;
use App\Analyzer\Graph\Node\UnknownNode;
use App\Analyzer\Graph\NodeId\MethodNodeId;
use App\Analyzer\Graph\NodeId\UnknownNodeId;
use App\Analyzer\Graph\NodeKind;
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
#[UsesClass(AttributeUsage::class)]
#[UsesClass(Modifiers::class)]
#[UsesClass(Parameter::class)]
#[UsesClass(Signature::class)]
#[UsesClass(SymbolDeclaration::class)]
#[UsesClass(FileMeta::class)]
#[UsesClass(MethodNodeId::class)]
#[UsesClass(UnknownNodeId::class)]
#[UsesClass(NodeKind::class)]
#[UsesClass(MethodNode::class)]
#[UsesClass(UnknownNode::class)]
#[UsesClass(QualifiedName::class)]
#[Small]
final class NodeLabelsTest extends TestCase
{
    /**
     * @param list<string> $labels
     */
    #[DataProvider('providerKindsAndTheLabelsTheyCarry')]
    public function testForKindReadsTheFamiliesAKindOfSymbolBelongsTo(NodeKind $kind, array $labels): void
    {
        self::assertSame($labels, NodeLabels::forKind($kind));
    }

    /**
     * @return iterable<string, array{NodeKind, list<string>}>
     */
    public static function providerKindsAndTheLabelsTheyCarry(): iterable
    {
        yield 'a class' => [NodeKind::Klass, ['Class', 'ClassLike']];

        yield 'an interface' => [NodeKind::Interface, ['Interface', 'ClassLike']];

        yield 'a trait' => [NodeKind::Trait, ['Trait', 'ClassLike']];

        yield 'an enum' => [NodeKind::Enum, ['Enum', 'ClassLike']];

        yield 'a method, which is a member and callable at once' => [NodeKind::Method, ['Method', 'Member', 'Callable']];

        yield 'a function, which is callable without being a member' => [NodeKind::Function, ['Function', 'Callable']];

        yield 'a property' => [NodeKind::Property, ['Property', 'Member']];

        yield 'a constant' => [NodeKind::Constant, ['Constant', 'Member']];

        yield 'an enum case' => [NodeKind::EnumCase, ['EnumCase', 'Member']];

        yield 'something PHP itself provides' => [NodeKind::Builtin, ['Builtin']];

        yield 'something analysis never identified' => [NodeKind::Unknown, ['Unknown']];
    }

    public function testOfSaysThatASymbolAnalysisFoundWasFound(): void
    {
        self::assertSame(['Method', 'Member', 'Callable', 'Resolved'], NodeLabels::of(SampleGraph::show()));
    }

    public function testOfSaysThatASymbolAnalysisNeverFoundWasNot(): void
    {
        self::assertSame(['Unknown', 'Unresolved'], NodeLabels::of(new UnknownNode(new UnknownNodeId('App\Missing'))));
    }

    public function testAllOffersEveryLabelOnceWithWhetherASymbolWasFoundLast(): void
    {
        self::assertSame(
            [
                'Class',
                'ClassLike',
                'Constant',
                'Member',
                'EnumCase',
                'Enum',
                'Function',
                'Callable',
                'Interface',
                'Method',
                'Property',
                'Trait',
                'Builtin',
                'Unknown',
                'Resolved',
                'Unresolved',
            ],
            NodeLabels::all(),
        );
    }
}
