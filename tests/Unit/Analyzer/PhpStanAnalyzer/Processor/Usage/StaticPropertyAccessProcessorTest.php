<?php

declare(strict_types=1);

namespace Tests\Unit\Analyzer\PhpStanAnalyzer\Processor\Usage;

use App\Analyzer\Graph\Edge;
use App\Analyzer\PhpStanAnalyzer\Collector\InClassMethodCollector;
use App\Analyzer\PhpStanAnalyzer\PhpStanAnalyzer;
use App\Analyzer\PhpStanAnalyzer\Processor\Usage\StaticPropertyAccessProcessor;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(StaticPropertyAccessProcessor::class)]
#[Medium]
final class StaticPropertyAccessProcessorTest extends TestCase
{
    public function testProcessRecordsAStaticPropertyBeingRead(): void
    {
        $graph = (new PhpStanAnalyzer(collectors: [InClassMethodCollector::class]))->analyze(dirname(__DIR__, 5).'/Fixture/Source/UsageProcessors.php');
        $relations = array_map(static fn (Edge $edge): string => $edge->from()->toString().' -['.$edge->kind()->value.']-> '.$edge->to()->toString(), $graph->forwardEdges());

        self::assertContains('Tests\Fixture\Source\UsageProcessorFixture::testMethod -[static-property-access]-> Tests\Fixture\Source\UsageDep::staticCount', $relations);
    }

    public function testProcessRecordsNothingForSourcesWithNoSuchRelation(): void
    {
        $graph = (new PhpStanAnalyzer(collectors: [InClassMethodCollector::class]))->analyze(dirname(__DIR__, 5).'/Fixture/Source/ClassDependency.php');
        $relations = array_map(static fn (Edge $edge): string => $edge->from()->toString().' -['.$edge->kind()->value.']-> '.$edge->to()->toString(), $graph->forwardEdges());

        self::assertNotContains('Tests\Fixture\Source\UsageProcessorFixture::testMethod -[static-property-access]-> Tests\Fixture\Source\UsageDep::staticCount', $relations);
    }
}
