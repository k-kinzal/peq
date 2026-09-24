<?php

declare(strict_types=1);

namespace Tests\Unit\Analyzer\ExperimentAnalyzer\Flow;

use App\Analyzer\ExperimentAnalyzer\DataFlow\DependencyGraph;
use App\Analyzer\ExperimentAnalyzer\Flow\Expressions;
use App\Analyzer\ExperimentAnalyzer\Flow\LoopTransfers;
use App\Analyzer\ExperimentAnalyzer\Flow\Recording;
use App\Analyzer\ExperimentAnalyzer\Flow\State;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Scalar\Int_;
use PhpParser\Node\Stmt;
use PHPUnit\Framework\Attributes\CoversNamespace;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversNamespace('App\Analyzer\ExperimentAnalyzer')]
final class LoopTransfersTest extends TestCase
{
    public function testConditionUsesTheLastOfMultipleForTests(): void
    {
        $graph = new DependencyGraph('f', '/f.php', '');
        $transfers = new LoopTransfers(new Expressions(new Recording($graph)));
        $node = new Stmt\For_(['cond' => [new Int_(1, ['startLine' => 2]), new Int_(0, ['startLine' => 3])]]);
        $id = $transfers->condition($node, new State(), null);
        self::assertCount(2, $graph->nodes);
        self::assertSame(3, $graph->nodes[$id]->line);
        self::assertFalse($transfers->truth($node));
    }

    public function testTruthDistinguishesAnEmptyForConditionAndForeach(): void
    {
        $graph = new DependencyGraph('f', '/f.php', '');
        $transfers = new LoopTransfers(new Expressions(new Recording($graph)));
        self::assertTrue($transfers->truth(new Stmt\For_()));
        self::assertNull($transfers->truth(new Stmt\Foreach_(new Variable('items'), new Variable('value'))));
        self::assertFalse($transfers->truth(new Stmt\Do_(new Int_(0))));
    }

    public function testBindOverwritesBothNamedIterationTargets(): void
    {
        $graph = new DependencyGraph('f', '/f.php', '');
        $transfers = new LoopTransfers(new Expressions(new Recording($graph)));
        $condition = $graph->record(new Variable('items'), 'iteration-test');
        $state = new State(controls: [$condition => 'iterate']);
        $transfers->bind(new Stmt\Foreach_(new Variable('items'), new Variable('value'), ['keyVar' => new Variable('key')]), $state, $condition);
        self::assertSame(['$key', '$value'], array_keys($state->definitions));
        self::assertContains('iteration-value', array_column($graph->nodes, 'kind'));
        self::assertSame([], $graph->issues);
        self::assertContains(false, $graph->scalars);
    }

    public function testJumpPreservesTheLiteralDepthAndAbruptCondition(): void
    {
        $graph = new DependencyGraph('f', '/f.php', '');
        $transfers = new LoopTransfers(new Expressions(new Recording($graph)));
        $break = $transfers->jump(new Stmt\Break_(new Int_(2)), new State());
        self::assertNull($break->normal);
        self::assertSame([2], array_keys($break->breaks));
        self::assertSame([], $break->continues);
        self::assertSame(['taken'], array_values($break->breaks[2][0]->controls));
        $continue = $transfers->jump(new Stmt\Continue_(), new State());
        self::assertSame([1], array_keys($continue->continues));
        self::assertSame([], $continue->breaks);
    }

    public function testJumpRejectsDynamicOrInvalidDepthsAsUnknown(): void
    {
        $graph = new DependencyGraph('f', '/f.php', '');
        $transfers = new LoopTransfers(new Expressions(new Recording($graph)));
        $result = $transfers->jump(new Stmt\Break_(new Variable('depth')), new State());
        self::assertNotNull($result->normal);
        self::assertSame(['DYNAMIC_JUMP'], array_column($graph->issues, 'code'));
    }
}
