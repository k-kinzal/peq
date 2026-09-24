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
final class ConditionalExpressionsTest extends TestCase
{
    public function testReadSkipsAConstantFalseOperand(): void
    {
        $source = <<<'SOURCE'
            <?php function f() { false && ($a = 1); }
            SOURCE;
        $parsed = (new ParserFactory())->createForNewestSupportedVersion()->parse($source);
        self::assertNotNull($parsed);
        self::assertInstanceOf(Function_::class, $parsed[0]);
        $graph = (new Inspection())->analyze($parsed[0], new DependencyGraph('f', '/f.php', $source));
        self::assertSame([], array_values(array_filter($graph->nodes, static fn (Occurrence $node): bool => $node->kind === 'write')));
    }

    public function testCoalesceAssignmentDoesNotMakeAReadControlItself(): void
    {
        $source = <<<'SOURCE'
            <?php function f($a) { $a[0] ??= 1; return $a; }
            SOURCE;
        $parsed = (new ParserFactory())->createForNewestSupportedVersion()->parse($source);
        self::assertNotNull($parsed);
        self::assertInstanceOf(Function_::class, $parsed[0]);
        $graph = (new Inspection())->analyze($parsed[0], new DependencyGraph('f', '/f.php', $source));
        self::assertSame([], array_values(array_filter($graph->edges, static fn (Dependency $edge): bool => $edge->from === $edge->to)));
    }

    public function testReadPreservesUnsolvedMatchArms(): void
    {
        $source = <<<'SOURCE'
            <?php function f($flag) { return match ($flag) { 1, 2 => 10, default => 20 }; }
            SOURCE;
        $parsed = (new ParserFactory())->createForNewestSupportedVersion()->parse($source);
        self::assertNotNull($parsed);
        self::assertInstanceOf(Function_::class, $parsed[0]);
        $graph = (new Inspection())->analyze($parsed[0], new DependencyGraph('f', '/f.php', $source));
        self::assertContains('UNSUPPORTED_EXPRESSION', array_column($graph->issues, 'code'));
        self::assertNotEmpty(array_filter($graph->nodes, static fn (Occurrence $node): bool => $node->text === '10'));
    }

    public function testTernaryKeepsKnownAndUnknownArms(): void
    {
        $source = <<<'SOURCE'
            <?php function f($flag) { $a = 0; $b = $flag ? throw new Exception() : ($a = 2); return $a; }
            SOURCE;
        $parsed = (new ParserFactory())->createForNewestSupportedVersion()->parse($source);
        self::assertNotNull($parsed);
        self::assertInstanceOf(Function_::class, $parsed[0]);
        $graph = (new Inspection())->analyze($parsed[0], new DependencyGraph('f', '/f.php', $source));
        $reads = array_values(array_filter($graph->nodes, static fn (Occurrence $node): bool => $node->kind === 'read' && $node->variable === '$a'));
        $edges = array_values(array_filter($graph->edges, static fn (Dependency $edge): bool => $edge->from === $reads[0]->id && $edge->kind === 'reaching-definition'));
        self::assertCount(3, $edges);
        self::assertContains('UNSUPPORTED_EXPRESSION', array_column($graph->issues, 'code'));
    }

    public function testBinarySkipsTheRightOperandOfTrueOr(): void
    {
        $source = <<<'SOURCE'
            <?php function f() { true || ($a = 2); }
            SOURCE;
        $parsed = (new ParserFactory())->createForNewestSupportedVersion()->parse($source);
        self::assertNotNull($parsed);
        self::assertInstanceOf(Function_::class, $parsed[0]);
        $graph = (new Inspection())->analyze($parsed[0], new DependencyGraph('f', '/f.php', $source));
        self::assertSame([], array_values(array_filter($graph->nodes, static fn (Occurrence $node): bool => $node->kind === 'write')));
    }

    public function testBinaryDoesNotEvaluateAFallbackForFalse(): void
    {
        $source = "<?php function f() {\n\$i = 0;\nfalse ?? (\$i = 1);\nreturn \$i;\n}";
        $parsed = (new ParserFactory())->createForNewestSupportedVersion()->parse($source);
        self::assertNotNull($parsed);
        self::assertInstanceOf(Function_::class, $parsed[0]);
        $graph = (new Inspection())->analyze($parsed[0], new DependencyGraph('f', '/f.php', $source));
        $slice = \App\Analyzer\ExperimentAnalyzer\DataFlow\Slice::of($graph, $graph->select(4, 'i'), \App\Analyzer\Graph\Direction::Uses, null);
        self::assertTrue($slice->analysis->complete);
        self::assertSame([2], array_values(array_unique(array_map(static fn (Occurrence $node): int => $node->line, array_filter($slice->nodes, static fn (Occurrence $node): bool => $node->kind === 'write')))));
    }
}
