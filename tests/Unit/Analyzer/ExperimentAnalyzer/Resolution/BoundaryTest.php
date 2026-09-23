<?php

declare(strict_types=1);

namespace Tests\Unit\Analyzer\ExperimentAnalyzer\Resolution;

use App\Analyzer\ExperimentAnalyzer\DataFlow\DependencyGraph;
use App\Analyzer\ExperimentAnalyzer\DataFlow\Inspection;
use App\Analyzer\ExperimentAnalyzer\DataFlow\Slice;
use App\Analyzer\Graph\Direction;
use PhpParser\Node\Stmt\Function_;
use PhpParser\ParserFactory;
use PHPUnit\Framework\Attributes\CoversNamespace;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversNamespace('App\Analyzer\ExperimentAnalyzer')]
final class BoundaryTest extends TestCase
{
    #[\PHPUnit\Framework\Attributes\DataProvider('providerUnsolvedConditions')]
    public function testReadKeepsUnknownContinuationWhenTruthBranchesRejoin(string $body): void
    {
        $source = "<?php function f() {\n".$body."\nreturn 1;\n}";
        $parsed = (new ParserFactory())->createForNewestSupportedVersion()->parse($source);
        self::assertNotNull($parsed);
        self::assertInstanceOf(Function_::class, $parsed[0]);
        $graph = (new Inspection())->analyze($parsed[0], new DependencyGraph('f', '/f.php', $source));
        $backward = Slice::of($graph, $graph->select(3, null), Direction::Uses, null);
        $forward = Slice::of($graph, $graph->select(2, null), Direction::UsedBy, null);
        self::assertFalse($backward->analysis->complete);
        self::assertSame(['OPAQUE_CALL'], array_column($backward->analysis->issues, 'code'));
        self::assertContains(2, array_column($backward->nodes, 'line'));
        self::assertContains(3, array_column($forward->nodes, 'line'));
        self::assertNotContains('resolved', $backward->analysis->nodes);
        self::assertNotContains('unknown-continuation', array_map(static fn (string $id): string => $graph->nodes[$id]->kind, $forward->roots));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function providerUnsolvedConditions(): iterable
    {
        yield 'if' => ['if (external()) {}'];

        yield 'elseif' => ['if (external()) {} elseif (false) {}'];

        yield 'ternary' => ['external() ? 1 : 2;'];

        yield 'boolean' => ['external() && 1;'];

        yield 'coalesce' => ['external() ?? 1;'];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('providerCounterexamples')]
    public function testReadNeverReportsAProvenCounterexampleAsComplete(string $body, string $code): void
    {
        $source = "<?php function f(\$a, \$b) {\n\$i = 0;\n".$body."\nreturn \$i;\n}";
        $parsed = (new ParserFactory())->createForNewestSupportedVersion()->parse($source);
        self::assertNotNull($parsed);
        self::assertInstanceOf(Function_::class, $parsed[0]);
        $graph = (new Inspection())->analyze($parsed[0], new DependencyGraph('f', '/f.php', $source));
        $slice = Slice::of($graph, $graph->select(4, 'i'), Direction::Uses, null);
        $reverse = Slice::of($graph, $graph->select(2, 'i'), Direction::UsedBy, null);
        self::assertFalse($slice->analysis->complete);
        self::assertFalse($reverse->analysis->complete);
        self::assertContains($code, array_column($slice->analysis->issues, 'code'));
        self::assertNotEmpty(array_filter($slice->nodes, static fn (\App\Analyzer\ExperimentAnalyzer\DataFlow\Occurrence $node): bool => $node->kind === 'unknown'));
        self::assertContains(4, array_column($reverse->nodes, 'line'));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function providerCounterexamples(): iterable
    {
        yield 'isset short circuit' => ['isset($a, $b[$i = 1]);', 'UNSUPPORTED_EXPRESSION'];

        yield 'foreach early return' => ['foreach ($a as $x) { if ($x) { return 0; } }', 'FOREACH_PROTOCOL'];

        yield 'locals snapshot' => ['$snapshot = get_defined_vars();', 'OPAQUE_CALL'];

        yield 'argument snapshot' => ['$a = 2; $snapshot = func_get_args();', 'OPAQUE_CALL'];

        yield 'disabled assertions' => ['assert(++$i);', 'OPAQUE_CALL'];

        yield 'reference alias' => ['$b =& $i;', 'UNSUPPORTED_EXPRESSION'];

        yield 'exception flow' => ['try { $i = 1; } finally { $i = 2; }', 'UNSUPPORTED_STATEMENT'];

        yield 'dynamic code' => ['eval($a);', 'UNSUPPORTED_EXPRESSION'];

        yield 'first class extract is not invoked' => ['$b = extract(...);', 'CALLABLE_CREATION'];

        yield 'first class compact is not invoked' => ['$b = compact(...);', 'CALLABLE_CREATION'];

        yield 'unmodeled match' => ['$i = match ($a) { 1 => 2, default => 3 };', 'UNSUPPORTED_EXPRESSION'];

        yield 'unmodeled switch' => ['switch ($a) { case 1: $i = 1; break; }', 'UNSUPPORTED_STATEMENT'];

        yield 'unmodeled do' => ['do { $i++; } while ($a);', 'LOOP_RECURRENCE'];

        yield 'order of side effects' => ['$i = $a++ + $a;', 'UNSUPPORTED_EXPRESSION'];

        yield 'nullsafe call' => ['$i = $a?->run($i++);', 'OPAQUE_CALL'];

        yield 'dynamic local names' => ['$$a = 1;', 'INDIRECT_WRITE'];

        yield 'generator suspension' => ['yield $a;', 'UNSUPPORTED_EXPRESSION'];
    }

    public function testContentsUsesByteSpansAndDoesNotAbsorbAnAdjacentExpression(): void
    {
        $source = '<?php function f($a) { foo(); $b = 1; return $b; }';
        $parsed = (new ParserFactory())->createForNewestSupportedVersion()->parse($source);
        self::assertNotNull($parsed);
        self::assertInstanceOf(Function_::class, $parsed[0]);
        $graph = (new Inspection())->analyze($parsed[0], new DependencyGraph('f', '/f.php', $source));
        $syntax = array_map(static fn (\App\Analyzer\ExperimentAnalyzer\DataFlow\Dependency $edge): \App\Analyzer\ExperimentAnalyzer\DataFlow\Occurrence => $graph->nodes[$edge->from], array_filter($graph->edges, static fn (\App\Analyzer\ExperimentAnalyzer\DataFlow\Dependency $edge): bool => $edge->kind === 'unresolved-region' && $graph->nodes[$edge->to]->text === 'foo()'));
        self::assertNotContains('$b', array_column($syntax, 'text'));
        self::assertContains('foo()', array_column($syntax, 'text'));
        self::assertNotEmpty($graph->issues);
    }

    public function testImplicitNamesRemainSelectableWithoutCertifyingBuiltinBinding(): void
    {
        $source = "<?php function f(\$ext) {\nreturn compact('ext');\n}";
        $parsed = (new ParserFactory())->createForNewestSupportedVersion()->parse($source);
        self::assertNotNull($parsed);
        self::assertInstanceOf(Function_::class, $parsed[0]);
        $graph = (new Inspection())->analyze($parsed[0], new DependencyGraph('f', '/f.php', $source));
        $slice = Slice::of($graph, $graph->select(2, 'ext'), Direction::Uses, null);
        self::assertSame('possible-implicit-read', $slice->nodes[0]->kind);
        self::assertFalse($slice->analysis->complete);
        self::assertSame(['OPAQUE_CALL'], array_column($slice->analysis->issues, 'code'));
    }
}
