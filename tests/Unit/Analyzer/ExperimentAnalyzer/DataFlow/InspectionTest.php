<?php

declare(strict_types=1);

namespace Tests\Unit\Analyzer\ExperimentAnalyzer\DataFlow;

use App\Analyzer\ExperimentAnalyzer\DataFlow\DependencyGraph;
use App\Analyzer\ExperimentAnalyzer\DataFlow\Inspection;
use App\Analyzer\ExperimentAnalyzer\DataFlow\InspectionException;
use PhpParser\Node\Stmt\Function_;
use PhpParser\ParserFactory;
use PHPUnit\Framework\Attributes\CoversNamespace;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversNamespace('App\Analyzer\ExperimentAnalyzer')]
#[\PHPUnit\Framework\Attributes\UsesClass(\App\Analyzer\SourceParser::class)]
final class InspectionTest extends TestCase
{
    public function testInspectRejectsAMissingCallable(): void
    {
        $this->expectException(InspectionException::class);
        $this->expectExceptionMessage('Callable not found');
        (new Inspection())->inspect(\App\Analyzer\ExperimentAnalyzer\SourceIndex::of([], ''), 'missing');
    }

    public function testCallablesDoesNotAssignAnAnonymousClassMethodToItsEnclosingClass(): void
    {
        $source = '<?php namespace Test; class Outer { function outer() { return new class { function inner() {} }; } }';
        $statements = (new ParserFactory())->createForNewestSupportedVersion()->parse($source);
        self::assertNotNull($statements);
        $traverser = new \PhpParser\NodeTraverser(new \PhpParser\NodeVisitor\NameResolver());
        $callables = (new Inspection())->callables(array_values(array_filter($traverser->traverse($statements), static fn (\PhpParser\Node $node): bool => $node instanceof \PhpParser\Node\Stmt)));
        self::assertSame(['Test\Outer::outer'], array_keys($callables));
    }

    /**
     * @param list<array{int, string, int, string}> $expected
     */
    #[DataProvider('providerPrograms')]
    public function testAnalyzeReachingDefinitionsFollowActualControlFlow(string $source, array $expected): void
    {
        $statements = (new ParserFactory())->createForNewestSupportedVersion()->parse($source);
        self::assertNotNull($statements);
        self::assertInstanceOf(Function_::class, $statements[0]);
        $graph = (new Inspection())->analyze($statements[0], new DependencyGraph('f', '/f.php', $source));
        $actual = array_values(array_map(
            static fn (\App\Analyzer\ExperimentAnalyzer\DataFlow\Dependency $edge): array => [
                $graph->nodes[$edge->from]->line, $graph->nodes[$edge->from]->variable,
                $graph->nodes[$edge->to]->line, $graph->nodes[$edge->to]->kind,
            ],
            array_filter($graph->edges, static fn (\App\Analyzer\ExperimentAnalyzer\DataFlow\Dependency $edge): bool => $edge->kind === 'reaching-definition'),
        ));
        sort($actual);
        sort($expected);
        self::assertSame($expected, $actual);
    }

    /**
     * @return iterable<string, array{string, list<array{int, string, int, string}>}>
     */
    public static function providerPrograms(): iterable
    {
        yield 'nested break exits both loops without visiting their tails' => [
            '<?php function f() {
$a = 0;
while (true) {
while (true) { $a = 1; break 2; }
$a = 2;
}
return $a;
}', [[7, '$a', 4, 'write']],
        ];

        yield 'switch continue exits the switch as break does' => [
            '<?php function f($flag) {
$a = 0;
switch ($flag) { default: $a = 1; continue; }
return $a;
}', [[3, '$flag', 1, 'parameter'], [4, '$a', 3, 'write']],
        ];

        yield 'switch break two exits its enclosing loop' => [
            '<?php function f($flag) {
$a = 0;
while (true) {
switch ($flag) { default: $a = 1; break 2; }
$a = 2;
}
return $a;
}', [[4, '$flag', 1, 'parameter'], [7, '$a', 4, 'write']],
        ];

        yield 'fallthrough does not evaluate another case condition' => [
            '<?php function f($flag) {
$a = 0;
switch ($flag) {
case 1: $a = 1;
case throw new Exception(): $a = 2; break;
default: $a = 3;
}
return $a;
}', [[3, '$flag', 1, 'parameter'], [8, '$a', 5, 'write']],
        ];

        yield 'constant ternary prunes its unused value' => [
            '<?php function f($a) {
$value = true ? $a : ($a = 2);
return $a;
}', [[2, '$a', 1, 'parameter'], [3, '$a', 1, 'parameter']],
        ];

        yield 'builtin reference output reaches its later use' => [
            '<?php function f($text) {
preg_match("/x/", $text, $matches);
return $matches;
}', [[2, '$text', 1, 'parameter'], [3, '$matches', 2, 'call-write']],
        ];

        yield 'constant short circuit never executes the right hand side' => [
            '<?php function f() {
$a = 1;
false && ($a = 2);
return $a;
}', [[4, '$a', 2, 'write']],
        ];

        yield 'a throw expression prevents the assignment and following statements' => [
            '<?php function f() {
$a = throw new Exception();
return $a;
}', [],
        ];

        yield 'ternary throw preserves only the normal arm' => [
            '<?php function f($flag) {
$a = 0;
$result = $flag ? throw new Exception() : ($a = 2);
return $a;
}', [[3, '$flag', 1, 'parameter'], [4, '$a', 3, 'write']],
        ];

        yield 'overwrite kills the old value' => [
            '<?php function f($input) {
$a = $input;
$a = 2;
return $a;
}', [[2, '$input', 1, 'parameter'], [4, '$a', 3, 'write']],
        ];

        yield 'both branches reach the merge' => [
            '<?php function f($flag) {
if ($flag) {
$a = 1;
} else {
$a = 2;
}
return $a;
}', [[2, '$flag', 1, 'parameter'], [7, '$a', 3, 'write'], [7, '$a', 5, 'write']],
        ];

        yield 'missing branch keeps an undefined value' => [
            '<?php function f($flag) {
if ($flag) {
$a = 1;
}
return $a;
}', [[2, '$flag', 1, 'parameter'], [5, '$a', 3, 'write'], [5, '$a', 3, 'unbound']],
        ];

        yield 'return does not reach the following statement' => [
            '<?php function f($flag) {
$a = 1;
if ($flag) {
$a = 2;
return $a;
}
return $a;
}', [[3, '$flag', 1, 'parameter'], [5, '$a', 4, 'write'], [7, '$a', 2, 'write']],
        ];

        yield 'constant false body is unreachable' => [
            '<?php function f() {
$a = 1;
if (false) { $a = 2; }
return $a;
}', [[4, '$a', 2, 'write']],
        ];

        yield 'while carries values across iterations and allows zero iterations' => [
            '<?php function f($flag) {
$a = 0;
while ($flag) {
$b = $a;
$a = 1;
}
return $a;
}', [[3, '$flag', 1, 'parameter'], [4, '$a', 2, 'write'], [4, '$a', 5, 'write'], [7, '$a', 2, 'write'], [7, '$a', 5, 'write']],
        ];

        yield 'do executes at least once' => [
            '<?php function f() {
$a = 0;
do { $a = 1; } while (false);
return $a;
}', [[4, '$a', 3, 'write']],
        ];

        yield 'break cannot reach a later assignment' => [
            '<?php function f() {
$a = 0;
while (true) {
$a = 1;
break;
$a = 2;
}
return $a;
}', [[8, '$a', 4, 'write']],
        ];

        yield 'continue feeds the header and skips the remainder' => [
            '<?php function f($flag) {
$a = 0;
while ($flag) {
$a = 1;
continue;
$a = 2;
}
return $a;
}', [[3, '$flag', 1, 'parameter'], [8, '$a', 2, 'write'], [8, '$a', 4, 'write']],
        ];

        yield 'foreach binds key and value but may be empty' => [
            '<?php function f($items) {
$value = 0;
foreach ($items as $key => $value) {
$copy = $value;
}
return $value;
}', [[3, '$items', 1, 'parameter'], [4, '$value', 3, 'write'], [6, '$value', 2, 'write'], [6, '$value', 3, 'write']],
        ];

        yield 'for increment runs after continue' => [
            '<?php function f($flag) {
for ($i = 0; $flag; $i++) {
continue;
}
return $i;
}', [[2, '$flag', 1, 'parameter'], [2, '$i', 2, 'write'], [2, '$i', 2, 'write'], [5, '$i', 2, 'write'], [5, '$i', 2, 'write']],
        ];

        yield 'short circuit write is not unconditional' => [
            '<?php function f($flag) {
$a = 0;
$flag && ($a = 1);
return $a;
}', [[3, '$flag', 1, 'parameter'], [4, '$a', 2, 'write'], [4, '$a', 3, 'write']],
        ];

        yield 'ternary branches use separate states' => [
            '<?php function f($flag) {
$a = 0;
$result = $flag ? ($a = 1) : $a;
return $a;
}', [[3, '$flag', 1, 'parameter'], [3, '$a', 2, 'write'], [4, '$a', 2, 'write'], [4, '$a', 3, 'write']],
        ];

        yield 'null coalescing assignment may keep old definition' => [
            '<?php function f($a) {
$a ??= 1;
return $a;
}', [[2, '$a', 1, 'parameter'], [3, '$a', 1, 'parameter'], [3, '$a', 2, 'write']],
        ];

        yield 'compound assignment reads before writing' => [
            '<?php function f($a) {
$a += 1;
return $a;
}', [[2, '$a', 1, 'parameter'], [3, '$a', 2, 'write']],
        ];

        yield 'unset cannot retain the old definition' => [
            '<?php function f($a) {
unset($a);
return $a;
}', [[3, '$a', 2, 'undefined']],
        ];

        yield 'switch fallthrough and break' => [
            '<?php function f($flag) {
$a = 0;
switch ($flag) {
case 1: $a = 1;
case 2: $b = $a; break;
default: $a = 3;
}
return $a;
}', [[3, '$flag', 1, 'parameter'], [5, '$a', 2, 'write'], [5, '$a', 4, 'write'], [8, '$a', 2, 'write'], [8, '$a', 4, 'write'], [8, '$a', 6, 'write']],
        ];

        yield 'closure body does not mutate the outer scope' => [
            '<?php function f($a) {
$closure = function () use ($a) { $a = 100; return $a; };
return $a;
}', [[3, '$a', 1, 'parameter']],
        ];

        yield 'throw terminates the current branch' => [
            '<?php function f($flag) {
$a = 1;
if ($flag) { $a = 2; throw new Exception(); }
return $a;
}', [[3, '$flag', 1, 'parameter'], [4, '$a', 2, 'write']],
        ];

        yield 'match arm writes are isolated' => [
            '<?php function f($flag) {
$a = 0;
$result = match ($flag) { 1 => ($a = 1), default => $a };
return $a;
}', [[3, '$flag', 1, 'parameter'], [3, '$a', 2, 'write'], [4, '$a', 2, 'write'], [4, '$a', 3, 'write']],
        ];
    }

    #[DataProvider('providerUnsupported')]
    public function testAnalyzeRejectsUnsupportedFlowInsteadOfReturningPlausibleEdges(string $body): void
    {
        $source = '<?php function f($a) { '.$body.' }';
        $statements = (new ParserFactory())->createForNewestSupportedVersion()->parse($source);
        self::assertNotNull($statements);
        self::assertInstanceOf(Function_::class, $statements[0]);
        $this->expectException(InspectionException::class);
        (new Inspection())->analyze($statements[0], new DependencyGraph('f', '/f.php', $source));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function providerUnsupported(): iterable
    {
        yield 'alias' => ['$b =& $a;'];

        yield 'reference iteration' => ['foreach ($a as &$b) {}'];

        yield 'variable variable' => ['return $$a;'];

        yield 'exception flow' => ['try { $a = 1; } finally { $a = 2; }'];

        yield 'reference capture' => ['$b = function () use (&$a) {};'];

        yield 'shared storage' => ['global $a;'];

        yield 'suspension' => ['yield $a;'];

        yield 'dynamic code' => ['eval($a);'];
    }

    /**
     * @param list<array{int, string, null|string}> $expected
     */
    #[DataProvider('providerValueSlices')]
    public function testAnalyzeKeepsOnlyTheDefinitionsThatDetermineTheSelectedValue(string $source, int $line, string $variable, array $expected): void
    {
        $parsed = (new ParserFactory())->createForNewestSupportedVersion()->parse($source);
        self::assertNotNull($parsed);
        self::assertInstanceOf(Function_::class, $parsed[0]);
        $graph = (new Inspection())->analyze($parsed[0], new DependencyGraph('f', '/f.php', $source));
        $slice = \App\Analyzer\ExperimentAnalyzer\DataFlow\Slice::of($graph, $graph->select($line, $variable), \App\Analyzer\Graph\Direction::Uses, null);
        $actual = array_map(static fn (\App\Analyzer\ExperimentAnalyzer\DataFlow\Occurrence $node): array => [$node->line, $node->kind, $node->variable], $slice->nodes);
        sort($actual);
        sort($expected);
        self::assertSame($expected, $actual);
    }

    /**
     * @return iterable<string, array{string, int, string, list<array{int, string, null|string}>}>
     */
    public static function providerValueSlices(): iterable
    {
        yield 'arithmetic keeps both inputs' => [
            '<?php function f($left, $right) {
$value = $left + $right;
return $value;
}', 3, 'value', [[1, 'parameter', '$left'], [1, 'parameter', '$right'], [2, 'read', '$left'], [2, 'read', '$right'], [2, 'expression', null], [2, 'write', '$value'], [3, 'read', '$value']],
        ];

        yield 'ternary value includes its condition and both arms' => [
            '<?php function f($flag, $left, $right) {
$value = $flag ? $left : $right;
return $value;
}', 3, 'value', [[1, 'parameter', '$flag'], [1, 'parameter', '$left'], [1, 'parameter', '$right'], [2, 'read', '$flag'], [2, 'read', '$left'], [2, 'read', '$right'], [2, 'expression', null], [2, 'write', '$value'], [3, 'read', '$value']],
        ];

        yield 'coalescing assignment carries the original or fallback value' => [
            '<?php function f($a, $fallback) {
$a ??= $fallback;
return $a;
}', 3, 'a', [[1, 'parameter', '$a'], [1, 'parameter', '$fallback'], [2, 'read', '$a'], [2, 'read', '$fallback'], [2, 'write', '$a'], [3, 'read', '$a']],
        ];

        yield 'foreach key depends on the iterable' => [
            '<?php function f($items) {
foreach ($items as $key => $value) {
return $key;
}
}', 3, 'key', [[1, 'parameter', '$items'], [2, 'read', '$items'], [2, 'write', '$key'], [3, 'read', '$key']],
        ];

        yield 'a copied value excludes a later overwrite' => [
            '<?php function f($input) {
$a = $input;
$copy = $a;
$a = 9;
return $copy;
}', 5, 'copy', [[1, 'parameter', '$input'], [2, 'read', '$input'], [2, 'write', '$a'], [3, 'read', '$a'], [3, 'write', '$copy'], [5, 'read', '$copy']],
        ];

        yield 'prefix increment supplies the new value' => [
            '<?php function f($a) {
$b = ++$a;
return $b;
}', 3, 'b', [[1, 'parameter', '$a'], [2, 'read', '$a'], [2, 'write', '$a'], [2, 'write', '$b'], [3, 'read', '$b']],
        ];

        yield 'postfix decrement supplies the old value' => [
            '<?php function f($a) {
$b = $a--;
return $b;
}', 3, 'b', [[1, 'parameter', '$a'], [2, 'read', '$a'], [2, 'write', '$b'], [3, 'read', '$b']],
        ];

        yield 'compound assignment includes both inputs' => [
            '<?php function f($a, $b) {
$a += $b;
return $a;
}', 3, 'a', [[1, 'parameter', '$a'], [1, 'parameter', '$b'], [2, 'read', '$a'], [2, 'read', '$b'], [2, 'write', '$a'], [3, 'read', '$a']],
        ];

        yield 'conditional assignment retains the predicate and both alternatives' => [
            '<?php function f($flag) {
$a = 0;
if ($flag) {
$a = 1;
}
return $a;
}', 6, 'a', [[1, 'parameter', '$flag'], [2, 'literal', null], [2, 'write', '$a'], [3, 'read', '$flag'], [4, 'literal', null], [4, 'write', '$a'], [6, 'read', '$a']],
        ];
    }

    #[DataProvider('providerTerminatedOperands')]
    public function testAnalyzeDoesNotVisitOperandsAfterTermination(string $body): void
    {
        $source = '<?php function f($flag, $a, $later) { '.$body.' }';
        $parsed = (new ParserFactory())->createForNewestSupportedVersion()->parse($source);
        self::assertNotNull($parsed);
        self::assertInstanceOf(Function_::class, $parsed[0]);
        $graph = (new Inspection())->analyze($parsed[0], new DependencyGraph('f', '/f.php', $source));
        self::assertSame([], array_values(array_filter($graph->nodes, static fn (\App\Analyzer\ExperimentAnalyzer\DataFlow\Occurrence $node): bool => $node->variable === '$later' && $node->kind === 'read')));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function providerTerminatedOperands(): iterable
    {
        yield 'echo' => ['echo throw new Exception(), $later;'];

        yield 'for initialization' => ['for (throw new Exception(), $later; true;) {}'];

        yield 'for conditions' => ['for (; throw new Exception(), $later;) {}'];

        yield 'for updates' => ['for (;; throw new Exception(), $later) {}'];

        yield 'switch conditions' => ['switch ($flag) { case throw new Exception(): break; case $later: break; }'];

        yield 'match conditions' => ['$a = match ($flag) { throw new Exception() => 1, $later => 2 };'];

        yield 'compound address' => ['$a[throw new Exception()] += $later;'];

        yield 'destructuring key' => ['[throw new Exception() => $a, $later => $a] = [];'];
    }

    public function testInspectUsesWrittenReferenceSignaturesAndCaseInsensitiveTargets(): void
    {
        $file = dirname(__DIR__, 4).'/Fixture/Experimental/Flow.php';
        $index = \App\Analyzer\ExperimentAnalyzer\SourceIndex::of([$file], dirname($file));
        $graph = (new Inspection())->inspect($index, '\tests\fixture\experimental\flow::referenced');
        self::assertSame('Tests\Fixture\Experimental\Flow::referenced', $graph->target);
        self::assertSame($file, $graph->file);
        $definitions = array_values(array_map(
            static fn (\App\Analyzer\ExperimentAnalyzer\DataFlow\Dependency $edge): array => [$graph->nodes[$edge->from]->line, $graph->nodes[$edge->to]->line, $graph->nodes[$edge->to]->kind],
            array_filter($graph->edges, static fn (\App\Analyzer\ExperimentAnalyzer\DataFlow\Dependency $edge): bool => $edge->kind === 'reaching-definition'),
        ));
        self::assertSame([[29, 28, 'write'], [31, 29, 'call-write']], $definitions);
    }

    public function testCallablesFindsMultipleNamespacedFunctionsAndConcreteMethods(): void
    {
        $source = '<?php namespace Test; function one() {} function two() {} abstract class C { abstract function absent(); function present() {} }';
        $parsed = (new ParserFactory())->createForNewestSupportedVersion()->parse($source);
        self::assertNotNull($parsed);
        $traverser = new \PhpParser\NodeTraverser(new \PhpParser\NodeVisitor\NameResolver());
        $callables = (new Inspection())->callables(array_values(array_filter($traverser->traverse($parsed), static fn (\PhpParser\Node $node): bool => $node instanceof \PhpParser\Node\Stmt)));
        self::assertSame(['Test\one', 'Test\two', 'Test\C::present'], array_keys($callables));
    }
}
