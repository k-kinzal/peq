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

    public function testRecordPreservesMultilineSourceLocationsAndText(): void
    {
        $source = "<?php\n  \$a +\n    \$b;\n";
        $parsed = (new ParserFactory())->createForNewestSupportedVersion()->parse($source);
        self::assertNotNull($parsed);
        self::assertInstanceOf(\PhpParser\Node\Stmt\Expression::class, $parsed[0]);
        $graph = new DependencyGraph('f', '/f.php', $source);
        $id = $graph->record($parsed[0]->expr, 'expression');
        $node = $graph->nodes[$id];
        self::assertSame([2, 3, 3, '$a +'."\n".'    $b', 'expression', null], [$node->line, $node->column, $node->endLine, $node->text, $node->kind, $node->variable]);
        self::assertSame($id, $graph->record($parsed[0]->expr, 'expression'));
        self::assertCount(1, $graph->nodes);
    }

    public function testSelectExcludesSyntheticDefinitionsAndOtherLinesAndVariables(): void
    {
        $graph = new DependencyGraph('f', '/f.php', '');
        $graph->nodes['unbound'] = new Occurrence('unbound', 'unbound', '$a', 4, 3, 4, '$a');
        $graph->nodes['receiver'] = new Occurrence('receiver', 'receiver', '$this', 4, 3, 4, '$this');
        $graph->nodes['read'] = new Occurrence('read', 'read', '$a', 4, 3, 4, '$a');
        $graph->nodes['other'] = new Occurrence('other', 'read', '$b', 4, 8, 4, '$b');
        $graph->nodes['later'] = new Occurrence('later', 'read', '$a', 5, 3, 5, '$a');
        self::assertSame(['read', 'other'], $graph->select(4, null));
        self::assertSame(['read'], $graph->select(4, 'a'));
        $this->expectException(\App\Analyzer\ExperimentAnalyzer\DataFlow\InspectionException::class);
        $this->expectExceptionMessage('No $a occurrence at line 4, column 2 in f.');
        $graph->select(4, '$a', 2);
    }

    public function testLocationDoesNotInsertAnAnalysisNode(): void
    {
        $graph = new DependencyGraph('f', '/f.php', '<?php $a;');
        $node = new \PhpParser\Node\Expr\Variable('a', ['startLine' => 1, 'endLine' => 1, 'startFilePos' => 6, 'endFilePos' => 7]);
        self::assertSame('$a', $graph->location($node, 'syntax', '$a')->text);
        self::assertSame([], $graph->nodes);
    }

    public function testFingerprintUsesTheActualParsedSource(): void
    {
        $graph = new DependencyGraph('f', '/missing.php', 'abc');
        self::assertSame('ba7816bf8f01cfea414140de5dae2223b00361a396177a9cb410ff61f20015ad', $graph->fingerprint());
    }

    public function testEmptyCopyKeepsSourceIdentityWithoutSharingAnalysisState(): void
    {
        $graph = new DependencyGraph('C::f', '/f.php', 'abc');
        $graph->nodes['x'] = new Occurrence('x', 'read', '$x', 1, 1, 1, '$x');
        $copy = $graph->emptyCopy();
        self::assertSame('C::f', $copy->target);
        self::assertSame('/f.php', $copy->file);
        self::assertSame($graph->fingerprint(), $copy->fingerprint());
        self::assertSame([], $copy->nodes);
    }

    public function testSelectableExcludesSyntheticLoopInputsAndLocalNamesFromProperties(): void
    {
        $graph = new DependencyGraph('C::$x', '/f.php', '');
        self::assertFalse($graph->selectable(new Occurrence('local', 'read', '$x', 1, 1, 1, '$x')));
        self::assertTrue($graph->selectable(new Occurrence('property', 'property-access', '$x', 1, 1, 1, '$this->x')));
        $local = new DependencyGraph('C::f', '/f.php', '');
        self::assertFalse($local->selectable(new Occurrence('loop', 'loop-input', '$x', 1, 1, 1, 'while')));
    }

    public function testAdoptCommitsOnlyTheSpeculativeAnalysis(): void
    {
        $graph = new DependencyGraph('f', '/f.php', 'abc');
        $trial = clone $graph;
        $trial->nodes['x'] = new Occurrence('x', 'read', '$x', 1, 1, 1, '$x');
        $trial->connect('x', 'y');
        $trial->scalars['x'] = true;
        $trial->testedOrigins['x'] = true;
        $trial->diagnostics['test'] = 'test';
        $trial->issues['x'] = new \App\Analyzer\ExperimentAnalyzer\Resolution\Issue('TEST', 'x', 'test', 'test', $trial->nodes['x']);
        $graph->adopt($trial);
        self::assertSame($trial->nodes, $graph->nodes);
        self::assertSame($trial->edges, $graph->edges);
        self::assertSame($trial->scalars, $graph->scalars);
        self::assertSame($trial->testedOrigins, $graph->testedOrigins);
        self::assertSame($trial->diagnostics, $graph->diagnostics);
        self::assertSame($trial->issues, $graph->issues);
        self::assertSame('ba7816bf8f01cfea414140de5dae2223b00361a396177a9cb410ff61f20015ad', $graph->fingerprint());
    }
}
