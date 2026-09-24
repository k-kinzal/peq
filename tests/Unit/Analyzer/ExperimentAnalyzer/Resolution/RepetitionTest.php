<?php

declare(strict_types=1);

namespace Tests\Unit\Analyzer\ExperimentAnalyzer\Resolution;

use App\Analyzer\ExperimentAnalyzer\DataFlow\DependencyGraph;
use App\Analyzer\ExperimentAnalyzer\Flow\State;
use App\Analyzer\ExperimentAnalyzer\Resolution\Repetition;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Stmt\While_;
use PHPUnit\Framework\Attributes\CoversNamespace;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversNamespace('App\Analyzer\ExperimentAnalyzer')]
final class RepetitionTest extends TestCase
{
    public function testOpenKeepsUnchangedInputsAndMarksOnlyChangingDefinitions(): void
    {
        $graph = new DependencyGraph('f', '/f.php', '');
        $original = $graph->record(new Variable('x'), 'parameter', '$x');
        $changed = $graph->record(new Variable('x'), 'write', '$x');
        $state = new State(['$x' => [$original => true], '$fixed' => [$original => true]]);
        $back = new State(['$x' => [$changed => true], '$fixed' => [$original => true]]);
        $inputs = (new Repetition($graph))->open(new While_(new Variable('flag')), $state, [$back]);
        self::assertSame(['$x'], array_keys($inputs));
        self::assertSame([$original => true], $state->definitions['$fixed']);
        self::assertSame([$inputs['$x'] => true], $state->definitions['$x']);
        self::assertSame(['LOOP_RECURRENCE'], array_column($graph->issues, 'code'));
        self::assertSame(['initial-definition', 'control'], array_column($graph->edges, 'kind'));
    }

    public function testCloseRetainsBackDefinitionsAndTheirGuardsWithoutSelfEdges(): void
    {
        $graph = new DependencyGraph('f', '/f.php', '');
        $node = new While_(new Variable('flag'));
        $iteration = $graph->record($node, 'loop-iteration');
        $input = $graph->record($node, 'loop-input', '$x');
        $write = $graph->record(new Variable('x'), 'write', '$x');
        $condition = $graph->record(new Variable('flag'), 'read', '$flag');
        $back = new State(['$x' => [$input => true, $write => true]], [$iteration => 'first-or-later-iteration', $condition => 'truthy']);
        (new Repetition($graph))->close($node, ['$x' => $input], [$back]);
        self::assertSame(['loop-carried', 'repeat'], array_column($graph->edges, 'kind'));
        self::assertSame([$write, $condition], array_column($graph->edges, 'to'));
        self::assertSame([null, 'truthy'], array_column($graph->edges, 'branch'));
    }
}
