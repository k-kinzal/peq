<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Analyzer\ExperimentAnalyzer\ExperimentAnalyzer;
use App\Analyzer\Graph\GraphSnapshot;
use App\Analyzer\NativeAnalyzer\NativeAnalyzer;
use PHPUnit\Framework\Attributes\CoversNamespace;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversNamespace('App\Analyzer\ExperimentAnalyzer')]
#[Large]
final class ExperimentalSelfAnalysisTest extends TestCase
{
    /**
     * @param list<array{int, string}> $expected
     */
    #[DataProvider('providerAuditedOccurrences')]
    public function testProjectReadsRetainAuditedSourceSitesAndDeclareUncertainty(string $target, string $file, int $line, string $variable, array $expected): void
    {
        $graph = (new ExperimentAnalyzer())->inspect(dirname(__DIR__, 2).'/'.$file, $target);
        $slice = \App\Analyzer\ExperimentAnalyzer\DataFlow\Slice::of($graph, $graph->select($line, $variable), \App\Analyzer\Graph\Direction::Uses, null);
        self::assertFalse($slice->analysis->complete);
        self::assertNotEmpty($slice->analysis->issues);
        $lines = array_map(static fn (\App\Analyzer\ExperimentAnalyzer\Structure\Site $site): int => $site->source->line, $slice->structure);
        self::assertSame([], array_values(array_diff(array_column($expected, 0), $lines)));
    }

    /**
     * @return iterable<string, array{string, string, int, string, list<array{int, string}>}>
     */
    public static function providerAuditedOccurrences(): iterable
    {
        yield 'configuration starts empty or uses the final overlay' => ['App\Config\ConfigLoader::load', 'src/Config/ConfigLoader.php', 44, 'merged', [[39, 'write'], [41, 'write']]];

        yield 'an overlay reads the previous iteration or the initial map' => ['App\Config\ConfigLoader::load', 'src/Config/ConfigLoader.php', 41, 'merged', [[39, 'write'], [41, 'write']]];

        yield 'the foreach reader is assigned by the loop' => ['App\Config\ConfigLoader::load', 'src/Config/ConfigLoader.php', 41, 'reader', [[40, 'write']]];

        yield 'regex output replaces the empty array' => ['App\Gql\Lexing\SourceCursor::capture', 'src/Gql/Lexing/SourceCursor.php', 111, 'matched', [[107, 'call-write']]];

        yield 'version components come from preg_match output' => ['App\Config\PhpVersion::parse', 'src/Config/PhpVersion.php', 105, 'parts', [[101, 'call-write']]];

        yield 'array_pop writes the previous block' => ['App\Reporter\Diagram\Layout\RowPlacement::fit', 'src/Reporter/Diagram/Layout/RowPlacement.php', 105, 'previous', [[104, 'write']]];

        yield 'the inner loop carries the pooled block' => ['App\Reporter\Diagram\Layout\RowPlacement::fit', 'src/Reporter/Diagram/Layout/RowPlacement.php', 105, 'block', [[102, 'write'], [105, 'write']]];

        yield 'spacing still refers to the parameter inside nested loops' => ['App\Reporter\Diagram\Layout\RowPlacement::fit', 'src/Reporter/Diagram/Layout/RowPlacement.php', 114, 'spacing', [[98, 'parameter']]];
    }

    public function testTheExperimentalSymbolPipelineMatchesNativeOnTheWholeProject(): void
    {
        $path = dirname(__DIR__, 2).'/src';
        $expected = GraphSnapshot::of((new NativeAnalyzer())->analyze($path));
        $actual = GraphSnapshot::of((new ExperimentAnalyzer())->analyze($path));
        self::assertSame($expected->fingerprint(), $actual->fingerprint(), $actual->differenceFrom($expected)->describe());
    }

    public function testProjectAttributeSearchDoesNotCertifyAnUnconditionalFalseReturn(): void
    {
        $path = dirname(__DIR__, 2).'/src/Analyzer/Graph/Declaration/SymbolDeclaration.php';
        $graph = (new ExperimentAnalyzer())->inspect($path, 'App\Analyzer\Graph\Declaration\SymbolDeclaration::hasAttribute');
        $backward = \App\Analyzer\ExperimentAnalyzer\DataFlow\Slice::of($graph, $graph->select(84, null), \App\Analyzer\Graph\Direction::Uses, null);
        $forward = \App\Analyzer\ExperimentAnalyzer\DataFlow\Slice::of($graph, $graph->select(75, 'name'), \App\Analyzer\Graph\Direction::UsedBy, null);
        self::assertFalse($backward->analysis->complete);
        self::assertContains('FOREACH_PROTOCOL', array_column($backward->analysis->issues, 'code'));
        self::assertContains(78, array_column($backward->nodes, 'line'));
        self::assertContains(84, array_column($forward->nodes, 'line'));
    }
}
