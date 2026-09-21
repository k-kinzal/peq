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
final class StatementsTest extends TestCase
{
    public function testReadDoesNotVisitStatementsAfterReturn(): void
    {
        $source = <<<'SOURCE'
            <?php function f() { return 1; $a = 2; }
            SOURCE;
        $parsed = (new ParserFactory())->createForNewestSupportedVersion()->parse($source);
        self::assertNotNull($parsed);
        self::assertInstanceOf(Function_::class, $parsed[0]);
        $graph = (new Inspection())->analyze($parsed[0], new DependencyGraph('f', '/f.php', $source));
        self::assertSame([], array_values(array_filter($graph->nodes, static fn (Occurrence $node): bool => $node->kind === 'write')));
    }

    public function testStatementSeparatesNestedBreakDepths(): void
    {
        $source = <<<'SOURCE'
            <?php function f() { while (true) { while (true) { $a = 1; break 2; } $a = 2; } return $a; }
            SOURCE;
        $parsed = (new ParserFactory())->createForNewestSupportedVersion()->parse($source);
        self::assertNotNull($parsed);
        self::assertInstanceOf(Function_::class, $parsed[0]);
        $graph = (new Inspection())->analyze($parsed[0], new DependencyGraph('f', '/f.php', $source));
        $writes = array_values(array_filter($graph->nodes, static fn (Occurrence $node): bool => $node->kind === 'write'));
        self::assertCount(1, $writes);
    }

    public function testSimpleRecordsOutputInputs(): void
    {
        $source = <<<'SOURCE'
            <?php function f($a) { echo $a; }
            SOURCE;
        $parsed = (new ParserFactory())->createForNewestSupportedVersion()->parse($source);
        self::assertNotNull($parsed);
        self::assertInstanceOf(Function_::class, $parsed[0]);
        $graph = (new Inspection())->analyze($parsed[0], new DependencyGraph('f', '/f.php', $source));
        self::assertCount(1, array_filter($graph->nodes, static fn (Occurrence $node): bool => $node->kind === 'output'));
        self::assertCount(1, array_filter($graph->edges, static fn (Dependency $edge): bool => $edge->kind === 'data'));
    }
}
