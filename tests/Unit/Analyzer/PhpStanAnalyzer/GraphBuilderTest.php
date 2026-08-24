<?php

declare(strict_types=1);

namespace Tests\Unit\Analyzer\PhpStanAnalyzer;

use App\Analyzer\Graph\NodeKind;
use App\Analyzer\PhpStanAnalyzer\Collector\DependencyCollector;
use App\Analyzer\PhpStanAnalyzer\GraphBuilder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Tests\Fixture\Graph\SampleEdges;
use Tests\Fixture\Graph\SampleNodes;

/**
 * @internal
 */
#[CoversClass(GraphBuilder::class)]
#[UsesClass(\App\Analyzer\Graph\AuthoredEdge::class)]
#[UsesClass(\App\Analyzer\Graph\Edge\MethodCallEdge::class)]
#[UsesClass(\App\Analyzer\Graph\Edge\UsedByEdge::class)]
#[UsesClass(\App\Analyzer\Graph\FileMeta::class)]
#[UsesClass(\App\Analyzer\Graph\Graph::class)]
#[UsesClass(\App\Analyzer\Graph\NodeId\ClassNodeId::class)]
#[UsesClass(\App\Analyzer\Graph\NodeId\MethodNodeId::class)]
#[UsesClass(\App\Analyzer\Graph\NodeId\UnknownNodeId::class)]
#[UsesClass(\App\Analyzer\Graph\Node\ClassNode::class)]
#[UsesClass(\App\Analyzer\Graph\Node\MethodNode::class)]
#[UsesClass(\App\Analyzer\Graph\Node\UnknownNode::class)]
#[UsesClass(\App\Analyzer\Graph\QualifiedName::class)]
#[Small]
final class GraphBuilderTest extends TestCase
{
    public function testBuildRecordsTheSymbolsACollectorReported(): void
    {
        $graph = (new GraphBuilder())->build(
            ['/project/src/Invoice.php' => [DependencyCollector::class => [[SampleNodes::invoice()]]]],
            [DependencyCollector::class],
        );

        self::assertSame(NodeKind::Klass, $graph->nodeNamed('App\Domain\Invoice')?->kind());
    }

    public function testBuildRecordsTheRelationsACollectorReported(): void
    {
        $graph = (new GraphBuilder())->build(
            ['/project/src/Invoice.php' => [DependencyCollector::class => [[SampleEdges::methodCall()]]]],
            [DependencyCollector::class],
        );

        self::assertCount(1, $graph->authoredEdges());
    }

    public function testBuildRecordsASymbolBeforeAnyRelationThatPointsAtIt(): void
    {
        $graph = (new GraphBuilder())->build(
            ['/project/src/Invoice.php' => [DependencyCollector::class => [[SampleEdges::methodCall(), SampleNodes::total()]]]],
            [DependencyCollector::class],
        );

        self::assertSame(NodeKind::Method, $graph->nodeNamed('App\Domain\Invoice::total')?->kind());
    }

    public function testBuildReadsOnlyTheCollectorsItWasAskedFor(): void
    {
        $graph = (new GraphBuilder())->build(
            ['/project/src/Invoice.php' => ['Some\Other\Collector' => [[SampleNodes::invoice()]]]],
            [DependencyCollector::class],
        );

        self::assertSame([], $graph->nodes());
    }

    public function testBuildSkipsAnythingThatIsNeitherASymbolNorARelation(): void
    {
        $graph = (new GraphBuilder())->build(
            ['/project/src/Invoice.php' => [DependencyCollector::class => [['not a node', 42, null]]]],
            [DependencyCollector::class],
        );

        self::assertSame([], $graph->nodes());
    }

    public function testBuildSkipsAFileWhoseFindingsAreNotShapedAsExpected(): void
    {
        $graph = (new GraphBuilder())->build(
            ['/project/src/Invoice.php' => 'not an array'],
            [DependencyCollector::class],
        );

        self::assertSame([], $graph->nodes());
    }

    public function testBuildProducesAnEmptyGraphWhenNothingWasCollected(): void
    {
        self::assertSame([], (new GraphBuilder())->build([], [DependencyCollector::class])->nodes());
    }

    public function testItemsOfReadsTheFindingsOfOneCollectorForOneFile(): void
    {
        $items = (new GraphBuilder())->itemsOf([[SampleNodes::invoice()], [SampleEdges::methodCall()]]);

        self::assertCount(2, $items);
    }

    public function testItemsOfReadsNothingFromSomethingThatIsNotAListOfFindings(): void
    {
        self::assertSame([], (new GraphBuilder())->itemsOf('not an array'));
    }

    public function testItemsOfSkipsABatchThatIsNotAListOfFindings(): void
    {
        self::assertSame([], (new GraphBuilder())->itemsOf(['not a batch']));
    }
}
