<?php

declare(strict_types=1);

namespace Tests\Unit\Analyzer\ExperimentAnalyzer\Flow;

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
final class CapturesTest extends TestCase
{
    public function testReadDoesNotCaptureNestedParameters(): void
    {
        $source = <<<'SOURCE'
            <?php function f($a) { return fn ($x) => fn ($y) => $a + $x + $y; }
            SOURCE;
        $parsed = (new ParserFactory())->createForNewestSupportedVersion()->parse($source);
        self::assertNotNull($parsed);
        self::assertInstanceOf(Function_::class, $parsed[0]);
        $graph = (new Inspection())->analyze($parsed[0], new DependencyGraph('f', '/f.php', $source));
        $captures = array_values(array_map(static fn (Occurrence $node): ?string => $node->variable, array_filter($graph->nodes, static fn (Occurrence $node): bool => $node->kind === 'capture')));
        self::assertSame(['$a'], $captures);
    }
}
