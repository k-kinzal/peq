<?php

declare(strict_types=1);

namespace Tests\Unit\Analyzer\PhpStanAnalyzer\Processor\Declaration;

use App\Analyzer\Graph\Edge;
use App\Analyzer\Graph\NodeId\ClassNodeId;
use App\Analyzer\Graph\NodeId\ConstantNodeId;
use App\Analyzer\PhpStanAnalyzer\Collector\DependencyCollector;
use App\Analyzer\PhpStanAnalyzer\PhpStanAnalyzer;
use App\Analyzer\PhpStanAnalyzer\Processor\Declaration\ClassConstProcessor;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(ClassConstProcessor::class)]
#[Medium]
final class ClassConstProcessorTest extends TestCase
{
    public function testProcessRecordsAConstantAClassDeclares(): void
    {
        $graph = (new PhpStanAnalyzer(collectors: [DependencyCollector::class]))->analyze(dirname(__DIR__, 5).'/Fixture/Source/Comprehensive.php');
        $relations = array_map(static fn (Edge $edge): string => $edge->from()->toString().' -['.$edge->kind()->value.']-> '.$edge->to()->toString(), $graph->authoredEdges());

        self::assertContains('Tests\Fixture\Source\ComprehensiveClass -[declaration-constant]-> Tests\Fixture\Source\ComprehensiveClass::MY_CONST', $relations);
    }

    public function testProcessRecordsNothingForSourcesWithNoSuchRelation(): void
    {
        $graph = (new PhpStanAnalyzer(collectors: [DependencyCollector::class]))->analyze(dirname(__DIR__, 5).'/Fixture/Source/ClassDependency.php');
        $relations = array_map(static fn (Edge $edge): string => $edge->from()->toString().' -['.$edge->kind()->value.']-> '.$edge->to()->toString(), $graph->authoredEdges());

        self::assertNotContains('Tests\Fixture\Source\ComprehensiveClass -[declaration-constant]-> Tests\Fixture\Source\ComprehensiveClass::MY_CONST', $relations);
    }

    public function testProcessRecordsWhereAConstantIsDeclared(): void
    {
        $graph = (new PhpStanAnalyzer(collectors: [DependencyCollector::class]))->analyze(dirname(__DIR__, 5).'/Fixture/Source/Comprehensive.php');
        $declaration = $graph->edge(ClassNodeId::of('Tests\Fixture\Source\ComprehensiveClass'), ConstantNodeId::of('Tests\Fixture\Source\ComprehensiveClass', 'MY_CONST'));

        self::assertNotNull($declaration);
        self::assertSame(26, $declaration->meta()->line);
        self::assertSame(1, $declaration->meta()->column);
    }
}
