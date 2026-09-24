<?php

declare(strict_types=1);

namespace Tests\Unit\Analyzer\PhpStanAnalyzer\Processor\Usage;

use App\Analyzer\Graph\Edge;
use App\Analyzer\PhpStanAnalyzer\Collector\InClassMethodCollector;
use App\Analyzer\PhpStanAnalyzer\PhpStanAnalyzer;
use App\Analyzer\PhpStanAnalyzer\Processor\Usage\CatchProcessor;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(CatchProcessor::class)]
#[Medium]
final class CatchProcessorTest extends TestCase
{
    public function testProcessRecordsTheExceptionTypeACatchClauseNames(): void
    {
        $graph = (new PhpStanAnalyzer(collectors: [InClassMethodCollector::class]))->analyze(dirname(__DIR__, 5).'/Fixture/Source/Guards.php');
        $relations = array_map(static fn (Edge $edge): string => $edge->from()->toString().' -['.$edge->kind()->value.']-> '.$edge->to()->toString(), $graph->forwardEdges());

        self::assertContains('Tests\Fixture\Source\GuardedClass::guarded -[catch]-> RuntimeException', $relations);
    }

    public function testProcessRecordsNothingForSourcesWithNoSuchRelation(): void
    {
        $graph = (new PhpStanAnalyzer(collectors: [InClassMethodCollector::class]))->analyze(dirname(__DIR__, 5).'/Fixture/Source/ClassDependency.php');
        $relations = array_map(static fn (Edge $edge): string => $edge->from()->toString().' -['.$edge->kind()->value.']-> '.$edge->to()->toString(), $graph->forwardEdges());

        self::assertNotContains('Tests\Fixture\Source\GuardedClass::guarded -[catch]-> RuntimeException', $relations);
    }
}
