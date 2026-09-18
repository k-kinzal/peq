<?php

declare(strict_types=1);

namespace Tests\Unit\Analyzer\PhpStanAnalyzer;

use App\Analyzer\Graph\Edge\Usage\MethodCallEdge;
use App\Analyzer\Graph\FileMeta;
use App\Analyzer\Graph\Node\ClassNode;
use App\Analyzer\Graph\Node\MethodNode;
use App\Analyzer\Graph\NodeId\ClassNodeId;
use App\Analyzer\Graph\NodeId\MethodNodeId;
use App\Analyzer\PhpStanAnalyzer\Collector\DependencyCollector;
use App\Analyzer\PhpStanAnalyzer\CollectorReport;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(CollectorReport::class)]
#[UsesClass(\App\Analyzer\Graph\AuthoredEdge::class)]
#[UsesClass(MethodCallEdge::class)]
#[UsesClass(FileMeta::class)]
#[UsesClass(ClassNodeId::class)]
#[UsesClass(MethodNodeId::class)]
#[UsesClass(ClassNode::class)]
#[UsesClass(MethodNode::class)]
#[UsesClass(\App\Analyzer\Graph\QualifiedName::class)]
#[Small]
final class CollectorReportTest extends TestCase
{
    public function testOfReadsTheSymbolsACollectorReported(): void
    {
        $report = CollectorReport::of(
            ['/project/src/Invoice.php' => [DependencyCollector::class => [[new ClassNode(ClassNodeId::of('App\Domain\Invoice'), true)]]]],
            [DependencyCollector::class],
        );

        self::assertCount(1, $report->symbols());
    }

    public function testOfReadsTheRelationsACollectorReported(): void
    {
        $report = CollectorReport::of(
            ['/project/src/Invoice.php' => [DependencyCollector::class => [[new MethodCallEdge(new MethodNode(MethodNodeId::of('App\Domain\Invoice', 'total'), true), new MethodNode(MethodNodeId::of('App\Domain\Money', 'add'), true), new FileMeta('/project/src/Domain/Invoice.php', 12, 1))]]]],
            [DependencyCollector::class],
        );

        self::assertCount(1, $report->symbols());
    }

    public function testOfReadsOnlyTheCollectorsItWasAskedFor(): void
    {
        $report = CollectorReport::of(
            ['/project/src/Invoice.php' => ['Some\Other\Collector' => [[new ClassNode(ClassNodeId::of('App\Domain\Invoice'), true)]]]],
            [DependencyCollector::class],
        );

        self::assertSame([], $report->symbols());
    }

    public function testOfSkipsAFileWhoseFindingsAreNotShapedAsExpected(): void
    {
        $report = CollectorReport::of(
            ['/project/src/Invoice.php' => 'not an array'],
            [DependencyCollector::class],
        );

        self::assertSame([], $report->symbols());
    }

    public function testOfReadsNothingFromAnAnalysisThatCollectedNothing(): void
    {
        self::assertSame([], CollectorReport::of([], [DependencyCollector::class])->symbols());
    }

    public function testItemsOfReadsTheFindingsOfOneCollectorForOneFile(): void
    {
        $items = CollectorReport::itemsOf([[new ClassNode(ClassNodeId::of('App\Domain\Invoice'), true)], [new MethodCallEdge(new MethodNode(MethodNodeId::of('App\Domain\Invoice', 'total'), true), new MethodNode(MethodNodeId::of('App\Domain\Money', 'add'), true), new FileMeta('/project/src/Domain/Invoice.php', 12, 1))]]);

        self::assertCount(2, $items);
    }

    public function testItemsOfReadsNothingFromSomethingThatIsNotAListOfFindings(): void
    {
        self::assertSame([], CollectorReport::itemsOf('not an array'));
    }

    public function testItemsOfSkipsABatchThatIsNotAListOfFindings(): void
    {
        self::assertSame([], CollectorReport::itemsOf(['not a batch']));
    }

    public function testItemsOfSkipsAnythingThatIsNeitherASymbolNorARelation(): void
    {
        self::assertSame([], CollectorReport::itemsOf([['not a symbol', 42, null]]));
    }

    public function testSymbolsReportsWhatTheReportWasBuiltWith(): void
    {
        $node = new ClassNode(ClassNodeId::of('App\Domain\Invoice'), true);

        self::assertSame([$node], (new CollectorReport([$node]))->symbols());
    }

    public function testOfKeepsReadingFilesAfterOneWhoseFindingsAreNotShapedAsExpected(): void
    {
        $report = CollectorReport::of(
            [
                '/project/src/Broken.php' => 'not an array',
                '/project/src/Invoice.php' => [DependencyCollector::class => [[new ClassNode(ClassNodeId::of('App\Domain\Invoice'), true)]]],
            ],
            [DependencyCollector::class],
        );

        self::assertCount(1, $report->symbols());
    }

    public function testItemsOfKeepsReadingBatchesAfterOneThatIsNotAListOfFindings(): void
    {
        $items = CollectorReport::itemsOf(['not a batch', [new ClassNode(ClassNodeId::of('App\Domain\Invoice'), true)]]);

        self::assertCount(1, $items);
    }
}
