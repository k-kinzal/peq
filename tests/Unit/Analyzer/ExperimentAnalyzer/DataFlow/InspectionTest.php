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
}
