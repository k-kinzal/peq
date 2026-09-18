<?php

declare(strict_types=1);

namespace Tests\Unit\Analyzer\PhpStanAnalyzer\Processor\Declaration;

use App\Analyzer\Graph\Edge;
use App\Analyzer\Graph\NodeId\ClassNodeId;
use App\Analyzer\Graph\NodeId\MethodNodeId;
use App\Analyzer\Graph\NodeKind;
use App\Analyzer\PhpStanAnalyzer\Collector\DependencyCollector;
use App\Analyzer\PhpStanAnalyzer\PhpStanAnalyzer;
use App\Analyzer\PhpStanAnalyzer\Processor\Declaration\FunctionLikeProcessor;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(FunctionLikeProcessor::class)]
#[Medium]
final class FunctionLikeProcessorTest extends TestCase
{
    public function testProcessRecordsAMethodAClassDeclares(): void
    {
        $graph = (new PhpStanAnalyzer(collectors: [DependencyCollector::class]))->analyze(dirname(__DIR__, 5).'/Fixture/Source/Comprehensive.php');
        $relations = array_map(static fn (Edge $edge): string => $edge->from()->toString().' -['.$edge->kind()->value.']-> '.$edge->to()->toString(), $graph->authoredEdges());

        self::assertContains('Tests\Fixture\Source\ComprehensiveClass -[declaration-method]-> Tests\Fixture\Source\ComprehensiveClass::myMethod', $relations);
    }

    public function testDeclaringNodeNamesTheKindOfClassLikeAMethodIsDeclaredIn(): void
    {
        $graph = (new PhpStanAnalyzer(collectors: [DependencyCollector::class]))->analyze(dirname(__DIR__, 5).'/Fixture/Source/Comprehensive.php');
        $relations = array_map(static fn (Edge $edge): string => $edge->from()->toString().' -['.$edge->kind()->value.']-> '.$edge->to()->toString(), $graph->authoredEdges());

        self::assertContains(
            'Tests\Fixture\Source\ComprehensiveClass -[declaration-method]-> Tests\Fixture\Source\ComprehensiveClass::myMethod',
            $relations,
        );
    }

    public function testSignatureRecordsTheTypesADeclarationCommitsTo(): void
    {
        $graph = (new PhpStanAnalyzer(collectors: [DependencyCollector::class]))->analyze(dirname(__DIR__, 5).'/Fixture/Source/Guards.php');
        $relations = array_map(static fn (Edge $edge): string => $edge->from()->toString().' -['.$edge->kind()->value.']-> '.$edge->to()->toString(), $graph->authoredEdges());

        self::assertContains(
            'Tests\Fixture\Source\GuardedClass::guardedBy -[declaration-type-parameter]-> Tests\Fixture\Source\Guard',
            $relations,
        );
    }

    public function testProcessRecordsNothingForSourcesWithNoSuchRelation(): void
    {
        $graph = (new PhpStanAnalyzer(collectors: [DependencyCollector::class]))->analyze(dirname(__DIR__, 5).'/Fixture/Source/ClassDependency.php');
        $relations = array_map(static fn (Edge $edge): string => $edge->from()->toString().' -['.$edge->kind()->value.']-> '.$edge->to()->toString(), $graph->authoredEdges());

        self::assertNotContains('Tests\Fixture\Source\ComprehensiveClass -[declaration-method]-> Tests\Fixture\Source\ComprehensiveClass::myMethod', $relations);
    }

    public function testProcessRecordsAFunctionAFileDeclaresAsAnalysed(): void
    {
        $graph = (new PhpStanAnalyzer(collectors: [DependencyCollector::class]))->analyze(dirname(__DIR__, 5).'/Fixture/Source/UsageProcessors.php');
        $function = $graph->nodeNamed('Tests\Fixture\Source\usage_target_func');

        self::assertNotNull($function);
        self::assertTrue($function->resolved());
        self::assertSame(NodeKind::Function, $function->kind());
        self::assertSame(7, $function->meta()?->line);
    }

    public function testProcessRecordsEveryTypeAFunctionCommitsTo(): void
    {
        $file = sys_get_temp_dir().'/'.uniqid('peq-snippet-', true).'.php';
        file_put_contents($file, implode(PHP_EOL, ['<?php', 'namespace Tests\Contract\Analyzer\Functions;', '', 'function build(\Countable $items): \DateTimeImmutable', '{', '    return new \DateTimeImmutable();', '}', '']));
        $graph = (new PhpStanAnalyzer(collectors: [DependencyCollector::class]))->analyze($file);
        unlink($file);
        $relations = array_map(static fn (Edge $edge): string => $edge->from()->toString().' -['.$edge->kind()->value.']-> '.$edge->to()->toString(), $graph->authoredEdges());

        self::assertContains('Tests\Contract\Analyzer\Functions\build -[declaration-type-parameter]-> Countable', $relations);
        self::assertContains('Tests\Contract\Analyzer\Functions\build -[declaration-type-return]-> DateTimeImmutable', $relations);
    }

    public function testProcessRecordsWhereAMethodIsDeclared(): void
    {
        $graph = (new PhpStanAnalyzer(collectors: [DependencyCollector::class]))->analyze(dirname(__DIR__, 5).'/Fixture/Source/Comprehensive.php');
        $declaration = $graph->edge(ClassNodeId::of('Tests\Fixture\Source\ComprehensiveClass'), MethodNodeId::of('Tests\Fixture\Source\ComprehensiveClass', 'myMethod'));

        self::assertNotNull($declaration);
        self::assertSame(37, $declaration->meta()->line);
        self::assertSame(1, $declaration->meta()->column);
    }
}
