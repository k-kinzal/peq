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
final class SwitchesTest extends TestCase
{
    public function testReadKeepsUnmatchedPathWhenThereIsNoDefault(): void
    {
        $source = <<<'SOURCE'
            <?php function f($flag) { $a = 0; switch ($flag) { case 1: $a = 1; break; } return $a; }
            SOURCE;
        $parsed = (new ParserFactory())->createForNewestSupportedVersion()->parse($source);
        self::assertNotNull($parsed);
        self::assertInstanceOf(Function_::class, $parsed[0]);
        $graph = (new Inspection())->analyze($parsed[0], new DependencyGraph('f', '/f.php', $source));
        $reads = array_values(array_filter($graph->nodes, static fn (Occurrence $node): bool => $node->kind === 'read' && $node->variable === '$a'));
        $definitions = array_values(array_filter($graph->edges, static fn (Dependency $edge): bool => $edge->from === $reads[0]->id && $edge->kind === 'reaching-definition'));
        self::assertCount(2, $definitions);
    }

    public function testSelectTestsEveryCaseBeforeChoosingTheDefault(): void
    {
        $source = '<?php switch ($flag) { default: break; case 1: break; }';
        $parsed = (new ParserFactory())->createForNewestSupportedVersion()->parse($source);
        self::assertNotNull($parsed);
        self::assertInstanceOf(\PhpParser\Node\Stmt\Switch_::class, $parsed[0]);
        $graph = new DependencyGraph('f', '/f.php', $source);
        $state = new \App\Analyzer\ExperimentAnalyzer\Flow\State();
        $expressions = new \App\Analyzer\ExperimentAnalyzer\Flow\Expressions(new \App\Analyzer\ExperimentAnalyzer\Flow\Recording($graph));
        $subject = $expressions->read($parsed[0]->cond, $state);
        [$entries, $exits] = (new \App\Analyzer\ExperimentAnalyzer\Flow\Switches(new \App\Analyzer\ExperimentAnalyzer\Flow\Statements($expressions)))->select($parsed[0], $state, $subject);
        self::assertSame([1, 0], array_keys($entries));
        self::assertSame(['match'], array_values($entries[1]->controls));
        self::assertSame(['no-match'], array_values($entries[0]->controls));
        self::assertSame([], $exits);
    }
}
