<?php

declare(strict_types=1);

namespace Tests\Unit\Analyzer\ExperimentAnalyzer\Flow;

use App\Analyzer\ExperimentAnalyzer\DataFlow\Dependency;
use App\Analyzer\ExperimentAnalyzer\DataFlow\DependencyGraph;
use App\Analyzer\ExperimentAnalyzer\DataFlow\Inspection;
use App\Analyzer\ExperimentAnalyzer\Flow\Recording;
use App\Analyzer\ExperimentAnalyzer\Flow\State;
use PhpParser\Node\Stmt\Function_;
use PhpParser\ParserFactory;
use PHPUnit\Framework\Attributes\CoversNamespace;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversNamespace('App\Analyzer\ExperimentAnalyzer')]
final class RecordingTest extends TestCase
{
    public function testValueConnectsInputsAndBranchConditions(): void
    {
        $parsed = (new ParserFactory())->createForNewestSupportedVersion()->parse('<?php $a;');
        self::assertNotNull($parsed);
        self::assertInstanceOf(\PhpParser\Node\Stmt\Expression::class, $parsed[0]);
        $graph = new DependencyGraph('f', '/f.php', '<?php $a;');
        $id = (new Recording($graph))->value($parsed[0]->expr, 'read', ['input'], new State(controls: ['condition' => 'falsy']));
        self::assertSame(['data', 'control'], array_values(array_map(static fn (Dependency $edge): string => $edge->kind, $graph->edges)));
        self::assertSame($id, array_values($graph->edges)[0]->from);
    }

    public function testReadConnectsOnlyTheCurrentDefinitions(): void
    {
        $source = <<<'SOURCE'
            <?php function f($a) { return $a; }
            SOURCE;
        $parsed = (new ParserFactory())->createForNewestSupportedVersion()->parse($source);
        self::assertNotNull($parsed);
        self::assertInstanceOf(Function_::class, $parsed[0]);
        $graph = (new Inspection())->analyze($parsed[0], new DependencyGraph('f', '/f.php', $source));
        $edges = array_values(array_filter($graph->edges, static fn (Dependency $edge): bool => $edge->kind === 'reaching-definition'));
        self::assertCount(1, $edges);
        self::assertSame('parameter', $graph->nodes[$edges[0]->to]->kind);
    }

    public function testWriteReplacesTheWholeVariableDefinition(): void
    {
        $source = <<<'SOURCE'
            <?php function f($a) { $a = 1; return $a; }
            SOURCE;
        $parsed = (new ParserFactory())->createForNewestSupportedVersion()->parse($source);
        self::assertNotNull($parsed);
        self::assertInstanceOf(Function_::class, $parsed[0]);
        $graph = (new Inspection())->analyze($parsed[0], new DependencyGraph('f', '/f.php', $source));
        $edges = array_values(array_filter($graph->edges, static fn (Dependency $edge): bool => $edge->kind === 'reaching-definition'));
        self::assertCount(1, $edges);
        self::assertSame('write', $graph->nodes[$edges[0]->to]->kind);
    }
}
