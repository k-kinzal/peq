<?php

declare(strict_types=1);

namespace Tests\Unit\Analyzer\PhpStanAnalyzer\Processor\Declaration;

use App\Analyzer\Graph\Edge;
use App\Analyzer\Graph\NodeId\EnumCaseNodeId;
use App\Analyzer\Graph\NodeId\EnumNodeId;
use App\Analyzer\Graph\NodeKind;
use App\Analyzer\PhpStanAnalyzer\Collector\DependencyCollector;
use App\Analyzer\PhpStanAnalyzer\PhpStanAnalyzer;
use App\Analyzer\PhpStanAnalyzer\Processor\Declaration\EnumCaseProcessor;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(EnumCaseProcessor::class)]
#[Medium]
final class EnumCaseProcessorTest extends TestCase
{
    public function testProcessRecordsACaseAnEnumDeclares(): void
    {
        $graph = (new PhpStanAnalyzer(collectors: [DependencyCollector::class]))->analyze(dirname(__DIR__, 5).'/Fixture/Source/Comprehensive.php');
        $relations = array_map(static fn (Edge $edge): string => $edge->from()->toString().' -['.$edge->kind()->value.']-> '.$edge->to()->toString(), $graph->authoredEdges());

        self::assertContains('Tests\Fixture\Source\MyEnum -[declaration-enum-case]-> Tests\Fixture\Source\MyEnum::A', $relations);
    }

    public function testProcessRecordsNothingForSourcesWithNoSuchRelation(): void
    {
        $graph = (new PhpStanAnalyzer(collectors: [DependencyCollector::class]))->analyze(dirname(__DIR__, 5).'/Fixture/Source/ClassDependency.php');
        $relations = array_map(static fn (Edge $edge): string => $edge->from()->toString().' -['.$edge->kind()->value.']-> '.$edge->to()->toString(), $graph->authoredEdges());

        self::assertNotContains('Tests\Fixture\Source\MyEnum -[declaration-enum-case]-> Tests\Fixture\Source\MyEnum::A', $relations);
    }

    public function testProcessRecordsTheCaseItselfAsAnalysed(): void
    {
        $graph = (new PhpStanAnalyzer(collectors: [DependencyCollector::class]))->analyze(dirname(__DIR__, 5).'/Fixture/Source/Comprehensive.php');
        $declared = $graph->nodeNamed('Tests\Fixture\Source\MyEnum::A');

        self::assertNotNull($declared);
        self::assertTrue($declared->resolved());
        self::assertSame(NodeKind::EnumCase, $declared->kind());
        self::assertSame(18, $declared->meta()?->line);
    }

    public function testProcessRecordsWhereACaseIsDeclared(): void
    {
        $graph = (new PhpStanAnalyzer(collectors: [DependencyCollector::class]))->analyze(dirname(__DIR__, 5).'/Fixture/Source/Comprehensive.php');
        $declaration = $graph->edge(EnumNodeId::of('Tests\Fixture\Source\MyEnum'), EnumCaseNodeId::of('Tests\Fixture\Source\MyEnum', 'A'));

        self::assertNotNull($declaration);
        self::assertSame(18, $declaration->meta()->line);
        self::assertSame(1, $declaration->meta()->column);
    }
}
