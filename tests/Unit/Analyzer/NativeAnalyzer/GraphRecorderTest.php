<?php

declare(strict_types=1);

namespace Tests\Unit\Analyzer\NativeAnalyzer;

use App\Analyzer\Graph\Edge\Usage\InstantiationEdge;
use App\Analyzer\Graph\FileMeta;
use App\Analyzer\Graph\Node\ClassNode;
use App\Analyzer\Graph\Node\MethodNode;
use App\Analyzer\Graph\NodeId\ClassNodeId;
use App\Analyzer\Graph\NodeId\MethodNodeId;
use App\Analyzer\Graph\NodeKind;
use App\Analyzer\NativeAnalyzer\GraphRecorder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(GraphRecorder::class)]
#[UsesClass(\App\Analyzer\Graph\AuthoredEdge::class)]
#[UsesClass(InstantiationEdge::class)]
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
final class GraphRecorderTest extends TestCase
{
    public function testGraphOfNothingRecordedIsEmpty(): void
    {
        self::assertSame([], (new GraphRecorder())->graph()->nodes());
    }

    public function testRecordKeepsTheSymbolsItIsGiven(): void
    {
        $recorder = new GraphRecorder();
        $recorder->record([new ClassNode(ClassNodeId::of('App\Domain\Invoice'), true, null)]);

        self::assertSame(NodeKind::Klass, $recorder->graph()->nodeNamed('App\Domain\Invoice')?->kind());
    }

    public function testRecordKeepsTheRelationsItIsGiven(): void
    {
        $meta = new FileMeta('/project/Invoice.php', 12, 1);
        $recorder = new GraphRecorder();
        $recorder->record([new InstantiationEdge(
            new MethodNode(MethodNodeId::of('App\Domain\Invoice', 'total'), true, null),
            new ClassNode(ClassNodeId::of('App\Domain\Money'), false, null),
            $meta,
        )]);

        self::assertNotNull($recorder->graph()->edge(MethodNodeId::of('App\Domain\Invoice', 'total'), ClassNodeId::of('App\Domain\Money')));
    }

    public function testGraphHoldsASymbolInFullEvenWhenARelationNamedItFirst(): void
    {
        $meta = new FileMeta('/project/Invoice.php', 12, 1);
        $recorder = new GraphRecorder();
        $recorder->record([new InstantiationEdge(
            new MethodNode(MethodNodeId::of('App\Domain\Invoice', 'total'), true, null),
            new ClassNode(ClassNodeId::of('App\Domain\Money'), false, null),
            $meta,
        )]);
        $recorder->record([new ClassNode(ClassNodeId::of('App\Domain\Money'), true, $meta)]);

        self::assertSame(NodeKind::Klass, $recorder->graph()->nodeNamed('App\Domain\Money')?->kind());
    }
}
