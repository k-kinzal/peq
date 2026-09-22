<?php

declare(strict_types=1);

namespace Tests\External;

use App\Analyzer\ExperimentAnalyzer\DataFlow\DependencyGraph;
use App\Analyzer\ExperimentAnalyzer\DataFlow\Inspection;
use App\Analyzer\ExperimentAnalyzer\SourceIndex;
use App\Analyzer\PhpFileCollector;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Structural validation of every declared callable in the WordPress 7.1.1 archive.
 *
 * See docs/wordpress-variable-validation.md for download and execution instructions.
 * These checks complement, rather than replace, the manually audited expectations.
 *
 * @internal
 */
#[CoversNothing]
#[Large]
final class WordPressCorpusTest extends TestCase
{
    /**
     * @param array{files: int, parsed: int, accepted: int, rejected: int, violations: list<string>} $audit
     */
    #[DataProvider('providerCorpus')]
    public function testEveryCallablePreservesItsSyntaxAndConsistentGraph(array $audit): void
    {
        self::assertSame(1512, $audit['files']);
        self::assertSame(1512, $audit['parsed']);
        self::assertSame(11941, $audit['accepted']);
        self::assertSame(0, $audit['rejected']);
        self::assertSame([], $audit['violations']);
    }

    /**
     * @return iterable<string, array{array{files: int, parsed: int, accepted: int, rejected: int, violations: list<string>}}>
     *
     * @throws RuntimeException If the pinned source archive is missing or unreadable
     */
    public static function providerCorpus(): iterable
    {
        $root = dirname(__DIR__, 2).'/var/cache/wordpress-validation/7.1.1/wordpress';
        if (!is_dir($root)) {
            throw new RuntimeException('Download the pinned WordPress archive as described in docs/wordpress-variable-validation.md.');
        }
        $files = (new PhpFileCollector())->collect([$root], [], []);
        $index = SourceIndex::of($files, $root, 70400);
        $inspection = new Inspection();
        $audit = ['files' => count($files), 'parsed' => count($index->sources()), 'accepted' => 0, 'rejected' => 0, 'violations' => []];
        foreach ($index->sources() as $source) {
            $text = file_get_contents($source->path);
            if ($text === false) {
                throw new RuntimeException('Cannot read '.$source->path);
            }
            foreach ($inspection->callables($source->statements) as $name => $callable) {
                $graph = $inspection->analyze($callable, new DependencyGraph($name, $source->path, $text));
                ++$audit['accepted'];
                $syntaxCount = count((new \PhpParser\NodeFinder())->find([$callable], static fn (\PhpParser\Node $node): bool => true));
                if ($syntaxCount !== count($graph->inventory->sites ?? [])) {
                    $audit['violations'][] = $name.': lost syntax sites';
                }
                foreach ($graph->edges as $edge) {
                    if (!isset($graph->nodes[$edge->from], $graph->nodes[$edge->to])) {
                        $audit['violations'][] = $name.': dangling edge';
                    } elseif ($edge->kind === 'reaching-definition' && $graph->nodes[$edge->from]->variable !== $graph->nodes[$edge->to]->variable) {
                        $audit['violations'][] = $name.': definition belongs to a different variable';
                    } elseif ($edge->from === $edge->to) {
                        $audit['violations'][] = $name.': direct self dependency';
                    }
                }
            }
        }

        yield 'all PHP files shipped in WordPress 7.1.1' => [$audit];
    }
}
