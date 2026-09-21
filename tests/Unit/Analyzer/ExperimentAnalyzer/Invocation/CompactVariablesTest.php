<?php

declare(strict_types=1);

namespace Tests\Unit\Analyzer\ExperimentAnalyzer\Invocation;

use App\Analyzer\ExperimentAnalyzer\DataFlow\Dependency;
use App\Analyzer\ExperimentAnalyzer\DataFlow\DependencyGraph;
use App\Analyzer\ExperimentAnalyzer\DataFlow\Inspection;
use App\Analyzer\ExperimentAnalyzer\DataFlow\InspectionException;
use App\Analyzer\ExperimentAnalyzer\DataFlow\Occurrence;
use App\Analyzer\ExperimentAnalyzer\Invocation\CallEffects;
use PhpParser\Node\Stmt\Function_;
use PhpParser\ParserFactory;
use PHPUnit\Framework\Attributes\CoversNamespace;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversNamespace('App\Analyzer\ExperimentAnalyzer')]
final class CompactVariablesTest extends TestCase
{
    /**
     * @param list<array{string, int, string}> $expected
     */
    #[DataProvider('providerLocalReads')]
    public function testInputsConnectImplicitReadsToCurrentLocalDefinitions(string $body, array $expected): void
    {
        $source = "<?php function f(\$flag) {\n".$body."\n}";
        $parsed = (new ParserFactory())->createForNewestSupportedVersion()->parse($source);
        self::assertNotNull($parsed);
        self::assertInstanceOf(Function_::class, $parsed[0]);
        $graph = (new Inspection())->analyze($parsed[0], new DependencyGraph('f', '/f.php', $source));
        $edges = array_filter($graph->edges, static fn (Dependency $edge): bool => $edge->kind === 'reaching-definition' && $graph->nodes[$edge->from]->kind === 'implicit-read');
        $actual = array_values(array_map(static fn (Dependency $edge): array => [$graph->nodes[$edge->from]->variable, $graph->nodes[$edge->to]->line, $graph->nodes[$edge->to]->kind], $edges));
        sort($actual);
        sort($expected);
        self::assertSame($expected, $actual);
        $reads = array_filter($graph->nodes, static fn (Occurrence $node): bool => $node->kind === 'implicit-read');
        $inputs = array_column(array_filter($graph->edges, static fn (Dependency $edge): bool => $edge->kind === 'call-input'), 'to');
        self::assertSame([], array_values(array_diff(array_keys($reads), $inputs)));
    }

    /**
     * @return iterable<string, array{string, list<array{string, int, string}>}>
     */
    public static function providerLocalReads(): iterable
    {
        yield 'parameters' => ["return compact('flag');", [['$flag', 1, 'parameter']]];

        yield 'overwrite kills previous value' => ["\$ext = false;\n\$ext = 'php';\nreturn compact('ext');", [['$ext', 3, 'write']]];

        yield 'conditional alternatives' => ["\$ext = false;\nif (\$flag) { \$ext = 'php'; }\nreturn compact('ext');", [['$ext', 2, 'write'], ['$ext', 3, 'write']]];

        yield 'nested literal lists' => ["\$ext = 'php';\nreturn compact(['flag', ['ext']]);", [['$flag', 1, 'parameter'], ['$ext', 2, 'write']]];

        yield 'undefined variable' => ["return compact('missing');", [['$missing', 2, 'unbound']]];

        yield 'unset local' => ["\$ext = 'php';\nunset(\$ext);\nreturn compact('ext');", [['$ext', 3, 'undefined']]];

        yield 'qualified case insensitive builtin' => ["return \\COMPACT('flag');", [['$flag', 1, 'parameter']]];

        yield 'empty list' => ['return compact([]);', []];

        yield 'callable creation' => ['return compact(...);', []];

        yield 'static method with the same name' => ["return Other::compact('flag');", []];

        yield 'different qualified function' => ["return Custom\\compact('flag');", []];
    }

    #[DataProvider('providerUnknownNames')]
    public function testNamesRejectsUnresolvedVariableSelection(string $arguments): void
    {
        $source = '<?php function f($names) { return compact('.$arguments.'); }';
        $parsed = (new ParserFactory())->createForNewestSupportedVersion()->parse($source);
        self::assertNotNull($parsed);
        self::assertInstanceOf(Function_::class, $parsed[0]);
        $this->expectException(InspectionException::class);
        $this->expectExceptionMessage('compact()');
        (new Inspection())->analyze($parsed[0], new DependencyGraph('f', '/f.php', $source));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function providerUnknownNames(): iterable
    {
        yield 'runtime name' => ['$names'];

        yield 'nested runtime names' => ["['names', [\$names]]"];

        yield 'duplicate keys overwrite names' => ["[0 => 'names', 0 => 'other']"];

        yield 'array unpacking' => ['[...$names]'];

        yield 'argument unpacking' => ['...$names'];
    }

    public function testInputsDoesNotTreatAShadowedFunctionAsTheBuiltin(): void
    {
        $source = "<?php function f(\$flag) { return compact('flag'); }";
        $parsed = (new ParserFactory())->createForNewestSupportedVersion()->parse($source);
        self::assertNotNull($parsed);
        self::assertInstanceOf(Function_::class, $parsed[0]);
        $graph = (new Inspection())->analyze($parsed[0], new DependencyGraph('f', '/f.php', $source), new CallEffects(['compact' => [false]]));
        self::assertSame([], array_filter($graph->nodes, static fn (Occurrence $node): bool => $node->kind === 'implicit-read'));
    }

    public function testInputsRespectsNamespacedFunctionShadowing(): void
    {
        $source = "<?php namespace Example; function compact(\$name) { return \$name; } function f(\$flag) { return compact('flag'); }";
        $parsed = (new ParserFactory())->createForNewestSupportedVersion()->parse($source);
        self::assertNotNull($parsed);
        $resolved = (new \PhpParser\NodeTraverser(new \PhpParser\NodeVisitor\NameResolver()))->traverse($parsed);
        $inspection = new Inspection();
        $callables = $inspection->callables(array_values(array_filter($resolved, static fn (\PhpParser\Node $node): bool => $node instanceof \PhpParser\Node\Stmt)));
        self::assertArrayHasKey('Example\f', $callables);
        $graph = $inspection->analyze($callables['Example\f'], new DependencyGraph('Example\f', '/f.php', $source), new CallEffects(['example\compact' => [false]]));
        self::assertSame([], array_filter($graph->nodes, static fn (Occurrence $node): bool => $node->kind === 'implicit-read'));
    }
}
