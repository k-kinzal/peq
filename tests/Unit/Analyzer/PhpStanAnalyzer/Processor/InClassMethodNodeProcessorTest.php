<?php

declare(strict_types=1);

namespace Tests\Unit\Analyzer\PhpStanAnalyzer\Processor;

use App\Analyzer\Graph\Edge;
use App\Analyzer\PhpStanAnalyzer\Collector\InClassMethodCollector;
use App\Analyzer\PhpStanAnalyzer\PhpStanAnalyzer;
use App\Analyzer\PhpStanAnalyzer\Processor\InClassMethodNodeProcessor;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Name;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(InClassMethodNodeProcessor::class)]
#[Medium]
final class InClassMethodNodeProcessorTest extends TestCase
{
    public function testProcessRecordsWhatAMethodBodyReaches(): void
    {
        $graph = (new PhpStanAnalyzer(collectors: [InClassMethodCollector::class]))->analyze(dirname(__DIR__, 4).'/Fixture/Source/MethodBody.php');
        $relations = array_map(static fn (Edge $edge): string => $edge->from()->toString().' -['.$edge->kind()->value.']-> '.$edge->to()->toString(), $graph->authoredEdges());

        self::assertContains('Tests\Fixture\Source\MethodBodyClass::testMethod -[instantiation]-> stdClass', $relations);
    }

    public function testHandlesSelectsTheExpressionsDispatchHasAnArmFor(): void
    {
        self::assertTrue(InClassMethodNodeProcessor::handles(new New_(new Name('stdClass'))));
        self::assertFalse(InClassMethodNodeProcessor::handles(new Variable('unrelated')));
    }

    public function testDispatchReportsNothingForAnExpressionWithNoArm(): void
    {
        $graph = (new PhpStanAnalyzer(collectors: [InClassMethodCollector::class]))->analyze(dirname(__DIR__, 4).'/Fixture/Source/ClassDependency.php');

        self::assertSame([], $graph->nodes());
    }

    public function testProcessRecordsNothingForSourcesWithNoSuchRelation(): void
    {
        $graph = (new PhpStanAnalyzer(collectors: [InClassMethodCollector::class]))->analyze(dirname(__DIR__, 4).'/Fixture/Source/ClassDependency.php');
        $relations = array_map(static fn (Edge $edge): string => $edge->from()->toString().' -['.$edge->kind()->value.']-> '.$edge->to()->toString(), $graph->authoredEdges());

        self::assertNotContains('Tests\Fixture\Source\MethodBodyClass::testMethod -[instantiation]-> stdClass', $relations);
    }
}
