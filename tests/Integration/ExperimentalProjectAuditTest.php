<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Analyzer\ExperimentAnalyzer\DataFlow\DependencyGraph;
use App\Analyzer\ExperimentAnalyzer\DataFlow\Inspection;
use App\Analyzer\ExperimentAnalyzer\SourceIndex;
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
final class ExperimentalProjectAuditTest extends TestCase
{
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
            }
        }

        yield 'every callable under src' => [$audit];
    }
}
