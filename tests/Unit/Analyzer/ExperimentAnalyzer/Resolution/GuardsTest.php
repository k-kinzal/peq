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
final class GuardsTest extends TestCase
{
    public function testJoinPreservesTheDisjunctionAfterNestedEarlyReturns(): void
    {
        $source = "<?php function f(\$a, \$b) {\nif (\$a) { if (\$b) { return 0; } }\nreturn 1;\n}";
        $parsed = (new ParserFactory())->createForNewestSupportedVersion()->parse($source);
        self::assertNotNull($parsed);
        self::assertInstanceOf(Function_::class, $parsed[0]);
        $graph = (new Inspection())->analyze($parsed[0], new DependencyGraph('f', '/f.php', $source));
        $slice = Slice::of($graph, $graph->select(3, null), Direction::Uses, null);
        self::assertTrue($slice->analysis->complete);
        self::assertContains('any-path', array_column($slice->nodes, 'kind'));
        self::assertSame(['truthy', 'falsy', 'falsy'], array_values(array_map(static fn (\App\Analyzer\ExperimentAnalyzer\DataFlow\Dependency $edge): ?string => $edge->branch, array_filter($graph->edges, static fn (\App\Analyzer\ExperimentAnalyzer\DataFlow\Dependency $edge): bool => str_starts_with($edge->from, 'all-conditions-') && $edge->kind === 'control'))));
    }

    public function testSimplifyEliminatesComplementaryAndSubsumedPaths(): void
    {
        $guards = new \App\Analyzer\ExperimentAnalyzer\Resolution\Guards();
        self::assertSame([[]], $guards->simplify([['a' => 'truthy'], ['a' => 'falsy']]));
        self::assertSame([['a' => 'truthy']], $guards->simplify([['a' => 'truthy', 'b' => 'falsy'], ['a' => 'truthy']]));
    }

    public function testContinueWithRetainsOnlyContinuingPaths(): void
    {
        $state = new \App\Analyzer\ExperimentAnalyzer\Flow\State();
        (new \App\Analyzer\ExperimentAnalyzer\Resolution\Guards())->continueWith(new \PhpParser\Node\Scalar\Int_(1), $state, [new \App\Analyzer\ExperimentAnalyzer\Flow\State(reachable: false)], new DependencyGraph('f', '', ''));
        self::assertFalse($state->reachable);
    }
}
