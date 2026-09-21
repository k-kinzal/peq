<?php

declare(strict_types=1);

namespace Tests\Unit\Analyzer\ExperimentAnalyzer;

use App\Analyzer\ExperimentAnalyzer\ExperimentAnalyzer;
use App\Analyzer\Graph\EdgeKind;
use App\Analyzer\Graph\NodeKind;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(ExperimentAnalyzer::class)]
#[Medium]
final class ExperimentAnalyzerTest extends TestCase
{
    public function testInspectPreservesByReferenceWritesOfWrittenMethods(): void
    {
        $graph = (new ExperimentAnalyzer())->inspect(dirname(__DIR__, 3).'/Fixture/Experimental', 'Tests\Fixture\Experimental\Flow::referenced');
        $reads = array_values(array_filter($graph->nodes, static fn (\App\Analyzer\ExperimentAnalyzer\DataFlow\Occurrence $node): bool => $node->line === 31 && $node->kind === 'read'));
        self::assertCount(1, $reads);
        $definitions = array_values(array_filter($graph->edges, static fn (\App\Analyzer\ExperimentAnalyzer\DataFlow\Dependency $edge): bool => $edge->from === $reads[0]->id && $edge->kind === 'reaching-definition'));
        self::assertCount(1, $definitions);
        self::assertSame('call-write', $graph->nodes[$definitions[0]->to]->kind);
        self::assertSame(29, $graph->nodes[$definitions[0]->to]->line);
    }

    public function testAnalyzeReadsTheSymbolsDeclaredUnderThePath(): void
    {
        $graph = (new ExperimentAnalyzer())->analyze(dirname(__DIR__, 3).'/Fixture/Sample');

        self::assertNotNull($graph->nodeNamed('Tests\Fixture\Sample\ComplexClass'));
    }

    #[DataProvider('providerSymbolsTheSampleDeclares')]
    public function testAnalyzeRecordsEverySymbolTheSourcesDeclare(string $name, NodeKind $kind): void
    {
        $graph = (new ExperimentAnalyzer())->analyze(dirname(__DIR__, 3).'/Fixture/Sample');

        self::assertSame($kind, $graph->nodeNamed($name)?->kind());
    }

    /**
     * @return iterable<string, array{string, NodeKind}>
     */
    public static function providerSymbolsTheSampleDeclares(): iterable
    {
        yield 'a class' => ['Tests\Fixture\Sample\ComplexClass', NodeKind::Klass];

        yield 'an interface' => ['Tests\Fixture\Sample\MyInterface', NodeKind::Interface];

        yield 'a trait' => ['Tests\Fixture\Sample\MyTrait', NodeKind::Trait];

        yield 'a method' => ['Tests\Fixture\Sample\ComplexClass::complexMethod', NodeKind::Method];

        yield 'an enum' => ['Tests\Fixture\Sample\MyEnum', NodeKind::Enum];

        yield 'an enum case' => ['Tests\Fixture\Sample\MyEnum::A', NodeKind::EnumCase];
    }

    #[DataProvider('providerRelationsTheSampleWrites')]
    public function testAnalyzeRecordsEveryRelationTheSourcesWrite(string $from, string $to, EdgeKind $kind): void
    {
        $graph = (new ExperimentAnalyzer())->analyze(dirname(__DIR__, 3).'/Fixture/Sample');
        $node = $graph->nodeNamed($from);
        self::assertNotNull($node);
        $target = $graph->nodeNamed($to);
        self::assertNotNull($target);

        self::assertSame($kind, $graph->edge($node->id(), $target->id(), $kind)?->kind());
    }

    /**
     * @return iterable<string, array{string, string, EdgeKind}>
     */
    public static function providerRelationsTheSampleWrites(): iterable
    {
        yield 'a class declares its methods' => ['Tests\Fixture\Sample\ComplexClass', 'Tests\Fixture\Sample\ComplexClass::complexMethod', EdgeKind::DeclarationMethod];

        yield 'an enum declares its cases' => ['Tests\Fixture\Sample\MyEnum', 'Tests\Fixture\Sample\MyEnum::A', EdgeKind::DeclarationEnumCase];

        yield 'a method is declared in its class' => ['Tests\Fixture\Sample\ComplexClass::complexMethod', 'Tests\Fixture\Sample\ComplexClass', EdgeKind::DeclaredIn];
    }

    public function testAnalyzeOfAPathWithNoPhpFileDescribesAnEmptyGraph(): void
    {
        self::assertSame([], (new ExperimentAnalyzer())->analyze(dirname(__DIR__, 4).'/build')->nodes());
    }

    public function testAnalyzeOfAPathThatDoesNotExistDescribesAnEmptyGraph(): void
    {
        self::assertSame([], (new ExperimentAnalyzer())->analyze('/nowhere/at/all')->nodes());
    }

    public function testAnalyzeReadsOnlyTheFilesTheIncludePatternsSelect(): void
    {
        $graph = (new ExperimentAnalyzer(includes: ['Analyzer']))->analyze(dirname(__DIR__, 3).'/Fixture');

        self::assertNull($graph->nodeNamed('Tests\Fixture\Sample\ComplexClass'));
    }

    public function testAnalyzeLeavesOutTheFilesTheExcludePatternsFilter(): void
    {
        $graph = (new ExperimentAnalyzer(excludes: ['Sample']))->analyze(dirname(__DIR__, 3).'/Fixture');

        self::assertNull($graph->nodeNamed('Tests\Fixture\Sample\ComplexClass'));
    }

    public function testAnalyzeReadsASingleFileAsWellAsADirectory(): void
    {
        $graph = (new ExperimentAnalyzer())->analyze(dirname(__DIR__, 3).'/Fixture/Sample/ComplexSample.php');

        self::assertNotNull($graph->nodeNamed('Tests\Fixture\Sample\ComplexClass'));
    }
}
