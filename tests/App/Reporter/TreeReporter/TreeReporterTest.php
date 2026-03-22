<?php

declare(strict_types=1);

namespace Tests\App\Reporter\TreeReporter;

use App\Analyzer\Graph\Edge\FunctionCallEdge;
use App\Analyzer\Graph\FileMeta;
use App\Analyzer\Graph\Graph;
use App\Analyzer\Graph\Node\FunctionNode;
use App\Analyzer\Graph\NodeId\FunctionNodeId;
use App\Reporter\TreeReporter\TreeReporter;
use App\Reporter\TreeReporter\TreeReporterOptions;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @internal
 */
final class TreeReporterTest extends TestCase
{
    #[Test]
    public function testReportOutputsTreeStructure(): void
    {
        $graph = new Graph();
        $nodeA = new FunctionNode(new FunctionNodeId('App', 'A'));
        $nodeB = new FunctionNode(new FunctionNodeId('App', 'B'));

        $graph->addNode($nodeA);
        $graph->addNode($nodeB);
        $graph->addEdge(new FunctionCallEdge($nodeA, $nodeB, new FileMeta('file.php', 1, 1)));

        $output = $this->createMock(OutputInterface::class);
        $output->expects(self::exactly(2))
            ->method('writeln')
            ->willReturnCallback(function (string $line) {
                static $calls = 0;

                /** @var int $calls */
                ++$calls;
                if ($calls === 1) {
                    self::assertSame('App\A', $line);
                } elseif ($calls === 2) {
                    self::assertSame('└── App\B', $line);
                }
            })
        ;

        $reporter = new TreeReporter(new TreeReporterOptions());
        $reporter->report($graph, $nodeA->id, $output);
    }

    #[Test]
    public function testReportRespectsLevelOption(): void
    {
        $graph = new Graph();
        $nodeA = new FunctionNode(new FunctionNodeId('App', 'A'));
        $nodeB = new FunctionNode(new FunctionNodeId('App', 'B'));

        $graph->addNode($nodeA);
        $graph->addNode($nodeB);
        $graph->addEdge(new FunctionCallEdge($nodeA, $nodeB, new FileMeta('file.php', 1, 1)));

        $output = $this->createMock(OutputInterface::class);
        $output->expects(self::once())
            ->method('writeln')
            ->with('App\A')
        ;

        $reporter = new TreeReporter(new TreeReporterOptions(level: 0));
        $reporter->report($graph, $nodeA->id, $output);
    }

    #[Test]
    public function testReportHandlesRecursion(): void
    {
        $graph = new Graph();
        $nodeA = new FunctionNode(new FunctionNodeId('App', 'A'));
        $nodeB = new FunctionNode(new FunctionNodeId('App', 'B'));
        $nodeC = new FunctionNode(new FunctionNodeId('App', 'C'));

        $graph->addNode($nodeA);
        $graph->addNode($nodeB);
        $graph->addNode($nodeC);

        $graph->addEdge(new FunctionCallEdge($nodeA, $nodeB, new FileMeta('file.php', 1, 1)));
        $graph->addEdge(new FunctionCallEdge($nodeB, $nodeC, new FileMeta('file.php', 1, 1)));
        $graph->addEdge(new FunctionCallEdge($nodeC, $nodeA, new FileMeta('file.php', 1, 1)));

        $output = $this->createMock(OutputInterface::class);
        $output->expects(self::exactly(4))
            ->method('writeln')
            ->willReturnCallback(function (string $line) {
                static $calls = 0;

                /** @var int $calls */
                ++$calls;
                if ($calls === 1) {
                    self::assertSame('App\A', $line);
                } elseif ($calls === 2) {
                    self::assertSame('└── App\B', $line);
                } elseif ($calls === 3) {
                    self::assertSame('    └── App\C', $line);
                } elseif ($calls === 4) {
                    self::assertSame('        └── App\A (recursive)', $line);
                }
            })
        ;

        $reporter = new TreeReporter(new TreeReporterOptions());
        $reporter->report($graph, $nodeA->id, $output);
    }
}
