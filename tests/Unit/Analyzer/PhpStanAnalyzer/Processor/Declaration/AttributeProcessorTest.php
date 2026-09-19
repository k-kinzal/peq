<?php

declare(strict_types=1);

namespace Tests\Unit\Analyzer\PhpStanAnalyzer\Processor\Declaration;

use App\Analyzer\Graph\Edge;
use App\Analyzer\Graph\NodeId\ClassNodeId;
use App\Analyzer\PhpStanAnalyzer\Collector\DependencyCollector;
use App\Analyzer\PhpStanAnalyzer\PhpStanAnalyzer;
use App\Analyzer\PhpStanAnalyzer\Processor\Declaration\AttributeProcessor;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(AttributeProcessor::class)]
#[Medium]
final class AttributeProcessorTest extends TestCase
{
    public function testProcessRecordsAnAttributeWrittenOnADeclaration(): void
    {
        $graph = (new PhpStanAnalyzer(collectors: [DependencyCollector::class]))->analyze(dirname(__DIR__, 5).'/Fixture/Source/Comprehensive.php');
        $relations = array_map(static fn (Edge $edge): string => $edge->from()->toString().' -['.$edge->kind()->value.']-> '.$edge->to()->toString(), $graph->authoredEdges());

        self::assertContains('Tests\Fixture\Source\ComprehensiveClass -[attribute]-> Tests\Fixture\Source\MyAttribute', $relations);
    }

    public function testProcessRecordsNothingForSourcesWithNoSuchRelation(): void
    {
        $graph = (new PhpStanAnalyzer(collectors: [DependencyCollector::class]))->analyze(dirname(__DIR__, 5).'/Fixture/Source/ClassDependency.php');
        $relations = array_map(static fn (Edge $edge): string => $edge->from()->toString().' -['.$edge->kind()->value.']-> '.$edge->to()->toString(), $graph->authoredEdges());

        self::assertNotContains('Tests\Fixture\Source\ComprehensiveClass -[attribute]-> Tests\Fixture\Source\MyAttribute', $relations);
    }

    public function testProcessRecordsWhereAnAttributeIsWritten(): void
    {
        $graph = (new PhpStanAnalyzer(collectors: [DependencyCollector::class]))->analyze(dirname(__DIR__, 5).'/Fixture/Source/Comprehensive.php');
        $attribute = $graph->edge(ClassNodeId::of('Tests\Fixture\Source\ComprehensiveClass'), ClassNodeId::of('Tests\Fixture\Source\MyAttribute'));

        self::assertNotNull($attribute);
        self::assertSame(21, $attribute->meta()->line);
        self::assertSame(1, $attribute->meta()->column);
    }

    public function testUsagesRecordsAnAttributeOnTheDeclarationThatCarriesIt(): void
    {
        $graph = (new PhpStanAnalyzer(collectors: [DependencyCollector::class]))->analyze(dirname(__DIR__, 5).'/Fixture/Source/Comprehensive.php');
        $declared = $graph->nodeNamed('Tests\Fixture\Source\ComprehensiveClass')?->declaration();

        self::assertNotNull($declared);
        self::assertSame(['Tests\Fixture\Source\MyAttribute'], $declared->attributeNames());
    }

    public function testUsagesRecordsNothingOnADeclarationThatCarriesNoAttribute(): void
    {
        $graph = (new PhpStanAnalyzer(collectors: [DependencyCollector::class]))->analyze(dirname(__DIR__, 5).'/Fixture/Source/Comprehensive.php');
        $declared = $graph->nodeNamed('Tests\Fixture\Source\MyInterface')?->declaration();

        self::assertNotNull($declared);
        self::assertSame([], $declared->attributeNames());
    }

    public function testProcessRecordsEveryAttributeWrittenOnADeclaration(): void
    {
        $file = sys_get_temp_dir().'/'.uniqid('peq-snippet-', true).'.php';
        file_put_contents($file, implode(PHP_EOL, ['<?php', 'namespace Tests\Contract\Analyzer\Attributes;', '', '#[\Attribute]', 'class First {}', '', '#[\Attribute]', 'class Second {}', '', '#[First]', '#[Second]', 'class Subject {}', '']));
        $graph = (new PhpStanAnalyzer(collectors: [DependencyCollector::class]))->analyze($file);
        unlink($file);
        $relations = array_map(static fn (Edge $edge): string => $edge->from()->toString().' -['.$edge->kind()->value.']-> '.$edge->to()->toString(), $graph->authoredEdges());

        self::assertContains('Tests\Contract\Analyzer\Attributes\Subject -[attribute]-> Tests\Contract\Analyzer\Attributes\First', $relations);
        self::assertContains('Tests\Contract\Analyzer\Attributes\Subject -[attribute]-> Tests\Contract\Analyzer\Attributes\Second', $relations);
    }
}
