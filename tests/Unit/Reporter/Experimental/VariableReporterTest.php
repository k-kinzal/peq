<?php

declare(strict_types=1);

namespace Tests\Unit\Reporter\Experimental;

use JsonException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(\App\Reporter\Experimental\VariableReporter::class)]
final class VariableReporterTest extends TestCase
{
    /**
     * @throws JsonException
     */
    public function testReportWritesMachineReadableMetadata(): void
    {
        $slice = new \App\Analyzer\ExperimentAnalyzer\DataFlow\Slice('f', '/f.php', \App\Analyzer\Graph\Direction::Uses, ['a'], [new \App\Analyzer\ExperimentAnalyzer\DataFlow\Occurrence('a', 'read', '$a', 1, 1, 1, '$a'), new \App\Analyzer\ExperimentAnalyzer\DataFlow\Occurrence('b', 'write', '$b', 2, 1, 2, '$b')], [new \App\Analyzer\ExperimentAnalyzer\DataFlow\Dependency('a', 'b', 'control', 'truthy')], []);
        $reporter = new \App\Reporter\Experimental\VariableReporter();
        $output = new \Symfony\Component\Console\Output\BufferedOutput();
        $reporter->report($slice, \App\Config\OutputFormat::Json, $output);
        $text = $output->fetch();
        self::assertJson($text);
        self::assertStringContainsString('"roots": [', $text);
        self::assertStringContainsString('"branch": "truthy"', $text);
    }

    public function testDiagramRetainsTheBranchInMermaid(): void
    {
        $slice = new \App\Analyzer\ExperimentAnalyzer\DataFlow\Slice('f', '/f.php', \App\Analyzer\Graph\Direction::Uses, ['a'], [new \App\Analyzer\ExperimentAnalyzer\DataFlow\Occurrence('a', 'read', '$a', 1, 1, 1, '$a'), new \App\Analyzer\ExperimentAnalyzer\DataFlow\Occurrence('b', 'write', '$b', 2, 1, 2, '$b')], [new \App\Analyzer\ExperimentAnalyzer\DataFlow\Dependency('a', 'b', 'control', 'truthy')], []);
        $reporter = new \App\Reporter\Experimental\VariableReporter();
        self::assertStringContainsString('control (truthy)', $reporter->diagram($slice, \App\Config\OutputFormat::Mermaid));
    }

    public function testTableWritesTheSelectedEdge(): void
    {
        $slice = new \App\Analyzer\ExperimentAnalyzer\DataFlow\Slice('f', '/f.php', \App\Analyzer\Graph\Direction::Uses, ['a'], [new \App\Analyzer\ExperimentAnalyzer\DataFlow\Occurrence('a', 'read', '$a', 1, 1, 1, '$a'), new \App\Analyzer\ExperimentAnalyzer\DataFlow\Occurrence('b', 'write', '$b', 2, 1, 2, '$b')], [new \App\Analyzer\ExperimentAnalyzer\DataFlow\Dependency('a', 'b', 'control', 'truthy')], []);
        $reporter = new \App\Reporter\Experimental\VariableReporter();
        self::assertSame("FROM | DEPENDENCY | TO\na | control (truthy) | b", $reporter->table($slice));
    }

    /**
     * @throws JsonException
     */
    public function testDotWritesTheBranchLabel(): void
    {
        $slice = new \App\Analyzer\ExperimentAnalyzer\DataFlow\Slice('f', '/f.php', \App\Analyzer\Graph\Direction::Uses, ['a'], [new \App\Analyzer\ExperimentAnalyzer\DataFlow\Occurrence('a', 'read', '$a', 1, 1, 1, '$a'), new \App\Analyzer\ExperimentAnalyzer\DataFlow\Occurrence('b', 'write', '$b', 2, 1, 2, '$b')], [new \App\Analyzer\ExperimentAnalyzer\DataFlow\Dependency('a', 'b', 'control', 'truthy')], []);
        $reporter = new \App\Reporter\Experimental\VariableReporter();
        self::assertSame("digraph experimental {\n  \"a\";\n  \"b\";\n  \"a\" -> \"b\" [label=\"control (truthy)\"];\n}", $reporter->dot($slice));
    }

    public function testTableRetainsAnIsolatedRootBesideAConnectedComponent(): void
    {
        $nodes = [
            new \App\Analyzer\ExperimentAnalyzer\DataFlow\Occurrence('a', 'read', '$a', 1, 1, 1, '$a'),
            new \App\Analyzer\ExperimentAnalyzer\DataFlow\Occurrence('b', 'write', '$b', 2, 1, 2, '$b'),
            new \App\Analyzer\ExperimentAnalyzer\DataFlow\Occurrence('isolated', 'literal', null, 3, 1, 3, '1'),
        ];
        $slice = new \App\Analyzer\ExperimentAnalyzer\DataFlow\Slice('f', '/f.php', \App\Analyzer\Graph\Direction::Uses, ['a', 'isolated'], $nodes, [new \App\Analyzer\ExperimentAnalyzer\DataFlow\Dependency('a', 'b', 'data')], []);
        self::assertSame("FROM | DEPENDENCY | TO\na | data | b\nisolated | (no selected edges) |", (new \App\Reporter\Experimental\VariableReporter())->table($slice));
    }
}
