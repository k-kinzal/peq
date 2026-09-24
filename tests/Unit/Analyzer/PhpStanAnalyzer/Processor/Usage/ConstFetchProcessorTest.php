<?php

declare(strict_types=1);

namespace Tests\Unit\Analyzer\PhpStanAnalyzer\Processor\Usage;

use App\Analyzer\Graph\Edge;
use App\Analyzer\PhpStanAnalyzer\Collector\InClassMethodCollector;
use App\Analyzer\PhpStanAnalyzer\PhpStanAnalyzer;
use App\Analyzer\PhpStanAnalyzer\Processor\Usage\ConstFetchProcessor;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(ConstFetchProcessor::class)]
#[Medium]
final class ConstFetchProcessorTest extends TestCase
{
    public function testProcessRecordsAClassConstantBeingRead(): void
    {
        $graph = (new PhpStanAnalyzer(collectors: [InClassMethodCollector::class]))->analyze(dirname(__DIR__, 5).'/Fixture/Source/MethodBody.php');
        $relations = array_map(static fn (Edge $edge): string => $edge->from()->toString().' -['.$edge->kind()->value.']-> '.$edge->to()->toString(), $graph->forwardEdges());

        self::assertContains('Tests\Fixture\Source\MethodBodyClass::testMethod -[const-fetch]-> DateTime::ATOM', $relations);
    }

    public function testProcessRecordsNothingForSourcesWithNoSuchRelation(): void
    {
        $graph = (new PhpStanAnalyzer(collectors: [InClassMethodCollector::class]))->analyze(dirname(__DIR__, 5).'/Fixture/Source/ClassDependency.php');
        $relations = array_map(static fn (Edge $edge): string => $edge->from()->toString().' -['.$edge->kind()->value.']-> '.$edge->to()->toString(), $graph->forwardEdges());

        self::assertNotContains('Tests\Fixture\Source\MethodBodyClass::testMethod -[const-fetch]-> DateTime::ATOM', $relations);
    }
}
