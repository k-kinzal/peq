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
#[CoversClass(GraphProjection::class)]
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
#[UsesClass(ElementGraph::class)]
#[UsesClass(NodeLabels::class)]
#[UsesClass(NodeProperties::class)]
#[Small]
final class GraphProjectionTest extends TestCase
{
    public function testOfTurnsEveryAnalysedSymbolIntoOneAPatternCanMatch(): void
    {
        self::assertCount(9, GraphProjection::of(SampleGraph::analysed())->nodes());
    }

    public function testOfMakesARelationReachableFromBothOfItsEnds(): void
    {
        $projected = GraphProjection::of(SampleGraph::analysed());

        self::assertCount(1, $projected->arriving('App\Http\Kernel'));
    }

    public function testOfLeavesOutTheReadingsPeqDerivesRatherThanReads(): void
    {
        $projected = GraphProjection::of(SampleGraph::analysed());

        self::assertSame([], $projected->leaving('App\Http\Kernel'));
    }

    public function testNodeCarriesTheLabelsAPatternSelectsASymbolBy(): void
    {
        self::assertSame(['Class', 'ClassLike', 'Resolved'], GraphProjection::node(SampleGraph::invoice())->labels);
    }

    public function testNodeCarriesWhatAQueryCanAskTheSymbol(): void
    {
        self::assertSame('Invoice', GraphProjection::node(SampleGraph::invoice())->property('name')->toText());
    }

    public function testEdgeIsIdentifiedByWhatItJoinsAndHow(): void
    {
        $written = new MethodCallEdge(SampleGraph::show(), SampleGraph::total(), SampleGraph::at('Http/Controller.php', 22));

        self::assertSame(
            'App\Http\Controller::show|method-call|App\Domain\Invoice::total',
            GraphProjection::edge($written)->id,
        );
    }

    public function testEdgeRemembersWhichEndItLeavesAndWhichItArrivesAt(): void
    {
        $written = new MethodCallEdge(SampleGraph::show(), SampleGraph::total(), SampleGraph::at('Http/Controller.php', 22));

        self::assertSame('App\Http\Controller::show', GraphProjection::edge($written)->origin);
    }

    public function testEdgeCarriesTheLabelsAPatternSelectsARelationBy(): void
    {
        $written = new MethodCallEdge(SampleGraph::show(), SampleGraph::total(), SampleGraph::at('Http/Controller.php', 22));

        self::assertSame(['methodCall', 'call', 'usage'], GraphProjection::edge($written)->labels);
    }
}
