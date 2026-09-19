<?php

declare(strict_types=1);

namespace Tests\Unit\Gql\Element;

use App\Analyzer\Graph\Declaration\AttributeUsage;
use App\Analyzer\Graph\Declaration\Modifiers;
use App\Analyzer\Graph\Declaration\Parameter;
use App\Analyzer\Graph\Declaration\Signature;
use App\Analyzer\Graph\Declaration\SymbolDeclaration;
use App\Analyzer\Graph\Declaration\Visibility;
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
use App\Analyzer\Graph\NodePrecedence;
use App\Analyzer\Graph\QualifiedName;
use App\Gql\Datum\BooleanDatum;
use App\Gql\Datum\DatumKind;
use App\Gql\Datum\EdgeDatum;
use App\Gql\Datum\IntegerDatum;
use App\Gql\Datum\ListDatum;
use App\Gql\Datum\NodeDatum;
use App\Gql\Datum\StringDatum;
use App\Gql\Element\EdgeLabels;
use App\Gql\Element\EdgeProperties;
use App\Gql\Element\ElementGraph;
use App\Gql\Element\GraphProjection;
use App\Gql\Element\NodeLabels;
use App\Gql\Element\NodeProperties;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Tests\Fixture\Gql\SampleGraph;

/**
 * @internal
 */
#[CoversClass(ElementGraph::class)]
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
#[UsesClass(AttributeUsage::class)]
#[UsesClass(Modifiers::class)]
#[UsesClass(Parameter::class)]
#[UsesClass(Signature::class)]
#[UsesClass(SymbolDeclaration::class)]
#[UsesClass(Visibility::class)]
#[UsesClass(BooleanDatum::class)]
#[UsesClass(DatumKind::class)]
#[UsesClass(EdgeDatum::class)]
#[UsesClass(IntegerDatum::class)]
#[UsesClass(ListDatum::class)]
#[UsesClass(NodeDatum::class)]
#[UsesClass(StringDatum::class)]
#[UsesClass(EdgeLabels::class)]
#[UsesClass(EdgeProperties::class)]
#[UsesClass(GraphProjection::class)]
#[UsesClass(NodeLabels::class)]
#[UsesClass(NodeProperties::class)]
#[Small]
final class ElementGraphTest extends TestCase
{
    public function testNodesOffersEverySymbolAPatternCanStartFrom(): void
    {
        self::assertCount(9, SampleGraph::elements()->nodes());
    }

    public function testNodesOfAnEmptyGraphOffersNothingToStartFrom(): void
    {
        self::assertSame([], (new ElementGraph([], [], []))->nodes());
    }

    public function testNodeFindsASymbolByWhatIdentifiesIt(): void
    {
        self::assertSame('App\Domain\Invoice::total', SampleGraph::elements()->node('App\Domain\Invoice::total')?->id);
    }

    public function testNodeFindsNothingForASymbolTheGraphDoesNotHold(): void
    {
        self::assertNull(SampleGraph::elements()->node('App\Nothing'));
    }

    public function testLeavingReadsTheRelationsWrittenFromASymbol(): void
    {
        $leaving = SampleGraph::elements()->leaving('App\Http\Controller');

        self::assertSame(['extends', 'declaresMethod', 'declaresMethod'], array_map(
            static fn ($edge): string => $edge->label(),
            $leaving,
        ));
    }

    public function testLeavingReadsNothingFromASymbolTheGraphDoesNotHold(): void
    {
        self::assertSame([], SampleGraph::elements()->leaving('App\Nothing'));
    }

    public function testArrivingReadsTheRelationsWrittenTowardsASymbol(): void
    {
        self::assertCount(3, SampleGraph::elements()->arriving('App\Domain\Invoice::total'));
    }

    public function testArrivingReadsNothingTowardsASymbolTheGraphDoesNotHold(): void
    {
        self::assertSame([], SampleGraph::elements()->arriving('App\Nothing'));
    }

    public function testArrivingReadsOnlyTheRelationsSourceCodeWrites(): void
    {
        self::assertSame([], SampleGraph::elements()->arriving('App\Missing'));
    }

    public function testBetweenReadsTheRelationsJoiningASetOfSymbols(): void
    {
        $joined = SampleGraph::elements()->between([
            'App\Http\Controller::show' => true,
            'App\Domain\Invoice::total' => true,
        ]);

        self::assertCount(1, $joined);
    }

    public function testBetweenLeavesOutARelationWithOnlyOneEndInTheSet(): void
    {
        self::assertSame([], SampleGraph::elements()->between(['App\Http\Controller::show' => true]));
    }

    public function testBetweenJoinsNothingWhenNothingIsWanted(): void
    {
        self::assertSame([], SampleGraph::elements()->between([]));
    }
}
