<?php

declare(strict_types=1);

namespace Tests\Unit\Analyzer\PhpStanAnalyzer\Processor\Declaration;

use App\Analyzer\Graph\Edge;
use App\Analyzer\Graph\NodeId\ClassNodeId;
use App\Analyzer\Graph\NodeId\PropertyNodeId;
use App\Analyzer\PhpStanAnalyzer\Collector\DependencyCollector;
use App\Analyzer\PhpStanAnalyzer\PhpStanAnalyzer;
use App\Analyzer\PhpStanAnalyzer\Processor\Declaration\PromotedPropertyProcessor;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(PromotedPropertyProcessor::class)]
#[Medium]
final class PromotedPropertyProcessorTest extends TestCase
{
    public function testProcessRecordsAPropertyPromotedFromAConstructorParameter(): void
    {
        $graph = (new PhpStanAnalyzer(collectors: [DependencyCollector::class]))->analyze(dirname(__DIR__, 5).'/Fixture/Source/Comprehensive.php');
        $relations = array_map(static fn (Edge $edge): string => $edge->from()->toString().' -['.$edge->kind()->value.']-> '.$edge->to()->toString(), $graph->forwardEdges());

        self::assertContains('Tests\Fixture\Source\ComprehensiveClass -[declaration-property]-> Tests\Fixture\Source\ComprehensiveClass::promotedProp', $relations);
    }

    public function testProcessRecordsNothingForSourcesWithNoSuchRelation(): void
    {
        $graph = (new PhpStanAnalyzer(collectors: [DependencyCollector::class]))->analyze(dirname(__DIR__, 5).'/Fixture/Source/ClassDependency.php');
        $relations = array_map(static fn (Edge $edge): string => $edge->from()->toString().' -['.$edge->kind()->value.']-> '.$edge->to()->toString(), $graph->forwardEdges());

        self::assertNotContains('Tests\Fixture\Source\ComprehensiveClass -[declaration-property]-> Tests\Fixture\Source\ComprehensiveClass::promotedProp', $relations);
    }

    public function testProcessRecordsTheTypeAPromotedPropertyIsDeclaredWith(): void
    {
        $file = sys_get_temp_dir().'/'.uniqid('peq-snippet-', true).'.php';
        file_put_contents($file, implode(PHP_EOL, ['<?php', 'namespace Tests\Contract\Analyzer\Properties;', '', 'class Subject', '{', '    public function __construct(public readonly \DateTimeImmutable $when) {}', '}', '']));
        $graph = (new PhpStanAnalyzer(collectors: [DependencyCollector::class]))->analyze($file);
        unlink($file);
        $relations = array_map(static fn (Edge $edge): string => $edge->from()->toString().' -['.$edge->kind()->value.']-> '.$edge->to()->toString(), $graph->forwardEdges());

        self::assertContains('Tests\Contract\Analyzer\Properties\Subject::when -[declaration-type-property]-> DateTimeImmutable', $relations);
    }

    public function testProcessRecordsNothingForAParameterThatPromotesNothing(): void
    {
        $file = sys_get_temp_dir().'/'.uniqid('peq-snippet-', true).'.php';
        file_put_contents($file, implode(PHP_EOL, ['<?php', 'namespace Tests\Contract\Analyzer\Properties;', '', 'class Subject', '{', '    public function __construct(\DateTimeImmutable $when) {}', '}', '']));
        $graph = (new PhpStanAnalyzer(collectors: [DependencyCollector::class]))->analyze($file);
        unlink($file);

        self::assertNull($graph->nodeNamed('Tests\Contract\Analyzer\Properties\Subject::when'));
    }

    public function testProcessRecordsWhereAPromotedPropertyIsDeclared(): void
    {
        $graph = (new PhpStanAnalyzer(collectors: [DependencyCollector::class]))->analyze(dirname(__DIR__, 5).'/Fixture/Source/Comprehensive.php');
        $declaration = $graph->edge(ClassNodeId::of('Tests\Fixture\Source\ComprehensiveClass'), PropertyNodeId::of('Tests\Fixture\Source\ComprehensiveClass', 'promotedProp'));

        self::assertNotNull($declaration);
        self::assertSame(33, $declaration->meta()->line);
        self::assertSame(1, $declaration->meta()->column);
    }
}
