<?php

declare(strict_types=1);

namespace Tests\Unit\Analyzer\PhpStanAnalyzer;

use App\Analyzer\Graph\NodeKind;
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
#[UsesClass(\App\Analyzer\Graph\Edge\Usage\MethodCallEdge::class)]
#[UsesClass(\App\Analyzer\Graph\Edge\Inverse\UsedByEdge::class)]
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
    public function testBuildRecordsTheSymbolsItIsGiven(): void
    {
        $graph = (new GraphBuilder())->build([SampleNodes::invoice()]);

        self::assertSame(NodeKind::Klass, $graph->nodeNamed('App\Domain\Invoice')?->kind());
    }

    public function testBuildRecordsTheRelationsItIsGiven(): void
    {
        $graph = (new GraphBuilder())->build([SampleEdges::methodCall()]);

        self::assertCount(1, $graph->authoredEdges());
    }

    public function testBuildRecordsASymbolBeforeAnyRelationThatPointsAtIt(): void
    {
        $graph = (new GraphBuilder())->build([SampleEdges::methodCall(), SampleNodes::total()]);

        self::assertSame(NodeKind::Method, $graph->nodeNamed('App\Domain\Invoice::total')?->kind());
    }

    public function testBuildProducesAnEmptyGraphWhenNothingWasFound(): void
    {
        self::assertSame([], (new GraphBuilder())->build([])->nodes());
    }
}
