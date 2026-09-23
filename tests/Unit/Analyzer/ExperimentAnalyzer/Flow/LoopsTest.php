<?php

declare(strict_types=1);

namespace Tests\Unit\Analyzer\ExperimentAnalyzer\Flow;

use App\Analyzer\ExperimentAnalyzer\DataFlow\DependencyGraph;
use App\Analyzer\ExperimentAnalyzer\DataFlow\Inspection;
use App\Analyzer\ExperimentAnalyzer\DataFlow\Slice;
use App\Analyzer\Graph\Direction;
use PhpParser\Node\Stmt\Function_;
use PhpParser\ParserFactory;
use PHPUnit\Framework\Attributes\CoversNamespace;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversNamespace('App\Analyzer\ExperimentAnalyzer')]
final class LoopsTest extends TestCase
{
    /**
     * @param list<string> $writes
     */
    #[DataProvider('providerNonrepeating')]
    public function testReadSolvesNonrepeatingLoopsWithoutInventingWrites(string $body, array $writes): void
    {
        $source = '<?php function f() { '.$body.' }';
        $parsed = (new ParserFactory())->createForNewestSupportedVersion()->parse($source);
        self::assertNotNull($parsed);
        self::assertInstanceOf(Function_::class, $parsed[0]);
        $graph = (new Inspection())->analyze($parsed[0], new DependencyGraph('f', '/f.php', $source));
        self::assertSame([], $graph->issues);
        self::assertSame($writes, array_column(array_filter($graph->nodes, static fn ($node) => $node->kind === 'write'), 'variable'));
    }

    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function providerNonrepeating(): iterable
    {
        yield 'false while' => ['while (false) { $unused = 1; }', []];

        yield 'for initialization and every condition run' => ['for ($i = 0; $test = 1, false; $unused++) { $body = 2; }', ['$i', '$test']];

        yield 'do is entered once' => ['do { $x = 1; } while (false);', ['$x']];

        yield 'break skips for updates' => ['for ($i = 0;; $unused++) { $x = 1; break; $dead = 1; }', ['$i', '$x']];

        yield 'break skips do condition' => ['do { $x = 1; break; } while ($unused = true);', ['$x']];

        yield 'continue reaches do condition but skips remaining body' => ['do { $x = 1; continue; $dead = 1; } while (false);', ['$x']];

        yield 'nested break two' => ['while (true) { do { $x = 1; break 2; } while (true); $dead = 2; }', ['$x']];
    }

    public function testPassRetainsTheFalseConditionAfterALoopEarlyReturn(): void
    {
        $source = "<?php function f(bool \$flag) {\nwhile (\$flag) { return 0; }\nreturn 1;\n}";
        $parsed = (new ParserFactory())->createForNewestSupportedVersion()->parse($source);
        self::assertNotNull($parsed);
        self::assertInstanceOf(Function_::class, $parsed[0]);
        $graph = (new Inspection())->analyze($parsed[0], new DependencyGraph('f', '/f.php', $source));
        $backward = Slice::of($graph, $graph->select(3, null), Direction::Uses, null);
        $forward = Slice::of($graph, $graph->select(1, 'flag'), Direction::UsedBy, null);
        self::assertTrue($backward->analysis->complete);
        self::assertSame([], $backward->analysis->issues);
        self::assertContains(3, array_column($forward->nodes, 'line'));
        self::assertContains('falsy', array_column($backward->edges, 'branch'));
        self::assertContains('truthy', array_column($forward->edges, 'branch'));
        self::assertSame([3, 3, 2, 1], array_column($backward->nodes, 'line'));
    }

    public function testAdvanceRunsForUpdatesOnContinueAndRetainsTheBackEdge(): void
    {
        $source = "<?php function f(bool \$flag) {\nfor (\$i = 0; \$flag; \$i++) {\ncontinue;\n\$dead = 1;\n}\nreturn \$i;\n}";
        $parsed = (new ParserFactory())->createForNewestSupportedVersion()->parse($source);
        self::assertNotNull($parsed);
        self::assertInstanceOf(Function_::class, $parsed[0]);
        $graph = (new Inspection())->analyze($parsed[0], new DependencyGraph('f', '/f.php', $source));
        $slice = Slice::of($graph, $graph->select(6, 'i'), Direction::Uses, null);
        self::assertFalse($slice->analysis->complete);
        self::assertContains('LOOP_RECURRENCE', array_column($slice->analysis->issues, 'code'));
        self::assertContains('loop-carried', array_column($slice->edges, 'kind'));
        self::assertContains('continue', array_column($slice->nodes, 'kind'));
        self::assertNotContains('$dead', array_column(array_filter($graph->nodes, static fn ($node) => $node->kind === 'write'), 'text'));
        self::assertCount(2, array_filter($graph->nodes, static fn ($node) => $node->kind === 'write' && $node->variable === '$i'));
    }

    public function testReadKeepsForeachBindingsAndBodyConditionsDespiteUnknownProtocol(): void
    {
        $source = "<?php function f(array \$items) {\nforeach (\$items as \$key => \$value) {\nif (\$value) { return \$key; }\n}\nreturn 0;\n}";
        $parsed = (new ParserFactory())->createForNewestSupportedVersion()->parse($source);
        self::assertNotNull($parsed);
        self::assertInstanceOf(Function_::class, $parsed[0]);
        $graph = (new Inspection())->analyze($parsed[0], new DependencyGraph('f', '/f.php', $source));
        $slice = Slice::of($graph, $graph->select(3, 'key'), Direction::Uses, null);
        self::assertContains('FOREACH_PROTOCOL', array_column($slice->analysis->issues, 'code'));
        self::assertContains('LOOP_RECURRENCE', array_column($slice->analysis->issues, 'code'));
        self::assertContains('iterate', array_column($slice->edges, 'branch'));
        self::assertContains('truthy', array_column($slice->edges, 'branch'));
        self::assertContains('iteration-value', array_column($slice->nodes, 'kind'));
        $forward = Slice::of($graph, $graph->select(1, 'items'), Direction::UsedBy, null);
        self::assertContains(5, array_column($forward->nodes, 'line'));
        self::assertContains('exhausted', array_column($forward->edges, 'branch'));
    }

    public function testReadDoesNotExecuteAReferenceIterationAsIndependentLocalWrites(): void
    {
        $source = '<?php function f($items) { foreach ($items as &$value) { $value = 1; } $value = 2; }';
        $parsed = (new ParserFactory())->createForNewestSupportedVersion()->parse($source);
        self::assertNotNull($parsed);
        self::assertInstanceOf(Function_::class, $parsed[0]);
        $graph = (new Inspection())->analyze($parsed[0], new DependencyGraph('f', '/f.php', $source));
        self::assertContains('FOREACH_REFERENCE', array_column($graph->issues, 'code'));
        self::assertContains('VALUE_LIFETIME', array_column($graph->issues, 'code'));
        self::assertCount(1, array_filter($graph->nodes, static fn ($node) => $node->kind === 'write'));
    }

    public function testReadDoesNotCarryAnOverwrittenPreLoopValueIntoTheResult(): void
    {
        $source = "<?php function f(int \$limit) {\n\$i = 9;\nfor (\$i = 0; \$i < \$limit; \$i++) {}\nreturn \$i;\n}";
        $parsed = (new ParserFactory())->createForNewestSupportedVersion()->parse($source);
        self::assertNotNull($parsed);
        self::assertInstanceOf(Function_::class, $parsed[0]);
        $graph = (new Inspection())->analyze($parsed[0], new DependencyGraph('f', '/f.php', $source));
        $old = Slice::of($graph, $graph->select(2, 'i'), Direction::UsedBy, null);
        self::assertSame([2], array_column($old->nodes, 'line'));
        $result = Slice::of($graph, $graph->select(4, 'i'), Direction::Uses, null);
        self::assertNotContains(2, array_column($result->nodes, 'line'));
        self::assertContains('LOOP_RECURRENCE', array_column($result->analysis->issues, 'code'));
        self::assertContains('loop-carried', array_column($result->edges, 'kind'));
    }

    public function testPassDetectsCorrelatedTestsAcrossAnIfAndSingleVisitLoop(): void
    {
        $source = '<?php function f(bool $flag) { if ($flag) { $x = 1; } while ($flag) { return $x; } return 0; }';
        $parsed = (new ParserFactory())->createForNewestSupportedVersion()->parse($source);
        self::assertNotNull($parsed);
        self::assertInstanceOf(Function_::class, $parsed[0]);
        $graph = (new Inspection())->analyze($parsed[0], new DependencyGraph('f', '/f.php', $source));
        self::assertContains('PATH_CORRELATION', array_column($graph->issues, 'code'));
        self::assertNotContains('LOOP_RECURRENCE', array_column($graph->issues, 'code'));
    }
}
