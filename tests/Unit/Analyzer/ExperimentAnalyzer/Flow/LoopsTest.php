<?php

declare(strict_types=1);

namespace Tests\Unit\Analyzer\ExperimentAnalyzer\Flow;

use App\Analyzer\ExperimentAnalyzer\DataFlow\Dependency;
use App\Analyzer\ExperimentAnalyzer\DataFlow\DependencyGraph;
use App\Analyzer\ExperimentAnalyzer\DataFlow\Inspection;
use App\Analyzer\ExperimentAnalyzer\DataFlow\Occurrence;
use App\Analyzer\ExperimentAnalyzer\Flow\Expressions;
use App\Analyzer\ExperimentAnalyzer\Flow\Recording;
use App\Analyzer\ExperimentAnalyzer\Flow\State;
use App\Analyzer\ExperimentAnalyzer\Flow\Statements;
use PhpParser\Node\Stmt\Function_;
use PhpParser\ParserFactory;
use PHPUnit\Framework\Attributes\CoversNamespace;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversNamespace('App\Analyzer\ExperimentAnalyzer')]
final class LoopsTest extends TestCase
{
    public function testReadFindsLoopCarriedDefinitions(): void
    {
        $source = <<<'SOURCE'
            <?php function f($flag) { $a = 0; while ($flag) { $b = $a; $a = 1; } return $b; }
            SOURCE;
        $parsed = (new ParserFactory())->createForNewestSupportedVersion()->parse($source);
        self::assertNotNull($parsed);
        self::assertInstanceOf(Function_::class, $parsed[0]);
        $graph = (new Inspection())->analyze($parsed[0], new DependencyGraph('f', '/f.php', $source));
        $reads = array_values(array_filter($graph->nodes, static fn (Occurrence $node): bool => $node->kind === 'read' && $node->variable === '$a'));
        self::assertCount(2, array_filter($graph->edges, static fn (Dependency $edge): bool => $edge->from === $reads[0]->id && $edge->kind === 'reaching-definition'));
    }

    public function testInitializeDefinesTheForCounter(): void
    {
        $source = '<?php for ($i = 0; $i < 2; $i++) {}';
        $parsed = (new ParserFactory())->createForNewestSupportedVersion()->parse($source);
        self::assertNotNull($parsed);
        self::assertInstanceOf(\PhpParser\Node\Stmt\For_::class, $parsed[0]);
        $graph = new DependencyGraph('f', '/f.php', $source);
        $state = new State();
        $loops = new \App\Analyzer\ExperimentAnalyzer\Flow\Loops(new Statements(new Expressions(new Recording($graph))));
        self::assertNull($loops->initialize($parsed[0], $state));
        self::assertArrayHasKey('$i', $state->definitions);
    }

    public function testConditionRecordsItsInputs(): void
    {
        $source = '<?php for ($i = 0; $i < 2; $i++) {}';
        $parsed = (new ParserFactory())->createForNewestSupportedVersion()->parse($source);
        self::assertNotNull($parsed);
        self::assertInstanceOf(\PhpParser\Node\Stmt\For_::class, $parsed[0]);
        $graph = new DependencyGraph('f', '/f.php', $source);
        $state = new State();
        $loops = new \App\Analyzer\ExperimentAnalyzer\Flow\Loops(new Statements(new Expressions(new Recording($graph))));
        self::assertNotNull($loops->condition($parsed[0], $state, null));
        self::assertCount(3, $graph->nodes);
    }

    public function testIterationLeavesAForEnvironmentUntouched(): void
    {
        $source = '<?php for ($i = 0; $i < 2; $i++) {}';
        $parsed = (new ParserFactory())->createForNewestSupportedVersion()->parse($source);
        self::assertNotNull($parsed);
        self::assertInstanceOf(\PhpParser\Node\Stmt\For_::class, $parsed[0]);
        $graph = new DependencyGraph('f', '/f.php', $source);
        $state = new State();
        $loops = new \App\Analyzer\ExperimentAnalyzer\Flow\Loops(new Statements(new Expressions(new Recording($graph))));
        $loops->iteration($parsed[0], $state, null);
        self::assertSame([], $state->definitions);
    }

    public function testAdvanceWritesTheUpdatedForCounter(): void
    {
        $source = '<?php for ($i = 0; $i < 2; $i++) {}';
        $parsed = (new ParserFactory())->createForNewestSupportedVersion()->parse($source);
        self::assertNotNull($parsed);
        self::assertInstanceOf(\PhpParser\Node\Stmt\For_::class, $parsed[0]);
        $graph = new DependencyGraph('f', '/f.php', $source);
        $state = new State();
        $loops = new \App\Analyzer\ExperimentAnalyzer\Flow\Loops(new Statements(new Expressions(new Recording($graph))));
        $loops->advance($parsed[0], $state, null);
        self::assertArrayHasKey('$i', $state->definitions);
    }

    public function testTruthDoesNotGuessTheCounterValue(): void
    {
        $source = '<?php for ($i = 0; $i < 2; $i++) {}';
        $parsed = (new ParserFactory())->createForNewestSupportedVersion()->parse($source);
        self::assertNotNull($parsed);
        self::assertInstanceOf(\PhpParser\Node\Stmt\For_::class, $parsed[0]);
        $graph = new DependencyGraph('f', '/f.php', $source);
        $state = new State();
        $loops = new \App\Analyzer\ExperimentAnalyzer\Flow\Loops(new Statements(new Expressions(new Recording($graph))));
        self::assertNull($loops->truth($parsed[0]));
    }
}
