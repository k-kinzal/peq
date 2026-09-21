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

    public function testMatchRetainsTheSubjectForMultiConditionArms(): void
    {
        $source = <<<'SOURCE'
            <?php function f($flag) { return match ($flag) { 1, 2 => 10, default => 20 }; }
            SOURCE;
        $parsed = (new ParserFactory())->createForNewestSupportedVersion()->parse($source);
        self::assertNotNull($parsed);
        self::assertInstanceOf(Function_::class, $parsed[0]);
        $graph = (new Inspection())->analyze($parsed[0], new DependencyGraph('f', '/f.php', $source));
        $value = array_values(array_filter($graph->nodes, static fn (Occurrence $node): bool => $node->text === '10'))[0];
        $controls = array_values(array_filter($graph->edges, static fn (Dependency $edge): bool => $edge->from === $value->id && $edge->kind === 'control'));
        self::assertNotEmpty($controls);
        self::assertSame('$flag', $graph->nodes[$controls[0]->to]->variable);
    }

    public function testTernaryKeepsOnlyTheNormalArmAfterThrow(): void
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
        self::assertCount(1, $edges);
        self::assertSame(73, $graph->nodes[$edges[0]->to]->column);
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
}
