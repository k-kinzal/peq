<?php

declare(strict_types=1);

namespace Tests\Unit\Analyzer\Graph;

use App\Analyzer\Graph\Edge\Usage\MethodCallEdge;
use App\Analyzer\Graph\FileMeta;
use App\Analyzer\Graph\Graph;
use App\Analyzer\Graph\GraphSnapshot;
use App\Analyzer\Graph\Node\ClassNode;
use App\Analyzer\Graph\Node\MethodNode;
use App\Analyzer\Graph\NodeId\ClassNodeId;
use App\Analyzer\Graph\NodeId\MethodNodeId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[UsesClass(\App\Analyzer\Graph\EdgeIdentity::class)]
#[CoversClass(GraphSnapshot::class)]
#[UsesClass(\App\Analyzer\Graph\AuthoredEdge::class)]
#[UsesClass(MethodCallEdge::class)]
#[UsesClass(\App\Analyzer\Graph\Edge\Inverse\UsedByEdge::class)]
#[UsesClass(FileMeta::class)]
#[UsesClass(Graph::class)]
#[UsesClass(\App\Analyzer\Graph\GraphDifference::class)]
#[UsesClass(ClassNodeId::class)]
#[UsesClass(MethodNodeId::class)]
#[UsesClass(\App\Analyzer\Graph\NodeId\UnknownNodeId::class)]
#[UsesClass(ClassNode::class)]
#[UsesClass(MethodNode::class)]
#[UsesClass(\App\Analyzer\Graph\Node\UnknownNode::class)]
#[UsesClass(\App\Analyzer\Graph\QualifiedName::class)]
#[Small]
final class GraphSnapshotTest extends TestCase
{
    public function testOfWritesOneLinePerSymbol(): void
    {
        $graph = new Graph();
        $graph->addNode(new ClassNode(ClassNodeId::of('App\Domain\Invoice'), true, new FileMeta('/project/Invoice.php', 12, 1)));

        self::assertSame(['class App\Domain\Invoice resolved=yes at /project/Invoice.php:12:1'], GraphSnapshot::of($graph)->nodes);
    }

    public function testOfWritesOneLinePerRelationSourceCodeWrites(): void
    {
        $meta = new FileMeta('/project/Invoice.php', 12, 1);
        $graph = new Graph();
        $graph->addEdge(new MethodCallEdge(
            new MethodNode(MethodNodeId::of('App\Domain\Invoice', 'total'), true, $meta),
            new MethodNode(MethodNodeId::of('App\Domain\Money', 'add'), true, $meta),
            $meta,
        ));

        self::assertSame(
            ['method-call App\Domain\Invoice::total -> App\Domain\Money::add at /project/Invoice.php:12:1'],
            GraphSnapshot::of($graph)->edges,
        );
    }

    public function testOfLeavesOutTheRelationsTheGraphDerivesForItself(): void
    {
        $meta = new FileMeta('/project/Invoice.php', 12, 1);
        $graph = new Graph();
        $graph->addEdge(new MethodCallEdge(
            new MethodNode(MethodNodeId::of('App\Domain\Invoice', 'total'), true, $meta),
            new MethodNode(MethodNodeId::of('App\Domain\Money', 'add'), true, $meta),
            $meta,
        ));

        self::assertCount(1, GraphSnapshot::of($graph)->edges);
    }

    public function testOfWritesTwoGraphsBuiltInOppositeOrdersTheSameWay(): void
    {
        $one = new ClassNode(ClassNodeId::of('App\A'), true, null);
        $two = new ClassNode(ClassNodeId::of('App\B'), true, null);
        $first = new Graph();
        $first->addNodes([$one, $two]);
        $second = new Graph();
        $second->addNodes([$two, $one]);

        self::assertSame(GraphSnapshot::of($first)->fingerprint(), GraphSnapshot::of($second)->fingerprint());
    }

    public function testOfTellsTwoGraphsThatSayDifferentThingsApart(): void
    {
        $first = new Graph();
        $first->addNode(new ClassNode(ClassNodeId::of('App\A'), true, null));
        $second = new Graph();
        $second->addNode(new ClassNode(ClassNodeId::of('App\B'), true, null));

        self::assertNotSame(GraphSnapshot::of($first)->fingerprint(), GraphSnapshot::of($second)->fingerprint());
    }

    public function testPlaceOfWritesNowhereForASymbolWithNoPlace(): void
    {
        self::assertSame('nowhere', GraphSnapshot::placeOf(null));
    }

    public function testPlaceOfWritesTheFileLineAndColumn(): void
    {
        self::assertSame('/project/Invoice.php:12:4', GraphSnapshot::placeOf(new FileMeta('/project/Invoice.php', 12, 4)));
    }

    public function testToStringSaysNothingAboutAnEmptyGraph(): void
    {
        self::assertSame('', GraphSnapshot::of(new Graph())->toString());
    }

    public function testFingerprintOfAnEmptyGraphIsTheFingerprintOfNothing(): void
    {
        self::assertSame(hash('sha256', ''), GraphSnapshot::of(new Graph())->fingerprint());
    }

    public function testDifferenceFromReportsNothingAgainstItself(): void
    {
        $graph = new Graph();
        $graph->addNode(new ClassNode(ClassNodeId::of('App\A'), true, null));
        $snapshot = GraphSnapshot::of($graph);

        self::assertTrue($snapshot->differenceFrom($snapshot)->isEmpty());
    }

    public function testDifferenceFromReportsWhatTheOtherGraphHoldsAndThisOneDoesNot(): void
    {
        $expected = new Graph();
        $expected->addNode(new ClassNode(ClassNodeId::of('App\A'), true, null));

        self::assertSame(
            ['class App\A resolved=yes at nowhere'],
            GraphSnapshot::of(new Graph())->differenceFrom(GraphSnapshot::of($expected))->missingNodes,
        );
    }

    public function testDifferenceFromReportsWhatThisGraphHoldsAndTheOtherDoesNot(): void
    {
        $candidate = new Graph();
        $candidate->addNode(new ClassNode(ClassNodeId::of('App\A'), true, null));

        self::assertSame(
            ['class App\A resolved=yes at nowhere'],
            GraphSnapshot::of($candidate)->differenceFrom(GraphSnapshot::of(new Graph()))->unexpectedNodes,
        );
    }
}
