<?php

declare(strict_types=1);

namespace Tests\Unit\Reporter\Experimental;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(\App\Reporter\Experimental\VariableTree::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\App\Analyzer\ExperimentAnalyzer\DataFlow\Dependency::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\App\Analyzer\ExperimentAnalyzer\DataFlow\Occurrence::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\App\Analyzer\ExperimentAnalyzer\DataFlow\Slice::class)]
#[\PHPUnit\Framework\Attributes\UsesNamespace('App\Analyzer\ExperimentAnalyzer')]
#[\PHPUnit\Framework\Attributes\UsesNamespace('App\Action\Experimental')]
final class VariableTreeTest extends TestCase
{
    public function testRenderTerminatesACycleWithAnExplicitReference(): void
    {
        $slice = new \App\Analyzer\ExperimentAnalyzer\DataFlow\Slice('f', '/f.php', \App\Analyzer\Graph\Direction::Uses, ['a'], [new \App\Analyzer\ExperimentAnalyzer\DataFlow\Occurrence('a', 'read', '$a', 1, 1, 1, '$a'), new \App\Analyzer\ExperimentAnalyzer\DataFlow\Occurrence('b', 'write', '$b', 2, 1, 2, '$b')], [new \App\Analyzer\ExperimentAnalyzer\DataFlow\Dependency('a', 'b', 'control', 'truthy')], []);
        $reporter = new \App\Reporter\Experimental\VariableReporter();
        $cycle = new \App\Analyzer\ExperimentAnalyzer\DataFlow\Slice('f', '/f.php', $slice->direction, $slice->roots, $slice->nodes, [new \App\Analyzer\ExperimentAnalyzer\DataFlow\Dependency('a', 'b', 'data'), new \App\Analyzer\ExperimentAnalyzer\DataFlow\Dependency('b', 'a', 'data')], []);
        $text = (new \App\Reporter\Experimental\VariableTree())->render($cycle);
        self::assertStringContainsString('a [already shown]', $text);
        self::assertSame(1, substr_count($text, '[already shown]'));
    }

    public function testDrawMarksAnAlreadySeenOccurrence(): void
    {
        $seen = ['a' => true];
        $lines = [];
        (new \App\Reporter\Experimental\VariableTree())->draw('a', '', '', [], $seen, $lines);
        self::assertSame(['a [already shown]'], $lines);
    }

    public function testRenderKeepsDirectionBranchesAndSharedDependenciesReadable(): void
    {
        $slice = new \App\Analyzer\ExperimentAnalyzer\DataFlow\Slice('Example::render', '/src/Example.php', \App\Analyzer\Graph\Direction::UsedBy, ['a', 'b'], [], [
            new \App\Analyzer\ExperimentAnalyzer\DataFlow\Dependency('a', 'b', 'control', 'falsy'),
            new \App\Analyzer\ExperimentAnalyzer\DataFlow\Dependency('b', 'c', 'data'),
            new \App\Analyzer\ExperimentAnalyzer\DataFlow\Dependency('c', 'a', 'possible-input'),
        ], []);
        self::assertSame("Experimental dependencies: Example::render (used-by)\n/src/Example.php\na\n  [control: falsy] b\n    [data] c\n      [possible-input] a [already shown]\nb [already shown]", (new \App\Reporter\Experimental\VariableTree())->render($slice));
    }
}
