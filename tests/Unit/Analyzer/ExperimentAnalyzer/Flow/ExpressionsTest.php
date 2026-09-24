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
use PhpParser\Node\Stmt\Function_;
use PhpParser\ParserFactory;
use PHPUnit\Framework\Attributes\CoversNamespace;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversNamespace('App\Analyzer\ExperimentAnalyzer')]
final class ExpressionsTest extends TestCase
{
    public function testReadKeepsNestedExpressionsWithTheSameStartSeparate(): void
    {
        $source = <<<'SOURCE'
            <?php function f(int $a, int $b, int $c) { return $a + $b + $c; }
            SOURCE;
        $parsed = (new ParserFactory())->createForNewestSupportedVersion()->parse($source);
        self::assertNotNull($parsed);
        self::assertInstanceOf(Function_::class, $parsed[0]);
        $graph = (new Inspection())->analyze($parsed[0], new DependencyGraph('f', '/f.php', $source));
        $operations = array_values(array_map(static fn (Occurrence $node): string => $node->text, array_filter($graph->nodes, static fn (Occurrence $node): bool => $node->kind === 'expression')));
        self::assertSame(['$a + $b', '$a + $b + $c'], $operations);
        self::assertSame([], array_values(array_filter($graph->edges, static fn (Dependency $edge): bool => $edge->from === $edge->to)));
    }

    public function testChildrenDoNotExecuteAnAnonymousClassBody(): void
    {
        $source = <<<'SOURCE'
            <?php function f($a) { return new class($a) { function inner() { $a = 2; } }; }
            SOURCE;
        $parsed = (new ParserFactory())->createForNewestSupportedVersion()->parse($source);
        self::assertNotNull($parsed);
        self::assertInstanceOf(Function_::class, $parsed[0]);
        $graph = (new Inspection())->analyze($parsed[0], new DependencyGraph('f', '/f.php', $source));
        self::assertSame(['OPAQUE_CALL'], array_column($graph->issues, 'code'));
        self::assertSame([], array_values(array_filter($graph->nodes, static fn (Occurrence $node): bool => $node->kind === 'write')));
    }

    public function testBoundaryRecordsPropertyInputsAsOpaque(): void
    {
        $source = <<<'SOURCE'
            <?php function f($object) { return $object->value; }
            SOURCE;
        $parsed = (new ParserFactory())->createForNewestSupportedVersion()->parse($source);
        self::assertNotNull($parsed);
        self::assertInstanceOf(Function_::class, $parsed[0]);
        $graph = (new Inspection())->analyze($parsed[0], new DependencyGraph('f', '/f.php', $source));
        self::assertSame(['UNSUPPORTED_EXPRESSION'], array_column($graph->issues, 'code'));
    }

    public function testValuesMarksThrowAsUnsolvedContinuation(): void
    {
        $source = '<?php throw new Exception();';
        $parsed = (new ParserFactory())->createForNewestSupportedVersion()->parse($source);
        self::assertNotNull($parsed);
        self::assertInstanceOf(\PhpParser\Node\Stmt\Expression::class, $parsed[0]);
        $state = new State();
        $expressions = new Expressions(new Recording(new DependencyGraph('f', '/f.php', $source)));
        self::assertCount(1, $expressions->values($parsed[0]->expr, $state));
        self::assertTrue($state->reachable);
        self::assertNotEmpty($expressions->recording->graph->issues);
    }

    public function testReadDoesNotApplyACallWhoseArgumentThrows(): void
    {
        $source = '<?php function f() { return preg_match(throw new Exception(), "", $out); }';
        $parsed = (new ParserFactory())->createForNewestSupportedVersion()->parse($source);
        self::assertNotNull($parsed);
        self::assertInstanceOf(Function_::class, $parsed[0]);
        $graph = (new Inspection())->analyze($parsed[0], new DependencyGraph('f', '/f.php', $source));
        self::assertSame([], array_values(array_filter($graph->nodes, static fn (Occurrence $node): bool => $node->kind === 'call-write')));
        self::assertSame(['OPAQUE_CALL'], array_column($graph->issues, 'code'));
    }
}
