<?php

declare(strict_types=1);

namespace Tests\Unit\Analyzer\PhpStanAnalyzer;

use App\Analyzer\Graph\Edge\Usage\MethodCallEdge;
use App\Analyzer\Graph\FileMeta;
use App\Analyzer\Graph\Node\ClassNode;
use App\Analyzer\Graph\Node\MethodNode;
use App\Analyzer\Graph\NodeId\ClassNodeId;
use App\Analyzer\Graph\NodeId\MethodNodeId;
use App\Analyzer\Graph\NodeKind;
use App\Analyzer\PhpStanAnalyzer\GraphBuilder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(GraphBuilder::class)]
#[UsesClass(\App\Analyzer\Graph\AuthoredEdge::class)]
#[UsesClass(MethodCallEdge::class)]
#[UsesClass(\App\Analyzer\Graph\Edge\Inverse\UsedByEdge::class)]
#[UsesClass(FileMeta::class)]
#[UsesClass(\App\Analyzer\Graph\Graph::class)]
#[UsesClass(ClassNodeId::class)]
#[UsesClass(MethodNodeId::class)]
#[UsesClass(\App\Analyzer\Graph\NodeId\UnknownNodeId::class)]
#[UsesClass(ClassNode::class)]
#[UsesClass(MethodNode::class)]
#[UsesClass(\App\Analyzer\Graph\Node\UnknownNode::class)]
#[UsesClass(\App\Analyzer\Graph\QualifiedName::class)]
#[Small]
final class GraphBuilderTest extends TestCase
{
    public function testBuildRecordsTheSymbolsItIsGiven(): void
    {
        $graph = (new GraphBuilder())->build([new ClassNode(ClassNodeId::of('App\Domain\Invoice'), true)]);

        self::assertSame(NodeKind::Klass, $graph->nodeNamed('App\Domain\Invoice')?->kind());
    }

    public function testBuildRecordsTheRelationsItIsGiven(): void
    {
        $graph = (new GraphBuilder())->build([new MethodCallEdge(new MethodNode(MethodNodeId::of('App\Domain\Invoice', 'total'), true), new MethodNode(MethodNodeId::of('App\Domain\Money', 'add'), true), new FileMeta('/project/src/Domain/Invoice.php', 12, 1))]);

        self::assertCount(1, $graph->forwardEdges());
    }

    public function testBuildRecordsASymbolBeforeAnyRelationThatPointsAtIt(): void
    {
        $graph = (new GraphBuilder())->build([new MethodCallEdge(new MethodNode(MethodNodeId::of('App\Domain\Invoice', 'total'), true), new MethodNode(MethodNodeId::of('App\Domain\Money', 'add'), true), new FileMeta('/project/src/Domain/Invoice.php', 12, 1)), new MethodNode(MethodNodeId::of('App\Domain\Invoice', 'total'), true)]);

        self::assertSame(NodeKind::Method, $graph->nodeNamed('App\Domain\Invoice::total')?->kind());
    }

    public function testBuildProducesAnEmptyGraphWhenNothingWasFound(): void
    {
        self::assertSame([], (new GraphBuilder())->build([])->nodes());
    }
}
