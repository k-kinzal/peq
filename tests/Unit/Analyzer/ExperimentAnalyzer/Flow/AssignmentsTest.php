<?php

declare(strict_types=1);

namespace Tests\Unit\Analyzer\ExperimentAnalyzer\Flow;

use App\Analyzer\ExperimentAnalyzer\DataFlow\Dependency;
use App\Analyzer\ExperimentAnalyzer\DataFlow\DependencyGraph;
use App\Analyzer\ExperimentAnalyzer\DataFlow\Inspection;
use App\Analyzer\ExperimentAnalyzer\DataFlow\Occurrence;
use PhpParser\Node\Stmt\Function_;
use PhpParser\ParserFactory;
use PHPUnit\Framework\Attributes\CoversNamespace;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversNamespace('App\Analyzer\ExperimentAnalyzer')]
final class AssignmentsTest extends TestCase
{
    public function testAssignKillsEarlierDefinitions(): void
    {
        $source = <<<'SOURCE'
            <?php function f($a) { $a = 1; return $a; }
            SOURCE;
        $parsed = (new ParserFactory())->createForNewestSupportedVersion()->parse($source);
        self::assertNotNull($parsed);
        self::assertInstanceOf(Function_::class, $parsed[0]);
        $graph = (new Inspection())->analyze($parsed[0], new DependencyGraph('f', '/f.php', $source));
        $edges = array_values(array_filter($graph->edges, static fn (Dependency $edge): bool => $edge->kind === 'reaching-definition'));
        self::assertCount(1, $edges);
        self::assertSame('write', $graph->nodes[$edges[0]->to]->kind);
    }

    public function testWriteArrayElementsRetainsAggregateHistory(): void
    {
        $source = <<<'SOURCE'
            <?php function f($a, $key) { $a[$key] = 1; return $a; }
            SOURCE;
        $parsed = (new ParserFactory())->createForNewestSupportedVersion()->parse($source);
        self::assertNotNull($parsed);
        self::assertInstanceOf(Function_::class, $parsed[0]);
        $graph = (new Inspection())->analyze($parsed[0], new DependencyGraph('f', '/f.php', $source));
        self::assertCount(1, array_filter($graph->nodes, static fn (Occurrence $node): bool => $node->kind === 'aggregate-write'));
        self::assertStringContainsString('elements are tracked together', implode('', $graph->diagnostics));
    }

    public function testIncrementReturnsTheOldValueForPostfix(): void
    {
        $source = <<<'SOURCE'
            <?php function f($a) { $b = $a++; return $b; }
            SOURCE;
        $parsed = (new ParserFactory())->createForNewestSupportedVersion()->parse($source);
        self::assertNotNull($parsed);
        self::assertInstanceOf(Function_::class, $parsed[0]);
        $graph = (new Inspection())->analyze($parsed[0], new DependencyGraph('f', '/f.php', $source));
        $write = array_values(array_filter($graph->nodes, static fn (Occurrence $node): bool => $node->kind === 'write' && $node->variable === '$b'))[0];
        $input = array_values(array_filter($graph->edges, static fn (Dependency $edge): bool => $edge->from === $write->id && $edge->kind === 'data'))[0];
        self::assertSame('read', $graph->nodes[$input->to]->kind);
        self::assertSame('$a', $graph->nodes[$input->to]->variable);
    }
}
