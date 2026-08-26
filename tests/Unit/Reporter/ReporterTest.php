<?php

declare(strict_types=1);

namespace Tests\Unit\Reporter;

use App\Analyzer\Graph\Direction;
use App\Analyzer\Graph\NodeId\ClassNodeId;
use App\Reporter\Reporter;
use App\Reporter\Traversal\DepthFirstTraversal;
use App\Reporter\TreeReporter\TreeReporter;
use App\Reporter\TreeReporter\TreeReporterOptions;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Output\BufferedOutput;
use Tests\Fixture\Graph\SampleGraph;

/**
 * @internal
 */
#[CoversClass(TreeReporter::class)]
#[UsesClass(\App\Analyzer\Graph\AuthoredEdge::class)]
#[UsesClass(\App\Analyzer\Graph\EdgeKind::class)]
#[UsesClass(\App\Analyzer\Graph\Edge\Declaration\MethodEdge::class)]
#[UsesClass(\App\Analyzer\Graph\Edge\Inverse\DeclaredInEdge::class)]
#[UsesClass(\App\Analyzer\Graph\Edge\Usage\MethodCallEdge::class)]
#[UsesClass(\App\Analyzer\Graph\Edge\Inverse\UsedByEdge::class)]
#[UsesClass(\App\Analyzer\Graph\FileMeta::class)]
#[UsesClass(\App\Analyzer\Graph\Graph::class)]
#[UsesClass(ClassNodeId::class)]
#[UsesClass(\App\Analyzer\Graph\NodeId\MethodNodeId::class)]
#[UsesClass(\App\Analyzer\Graph\Node\ClassNode::class)]
#[UsesClass(\App\Analyzer\Graph\Node\MethodNode::class)]
#[UsesClass(\App\Analyzer\Graph\QualifiedName::class)]
#[UsesClass(DepthFirstTraversal::class)]
#[UsesClass(\App\Reporter\Traversal\DepthFirstWalk::class)]
#[UsesClass(\App\Reporter\TreeReporter\LineRenderer::class)]
#[UsesClass(\App\Reporter\TreeReporter\TreeCursor::class)]
#[Small]
final class ReporterTest extends TestCase
{
    #[DataProvider('providerEveryReporter')]
    public function testReportWritesTheRequestedSymbolToTheGivenOutput(Reporter $reporter): void
    {
        $output = new BufferedOutput();
        $reporter->report(SampleGraph::invoice(), ClassNodeId::of('App\Domain\Invoice'), $output);

        self::assertStringContainsString('App\Domain\Invoice', $output->fetch());
    }

    #[DataProvider('providerEveryReporter')]
    public function testReportWritesNowhereElseThanTheGivenOutput(Reporter $reporter): void
    {
        $output = new BufferedOutput();
        $this->expectOutputString('');

        $reporter->report(SampleGraph::invoice(), ClassNodeId::of('App\Domain\Invoice'), $output);
    }

    #[DataProvider('providerEveryReporter')]
    public function testReportWritesNothingForASymbolTheGraphDoesNotHold(Reporter $reporter): void
    {
        $output = new BufferedOutput();
        $reporter->report(SampleGraph::invoice(), ClassNodeId::of('App\Domain\Missing'), $output);

        self::assertSame('', $output->fetch());
    }

    /**
     * @return iterable<string, array{Reporter}>
     */
    public static function providerEveryReporter(): iterable
    {
        yield 'the tree reporter reading away from the subject' => [
            new TreeReporter(new TreeReporterOptions(), new DepthFirstTraversal(Direction::Uses)),
        ];

        yield 'the tree reporter reading towards the subject' => [
            new TreeReporter(new TreeReporterOptions(), new DepthFirstTraversal(Direction::UsedBy)),
        ];
    }
}
