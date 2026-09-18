<?php

declare(strict_types=1);

namespace Tests\Unit\Analyzer\PhpStanAnalyzer\Processor\Declaration;

use App\Analyzer\Graph\Edge;
use App\Analyzer\Graph\Node;
use App\Analyzer\Graph\NodeId\ClassNodeId;
use App\Analyzer\Graph\NodeId\InterfaceNodeId;
use App\Analyzer\Graph\NodeKind;
use App\Analyzer\PhpStanAnalyzer\Collector\DependencyCollector;
use App\Analyzer\PhpStanAnalyzer\PhpStanAnalyzer;
use App\Analyzer\PhpStanAnalyzer\Processor\Declaration\ClassLikeProcessor;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(ClassLikeProcessor::class)]
#[Medium]
final class ClassLikeProcessorTest extends TestCase
{
    public function testProcessRecordsWhatAClassLikeIsBuiltFrom(): void
    {
        $graph = (new PhpStanAnalyzer(collectors: [DependencyCollector::class]))->analyze(dirname(__DIR__, 5).'/Fixture/Source/Comprehensive.php');
        $relations = array_map(static fn (Edge $edge): string => $edge->from()->toString().' -['.$edge->kind()->value.']-> '.$edge->to()->toString(), $graph->authoredEdges());

        self::assertContains('Tests\Fixture\Source\ComprehensiveClass -[declaration-implements]-> Tests\Fixture\Source\MyInterface', $relations);
    }

    public function testDeclaredNodeBuildsTheNodeForTheDeclarationItself(): void
    {
        $graph = (new PhpStanAnalyzer(collectors: [DependencyCollector::class]))->analyze(dirname(__DIR__, 5).'/Fixture/Source/Comprehensive.php');

        self::assertContains('Tests\Fixture\Source\MyTrait', array_map(static fn (Node $node): string => $node->id()->toString(), $graph->nodes()));
    }

    public function testInheritanceRecordsOnlyWhatTheKindOfDeclarationCanTakeOn(): void
    {
        $graph = (new PhpStanAnalyzer(collectors: [DependencyCollector::class]))->analyze(dirname(__DIR__, 5).'/Fixture/Source/Comprehensive.php');
        $relations = array_map(static fn (Edge $edge): string => $edge->from()->toString().' -['.$edge->kind()->value.']-> '.$edge->to()->toString(), $graph->authoredEdges());

        self::assertNotContains(
            'Tests\Fixture\Source\MyInterface -[declaration-trait-use]-> Tests\Fixture\Source\MyTrait',
            $relations,
        );
    }

    public function testProcessRecordsNothingForSourcesWithNoSuchRelation(): void
    {
        $graph = (new PhpStanAnalyzer(collectors: [DependencyCollector::class]))->analyze(dirname(__DIR__, 5).'/Fixture/Source/ClassDependency.php');
        $relations = array_map(static fn (Edge $edge): string => $edge->from()->toString().' -['.$edge->kind()->value.']-> '.$edge->to()->toString(), $graph->authoredEdges());

        self::assertNotContains('Tests\Fixture\Source\ComprehensiveClass -[declaration-implements]-> Tests\Fixture\Source\MyInterface', $relations);
    }

    public function testProcessRecordsTheDeclarationItselfAsAnalysed(): void
    {
        $graph = (new PhpStanAnalyzer(collectors: [DependencyCollector::class]))->analyze(dirname(__DIR__, 5).'/Fixture/Source/Comprehensive.php');
        $declared = $graph->nodeNamed('Tests\Fixture\Source\ComprehensiveClass');

        self::assertNotNull($declared);
        self::assertTrue($declared->resolved());
        self::assertSame(NodeKind::Klass, $declared->kind());
        self::assertSame(21, $declared->meta()?->line);
    }

    public function testProcessRecordsWhereADeclarationIsWritten(): void
    {
        $graph = (new PhpStanAnalyzer(collectors: [DependencyCollector::class]))->analyze(dirname(__DIR__, 5).'/Fixture/Source/Comprehensive.php');
        $implements = $graph->edge(ClassNodeId::of('Tests\Fixture\Source\ComprehensiveClass'), InterfaceNodeId::of('Tests\Fixture\Source\MyInterface'));

        self::assertNotNull($implements);
        self::assertSame(21, $implements->meta()->line);
        self::assertSame(1, $implements->meta()->column);
    }
}
