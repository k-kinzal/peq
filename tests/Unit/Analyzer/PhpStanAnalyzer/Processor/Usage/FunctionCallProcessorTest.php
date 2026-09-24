<?php

declare(strict_types=1);

namespace Tests\Unit\Analyzer\PhpStanAnalyzer\Processor\Usage;

use App\Analyzer\Graph\Edge;
use App\Analyzer\Graph\NodeId\FunctionNodeId;
use App\Analyzer\Graph\NodeId\MethodNodeId;
use App\Analyzer\PhpStanAnalyzer\Collector\DependencyCollector;
use App\Analyzer\PhpStanAnalyzer\Collector\InClassMethodCollector;
use App\Analyzer\PhpStanAnalyzer\PhpStanAnalyzer;
use App\Analyzer\PhpStanAnalyzer\Processor\Usage\FunctionCallProcessor;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(FunctionCallProcessor::class)]
#[Medium]
final class FunctionCallProcessorTest extends TestCase
{
    public function testProcessRecordsAFunctionBeingCalled(): void
    {
        $graph = (new PhpStanAnalyzer(collectors: [InClassMethodCollector::class]))->analyze(dirname(__DIR__, 5).'/Fixture/Source/UsageProcessors.php');
        $relations = array_map(static fn (Edge $edge): string => $edge->from()->toString().' -['.$edge->kind()->value.']-> '.$edge->to()->toString(), $graph->forwardEdges());

        self::assertContains('Tests\Fixture\Source\UsageProcessorFixture::testMethod -[function-call]-> Tests\Fixture\Source\usage_target_func', $relations);
    }

    public function testProcessRecordsNothingForSourcesWithNoSuchRelation(): void
    {
        $graph = (new PhpStanAnalyzer(collectors: [InClassMethodCollector::class]))->analyze(dirname(__DIR__, 5).'/Fixture/Source/ClassDependency.php');
        $relations = array_map(static fn (Edge $edge): string => $edge->from()->toString().' -['.$edge->kind()->value.']-> '.$edge->to()->toString(), $graph->forwardEdges());

        self::assertNotContains('Tests\Fixture\Source\UsageProcessorFixture::testMethod -[function-call]-> Tests\Fixture\Source\usage_target_func', $relations);
    }

    public function testProcessRecordsACalledFunctionItCannotSeeAsUnresolved(): void
    {
        $file = sys_get_temp_dir().'/'.uniqid('peq-snippet-', true).'.php';
        file_put_contents($file, "<?php\nnamespace Tests\\Contract\\Analyzer\\Calls;\n\nclass Subject\n{\n    public function testMethod(): void\n    {\n        \\Elsewhere\\helper();\n    }\n}\n");
        $graph = (new PhpStanAnalyzer(collectors: [InClassMethodCollector::class]))->analyze($file);
        unlink($file);
        $called = $graph->nodeNamed('Elsewhere\helper');

        self::assertNotNull($called);
        self::assertFalse($called->resolved());
    }

    public function testProcessRecordsACallWrittenInsideAFunction(): void
    {
        $file = sys_get_temp_dir().'/'.uniqid('peq-snippet-', true).'.php';
        file_put_contents($file, "<?php\nnamespace Tests\\Contract\\Analyzer\\Calls;\n\nfunction caller(): void\n{\n    \\Elsewhere\\helper();\n}\n");
        $graph = (new PhpStanAnalyzer(collectors: [DependencyCollector::class]))->analyze($file);
        unlink($file);

        self::assertNotNull($graph->edge(FunctionNodeId::of('Tests\Contract\Analyzer\Calls\caller'), FunctionNodeId::of('Elsewhere\helper')));
    }

    public function testProcessRecordsNothingForACallWrittenOutsideAnyDeclaration(): void
    {
        $file = sys_get_temp_dir().'/'.uniqid('peq-snippet-', true).'.php';
        file_put_contents($file, "<?php\nnamespace Tests\\Contract\\Analyzer\\Calls;\n\n\\Elsewhere\\helper();\n");
        $graph = (new PhpStanAnalyzer(collectors: [DependencyCollector::class]))->analyze($file);
        unlink($file);

        self::assertNull($graph->nodeNamed('Elsewhere\helper'));
    }

    public function testProcessRecordsWhereTheCallIsWritten(): void
    {
        $file = sys_get_temp_dir().'/'.uniqid('peq-snippet-', true).'.php';
        file_put_contents($file, "<?php\nnamespace Tests\\Contract\\Analyzer\\Calls;\n\nclass Subject\n{\n    public function testMethod(): void\n    {\n        \\Elsewhere\\helper();\n    }\n}\n");
        $path = realpath($file);
        $graph = (new PhpStanAnalyzer(collectors: [InClassMethodCollector::class]))->analyze($file);
        unlink($file);
        $call = $graph->edge(MethodNodeId::of('Tests\Contract\Analyzer\Calls\Subject', 'testMethod'), FunctionNodeId::of('Elsewhere\helper'));

        self::assertNotNull($call);
        self::assertSame($path, $call->meta()->path);
        self::assertSame(8, $call->meta()->line);
        self::assertSame(1, $call->meta()->column);
    }
}
