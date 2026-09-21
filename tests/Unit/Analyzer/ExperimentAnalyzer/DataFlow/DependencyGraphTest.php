<?php

declare(strict_types=1);

namespace Tests\Unit\Analyzer\ExperimentAnalyzer\DataFlow;

use App\Analyzer\ExperimentAnalyzer\DataFlow\DependencyGraph;
use App\Analyzer\ExperimentAnalyzer\DataFlow\Occurrence;
use PhpParser\ParserFactory;
use PHPUnit\Framework\Attributes\CoversNamespace;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversNamespace('App\Analyzer\ExperimentAnalyzer')]
final class DependencyGraphTest extends TestCase
{
    public function testRecordSeparatesNestedExpressionSpans(): void
    {
        $source = '<?php $a + $b + $c;';
        $parsed = (new ParserFactory())->createForNewestSupportedVersion()->parse($source);
        self::assertNotNull($parsed);
        self::assertInstanceOf(\PhpParser\Node\Stmt\Expression::class, $parsed[0]);
        self::assertInstanceOf(\PhpParser\Node\Expr\BinaryOp\Plus::class, $parsed[0]->expr);
        $graph = new DependencyGraph('f', '/f.php', $source);
        $outer = $graph->record($parsed[0]->expr, 'expression');
        $inner = $graph->record($parsed[0]->expr->left, 'expression');
        self::assertNotSame($outer, $inner);
        self::assertSame(7, $graph->nodes[$outer]->column);
    }

    public function testConnectKeepsDifferentKindsAndBranchPolarities(): void
    {
        $graph = new DependencyGraph('f', '/f.php', '');
        $graph->connect('a', 'b', 'data');
        $graph->connect('a', 'b', 'data');
        $graph->connect('a', 'b', 'control', 'truthy');
        $graph->connect('a', 'b', 'control', 'falsy');
        self::assertCount(3, $graph->edges);
    }

    public function testDiagnoseDeduplicatesLoopDiagnostics(): void
    {
        $graph = new DependencyGraph('f', '/f.php', '');
        $node = new \PhpParser\Node\Expr\Variable('a', ['startLine' => 3]);
        $graph->diagnose($node, 'Call boundary.');
        $graph->diagnose($node, 'Call boundary.');
        self::assertSame(['Line 3: Call boundary.'], array_values($graph->diagnostics));
    }

    public function testSelectUsesTheVariableAndByteColumn(): void
    {
        $graph = new DependencyGraph('f', '/f.php', '');
        $graph->nodes['first'] = new Occurrence('first', 'read', '$a', 4, 3, 4, '$a');
        $graph->nodes['second'] = new Occurrence('second', 'write', '$a', 4, 10, 4, '$a');
        self::assertSame(['second'], $graph->select(4, 'a', 10));
        self::assertSame(['first', 'second'], $graph->select(4, '$a'));
    }
}
