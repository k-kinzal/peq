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
#[\PHPUnit\Framework\Attributes\UsesClass(\App\Analyzer\ExperimentAnalyzer\DataFlow\Dependency::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\App\Analyzer\ExperimentAnalyzer\DataFlow\Occurrence::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\App\Analyzer\ExperimentAnalyzer\DataFlow\Slice::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\App\Reporter\Diagram\Diagram::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\App\Reporter\Diagram\DiagramEdge::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\App\Reporter\Diagram\DiagramNode::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\App\Reporter\Diagram\MermaidRenderer::class)]
#[\PHPUnit\Framework\Attributes\UsesNamespace('App\Analyzer\ExperimentAnalyzer')]
#[\PHPUnit\Framework\Attributes\UsesNamespace('App\Action\Experimental')]
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

    /**
     * @throws JsonException
     */
    public function testReportPreservesTheCompleteMachineReadableUncertaintyContract(): void
    {
        $source = new \App\Analyzer\ExperimentAnalyzer\DataFlow\Occurrence('call', 'unknown', null, 4, 3, 5, "run(\n)");
        $issue = new \App\Analyzer\ExperimentAnalyzer\Resolution\Issue('OPAQUE_CALL', 'call', 'checked-rules/v1', 'Unknown effects.', $source);
        $analysis = new \App\Analyzer\ExperimentAnalyzer\Resolution\Assessment('partial', false, ['call' => 'unknown'], [$issue], []);
        $site = new \App\Analyzer\ExperimentAnalyzer\Structure\Site($source, 'Expr_FuncCall', 'body', 'expr', 'run', 20, 26);
        $slice = new \App\Analyzer\ExperimentAnalyzer\DataFlow\Slice('f', '/f.php', \App\Analyzer\Graph\Direction::UsedBy, ['call'], [$source], [], ['OPAQUE_CALL: Unknown effects.'], $analysis, [$site], ['sourceSha256' => 'hash']);
        $output = new \Symfony\Component\Console\Output\BufferedOutput();
        (new \App\Reporter\Experimental\VariableReporter())->report($slice, \App\Config\OutputFormat::Json, $output);
        $result = json_decode($output->fetch(), true, flags: JSON_THROW_ON_ERROR);
        self::assertIsArray($result);
        self::assertSame(['experimental', 'schemaVersion', 'analysis', 'structure', 'provenance', 'semantics', 'target', 'file', 'direction', 'roots', 'nodes', 'edges', 'diagnostics'], array_keys($result));
        self::assertTrue($result['experimental']);
        self::assertSame(2, $result['schemaVersion']);
        self::assertSame('f', $result['target']);
        self::assertSame('/f.php', $result['file']);
        self::assertSame('used-by', $result['direction']);
        self::assertSame(['call'], $result['roots']);
        self::assertSame([['id' => 'call', 'kind' => 'unknown', 'variable' => null, 'line' => 4, 'column' => 3, 'endLine' => 5, 'text' => "run(\n)"]], $result['nodes']);
        self::assertSame([], $result['edges']);
        self::assertSame(['OPAQUE_CALL: Unknown effects.'], $result['diagnostics']);
        self::assertSame(['sourceSha256' => 'hash'], $result['provenance']);
        self::assertSame(['status' => 'partial', 'complete' => false, 'nodes' => ['call' => 'unknown'], 'issues' => [['code' => 'OPAQUE_CALL', 'nodeId' => 'call', 'rule' => 'checked-rules/v1', 'reason' => 'Unknown effects.', 'source' => ['id' => 'call', 'kind' => 'unknown', 'variable' => null, 'line' => 4, 'column' => 3, 'endLine' => 5, 'text' => "run(\n)"], 'affects' => ['value', 'control', 'effects']]], 'frontier' => []], $result['analysis']);
        self::assertSame([['source' => ['id' => 'call', 'kind' => 'unknown', 'variable' => null, 'line' => 4, 'column' => 3, 'endLine' => 5, 'text' => "run(\n)"], 'syntax' => 'Expr_FuncCall', 'parent' => 'body', 'role' => 'expr', 'target' => 'run', 'start' => 20, 'end' => 26]], $result['structure']);
        self::assertIsString($result['semantics']);
        self::assertStringContainsString('not proven dependencies', $result['semantics']);
    }

    /**
     * @throws JsonException
     */
    public function testReportKeepsWarningsVisibleAndValidInDot(): void
    {
        $slice = new \App\Analyzer\ExperimentAnalyzer\DataFlow\Slice('f', '/f.php', \App\Analyzer\Graph\Direction::Uses, [], [], [], ['OPAQUE_CALL: <unknown>'], new \App\Analyzer\ExperimentAnalyzer\Resolution\Assessment('partial', false));
        $output = new \Symfony\Component\Console\Output\BufferedOutput();
        (new \App\Reporter\Experimental\VariableReporter())->report($slice, \App\Config\OutputFormat::Dot, $output);
        self::assertSame("digraph experimental {\n}\n// Analysis: partial; complete: no\n// OPAQUE_CALL: <unknown>\n", $output->fetch());
    }

    /**
     * @throws JsonException
     */
    public function testReportStatesWhenTheSelectedAnswerIsComplete(): void
    {
        $slice = new \App\Analyzer\ExperimentAnalyzer\DataFlow\Slice('f', '/f.php', \App\Analyzer\Graph\Direction::Uses, [], [], [], [], new \App\Analyzer\ExperimentAnalyzer\Resolution\Assessment('resolved', true));
        $output = new \Symfony\Component\Console\Output\BufferedOutput();
        (new \App\Reporter\Experimental\VariableReporter())->report($slice, \App\Config\OutputFormat::Table, $output);
        self::assertSame("FROM | DEPENDENCY | TO\nAnalysis: resolved; complete: yes\n", $output->fetch());
    }
}
