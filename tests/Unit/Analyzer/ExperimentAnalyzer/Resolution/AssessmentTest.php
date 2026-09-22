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
final class AssessmentTest extends TestCase
{
    public function testOfDistinguishesRuntimeInputFromUnsolvedAnalysisAndBounds(): void
    {
        $source = "<?php function f(\$input) {\n\$x = \$input;\nreturn \$x;\n}";
        $parsed = (new ParserFactory())->createForNewestSupportedVersion()->parse($source);
        self::assertNotNull($parsed);
        self::assertInstanceOf(Function_::class, $parsed[0]);
        $graph = (new Inspection())->analyze($parsed[0], new DependencyGraph('f', '/f.php', $source));
        $full = Slice::of($graph, $graph->select(3, 'x'), Direction::Uses, null);
        $bounded = Slice::of($graph, $graph->select(3, 'x'), Direction::Uses, 0);
        self::assertSame('input', $full->analysis->status);
        self::assertTrue($full->analysis->complete);
        self::assertSame('truncated', $bounded->analysis->status);
        self::assertFalse($bounded->analysis->complete);
        self::assertSame($full->structure, $bounded->structure);
        self::assertNotEmpty($bounded->analysis->frontier);
    }

    public function testPropagateCrossesCyclesWithoutLosingTheBoundary(): void
    {
        $graph = new DependencyGraph('f', '', '');
        $graph->connect('a', 'b');
        $graph->connect('b', 'a');
        $graph->connect('c', 'a');
        self::assertSame(['b' => true, 'a' => true, 'c' => true], \App\Analyzer\ExperimentAnalyzer\Resolution\Assessment::propagate($graph, ['b' => true]));
    }
}
