<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Analyzer\ExperimentAnalyzer\DataFlow\Dependency;
use App\Analyzer\ExperimentAnalyzer\DataFlow\DependencyGraph;
use App\Analyzer\ExperimentAnalyzer\DataFlow\Inspection;
use App\Analyzer\ExperimentAnalyzer\DataFlow\InspectionException;
use App\Analyzer\ExperimentAnalyzer\ExperimentAnalyzer;
use App\Analyzer\ExperimentAnalyzer\SourceIndex;
use App\Analyzer\Graph\GraphSnapshot;
use App\Analyzer\NativeAnalyzer\NativeAnalyzer;
use App\Analyzer\PhpFileCollector;
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
    public function testProjectReadsHaveTheManuallyAuditedReachingDefinitions(string $target, string $file, int $line, string $variable, array $expected): void
    {
        $graph = (new ExperimentAnalyzer())->inspect(dirname(__DIR__, 2).'/'.$file, $target);
        $roots = $graph->select($line, $variable);
        $edges = array_filter($graph->edges, static fn (Dependency $edge): bool => in_array($edge->from, $roots, true) && $edge->kind === 'reaching-definition');
        $actual = array_values(array_unique(array_map(static fn (Dependency $edge): array => [$graph->nodes[$edge->to]->line, $graph->nodes[$edge->to]->kind], $edges), SORT_REGULAR));
        sort($actual);
        sort($expected);
        self::assertSame($expected, $actual);
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

    /**
     * @param array{accepted: int, rejected: list<string>, errors: list<string>} $audit
     */
    #[DataProvider('providerWholeProjectAudit')]
    public function testEveryProjectCallableEitherProducesConsistentLocalDefinitionsOrAnExplicitRefusal(array $audit): void
    {
        self::assertGreaterThan(1200, $audit['accepted']);
        self::assertLessThan(30, count($audit['rejected']));
        self::assertSame([], $audit['errors']);
    }

    /**
     * @return iterable<string, array{array{accepted: int, rejected: list<string>, errors: list<string>}}>
     */
    public static function providerWholeProjectAudit(): iterable
    {
        $directory = dirname(__DIR__, 2);
        $files = (new PhpFileCollector())->collect([$directory.'/src'], [], []);
        $index = SourceIndex::of($files, $directory);
        $inspection = new Inspection();
        $audit = ['accepted' => 0, 'rejected' => [], 'errors' => []];
        foreach ($index->sources() as $source) {
            foreach ($inspection->callables($source->statements) as $name => $callable) {
                $text = file_get_contents($source->path);
                assert(is_string($text));

                try {
                    $graph = $inspection->analyze($callable, new DependencyGraph($name, $source->path, $text));
                    ++$audit['accepted'];
                    foreach ($graph->edges as $edge) {
                        if (!isset($graph->nodes[$edge->from], $graph->nodes[$edge->to])) {
                            $audit['errors'][] = $name.': dangling dependency';
                        } elseif ($edge->kind === 'reaching-definition' && $graph->nodes[$edge->from]->variable !== $graph->nodes[$edge->to]->variable) {
                            $audit['errors'][] = $name.': definition belongs to a different variable';
                        } elseif ($edge->from === $edge->to) {
                            $audit['errors'][] = $name.': self dependency';
                        }
                    }
                } catch (InspectionException $error) {
                    $audit['rejected'][] = $name.': '.$error->getMessage();
                }
            }
        }

        yield 'every callable under src' => [$audit];
    }
}
