<?php

declare(strict_types=1);

namespace Tests\Unit\Analyzer\PhpStanAnalyzer;

use App\Analyzer\Graph\Edge;
use App\Analyzer\Graph\Node;
use App\Analyzer\Graph\NodeKind;
use App\Analyzer\PhpStanAnalyzer\Collector\DependencyCollector;
use App\Analyzer\PhpStanAnalyzer\Collector\InClassMethodCollector;
use App\Analyzer\PhpStanAnalyzer\PhpStanAnalyzer;
use App\Analyzer\PhpStanAnalyzer\SourceResolver;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(SourceResolver::class)]
#[Medium]
final class SourceResolverTest extends TestCase
{
    public function testResolveAttributesARelationInsideAMethodToThatMethod(): void
    {
        $graph = (new PhpStanAnalyzer(collectors: [InClassMethodCollector::class]))->analyze(dirname(__DIR__, 3).'/Fixture/Source/MethodBody.php');
        $relations = array_map(static fn (Edge $edge): string => $edge->from()->toString().' -['.$edge->kind()->value.']-> '.$edge->to()->toString(), $graph->forwardEdges());

        self::assertContains(
            'Tests\Fixture\Source\MethodBodyClass::testMethod -[instantiation]-> stdClass',
            $relations,
        );
    }

    public function testResolveAttributesARelationInsideAFunctionToThatFunction(): void
    {
        $graph = (new PhpStanAnalyzer(collectors: [DependencyCollector::class]))->analyze(dirname(__DIR__, 3).'/Fixture/Source/UsageProcessors.php');

        self::assertContains('Tests\Fixture\Source\usage_target_func', array_map(static fn (Node $node): string => $node->id()->toString(), $graph->nodes()));
    }

    public function testResolveNamesTheDeclaringSymbolRatherThanTheFile(): void
    {
        $graph = (new PhpStanAnalyzer(collectors: [InClassMethodCollector::class]))->analyze(dirname(__DIR__, 3).'/Fixture/Source/UsageProcessors.php');
        $relations = array_map(static fn (Edge $edge): string => $edge->from()->toString().' -['.$edge->kind()->value.']-> '.$edge->to()->toString(), $graph->forwardEdges());

        self::assertNotSame([], $relations);
        self::assertSame($relations, array_values(array_filter($relations, static fn (string $relation): bool => str_starts_with($relation, 'Tests\Fixture\Source\UsageProcessorFixture::'))));
    }

    public function testResolveProducesANodeThatReportsItselfAsAnalysed(): void
    {
        $graph = (new PhpStanAnalyzer(collectors: [DependencyCollector::class]))->analyze(dirname(__DIR__, 3).'/Fixture/Source/ClassDependency.php');

        $node = $graph->nodeNamed('Tests\Fixture\Source\ClassDependency');

        self::assertNotNull($node);
        self::assertSame(NodeKind::Klass, $node->kind());
        self::assertTrue($node->resolved());
    }
}
