<?php

declare(strict_types=1);

namespace Tests\Contract\Analyzer;

use App\Action\Inspect\InspectionGraph;
use App\Analyzer\Analyzer;
use App\Analyzer\Graph\Call\CallOccurrence;
use App\Analyzer\Graph\Direction;
use App\Analyzer\Graph\EdgeKind;
use App\Analyzer\NativeAnalyzer\NativeAnalyzer;
use App\Analyzer\PhpStanAnalyzer\PhpStanAnalyzer;
use App\Config\Config;
use App\Config\InspectFilter;
use App\Config\OutputFormat;
use App\Gql\Element\EdgeProperties;
use App\Reporter\ReporterFactory;
use PHPUnit\Framework\Attributes\CoversNamespace;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Output\BufferedOutput;

/**
 * @internal
 */
#[CoversNamespace('App')]
#[Large]
final class CallOccurrenceContractTest extends TestCase
{
    #[DataProvider('providerEngineFormats')]
    public function testCallsKeepWrittenArgumentsAndDependenciesCollapseOccurrences(Analyzer $analyzer, OutputFormat $format): void
    {
        $source = <<<'PHP'
            <?php
            namespace Sites;
            function target(...$args) {}
            function caller($value) {
                target($value); target('literal'); target(mode: Config::MODE, next: 1 + 2, rest: [1, 2]);
                $closure = function () { target('nested'); };
                $reference = target(...);
            }
            PHP;
        $file = sys_get_temp_dir().'/peq-sites-'.uniqid().'.php';
        file_put_contents($file, $source);
        $graph = $analyzer->analyze($file);
        unlink($file);
        $root = $graph->nodeNamed('Sites\caller');
        self::assertNotNull($root);
        $calls = array_values(array_filter($graph->forwardEdges(), static fn ($edge): bool => $edge instanceof CallOccurrence && $edge->kind() === EdgeKind::FunctionCall));
        self::assertCount(4, $calls);
        $rows = array_map(static fn ($edge): array => [$edge->from()->toString(), EdgeProperties::of($edge)['arguments']->toText()], $calls);
        sort($rows);
        self::assertSame([
            ['Sites\caller', '[$value]'],
            ['Sites\caller', "['literal']"],
            ['Sites\caller', '[mode: Config::MODE, next: 1 + 2, rest: [1, 2]]'],
            ['Sites\caller{closure@6:16}', "['nested']"],
        ], $rows);
        $details = EdgeProperties::of($calls[2]);
        self::assertSame('[mode, next, rest]', $details['argumentNames']->toText());
        self::assertSame('[NULL, NULL, array]', $details['argumentTypes']->toText());
        self::assertSame('3', $details['argumentCount']->toText());
        self::assertSame('FALSE', $details['callableReference']->toText());
        $references = array_values(array_filter($graph->forwardEdges(), static fn ($edge): bool => $edge->kind() === EdgeKind::CallableReference));
        self::assertCount(1, $references);
        self::assertSame('TRUE', EdgeProperties::of($references[0])['callableReference']->toText());
        $depend = InspectionGraph::of($graph, $root, InspectFilter::Depend, Direction::Uses);
        self::assertCount(1, array_filter($depend->forwardEdges(), static fn ($edge): bool => $edge->kind() === EdgeKind::FunctionCall));
        $output = new BufferedOutput();
        (new ReporterFactory())->create(new Config(dirname($file), Direction::Uses, output: $format))->report(InspectionGraph::calls($graph, $root), $root->id(), $output);
        $written = $output->fetch();
        self::assertStringContainsString('literal', $written, $format->value);
        self::assertStringContainsString('Config::MODE', $written, $format->value);
        self::assertStringContainsString('nested', $written, $format->value);
        self::assertStringNotContainsString('target(...)', $written, $format->value);
        $reverse = new BufferedOutput();
        (new ReporterFactory())->create(new Config(dirname($file), Direction::UsedBy, output: OutputFormat::Json))->report(InspectionGraph::calls($graph, $root), \App\Analyzer\Graph\NodeId\FunctionNodeId::of('Sites\target'), $reverse);
        self::assertStringContainsString('Config::MODE', $reverse->fetch());
    }

    /**
     * @return iterable<string, array{Analyzer, OutputFormat}>
     */
    public static function providerEngineFormats(): iterable
    {
        foreach (self::providerEngines() as $engine => [$analyzer]) {
            foreach (OutputFormat::cases() as $format) {
                yield $engine.' '.$format->value => [$analyzer, $format];
            }
        }
    }

    #[DataProvider('providerEngines')]
    public function testNestedCallsWithTheSameStartAndTargetHaveSeparateSites(Analyzer $analyzer): void
    {
        $source = <<<'PHP'
            <?php
            namespace Sites;
            class Chain {
                function step($value): self { return $this; }
                function caller() { $this->step('first')->step('second'); }
            }
            PHP;
        $file = sys_get_temp_dir().'/peq-sites-'.uniqid().'.php';
        file_put_contents($file, $source);
        $graph = $analyzer->analyze($file);
        unlink($file);
        $calls = array_values(array_filter($graph->forwardEdges(), static fn ($edge): bool => $edge instanceof CallOccurrence && $edge->kind() === EdgeKind::MethodCall));
        self::assertCount(2, $calls);
        $expressions = array_map(static fn ($edge): string => EdgeProperties::of($edge)['expression']->toText(), $calls);
        sort($expressions);
        self::assertSame(["\$this->step('first')", "\$this->step('first')->step('second')"], $expressions);
        self::assertSame($calls[0]->meta()->offset, $calls[1]->meta()->offset);
        self::assertNotSame(EdgeProperties::of($calls[0])['callSite']->toText(), EdgeProperties::of($calls[1])['callSite']->toText());
    }

    /**
     * @return iterable<string, array{Analyzer}>
     */
    public static function providerEngines(): iterable
    {
        yield 'native' => [new NativeAnalyzer()];

        yield 'phpstan' => [new PhpStanAnalyzer()];
    }
}
