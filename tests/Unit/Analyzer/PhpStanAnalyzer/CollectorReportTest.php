<?php

declare(strict_types=1);

namespace Tests\Unit\Analyzer\PhpStanAnalyzer;

use App\Analyzer\PhpStanAnalyzer\Collector\DependencyCollector;
use App\Analyzer\PhpStanAnalyzer\CollectorReport;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Tests\Fixture\Graph\SampleEdges;
use Tests\Fixture\Graph\SampleNodes;

/**
 * @internal
 */
#[CoversClass(CollectorReport::class)]
#[UsesClass(\App\Analyzer\Graph\AuthoredEdge::class)]
#[UsesClass(\App\Analyzer\Graph\Edge\Usage\MethodCallEdge::class)]
#[UsesClass(\App\Analyzer\Graph\FileMeta::class)]
#[UsesClass(\App\Analyzer\Graph\NodeId\ClassNodeId::class)]
#[UsesClass(\App\Analyzer\Graph\NodeId\MethodNodeId::class)]
#[UsesClass(\App\Analyzer\Graph\Node\ClassNode::class)]
#[UsesClass(\App\Analyzer\Graph\Node\MethodNode::class)]
#[UsesClass(\App\Analyzer\Graph\QualifiedName::class)]
#[Small]
final class CollectorReportTest extends TestCase
{
    public function testOfReadsTheSymbolsACollectorReported(): void
    {
        $report = CollectorReport::of(
            ['/project/src/Invoice.php' => [DependencyCollector::class => [[SampleNodes::invoice()]]]],
            [DependencyCollector::class],
        );

        self::assertCount(1, $report->symbols());
    }

    public function testOfReadsTheRelationsACollectorReported(): void
    {
        $report = CollectorReport::of(
            ['/project/src/Invoice.php' => [DependencyCollector::class => [[SampleEdges::methodCall()]]]],
            [DependencyCollector::class],
        );

        self::assertCount(1, $report->symbols());
    }

    public function testOfReadsOnlyTheCollectorsItWasAskedFor(): void
    {
        $report = CollectorReport::of(
            ['/project/src/Invoice.php' => ['Some\Other\Collector' => [[SampleNodes::invoice()]]]],
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
        $items = CollectorReport::itemsOf([[SampleNodes::invoice()], [SampleEdges::methodCall()]]);

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
        $node = SampleNodes::invoice();

        self::assertSame([$node], (new CollectorReport([$node]))->symbols());
    }
}
