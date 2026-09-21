<?php

declare(strict_types=1);

namespace Tests\External;

use App\Analyzer\ExperimentAnalyzer\DataFlow\DependencyGraph;
use App\Analyzer\ExperimentAnalyzer\DataFlow\Inspection;
use App\Analyzer\ExperimentAnalyzer\DataFlow\InspectionException;
use App\Analyzer\ExperimentAnalyzer\Invocation\CallEffects;
use App\Analyzer\ExperimentAnalyzer\SourceIndex;
use App\Analyzer\PhpFileCollector;
use PhpParser\Node\Expr\Variable;
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
    public function testEveryCallableProducesAConsistentGraphOrAnExplicitRefusal(array $audit): void
    {
        self::assertSame(1512, $audit['files']);
        self::assertSame(1512, $audit['parsed']);
        self::assertSame(9912, $audit['accepted']);
        self::assertSame(2029, $audit['rejected']);
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
        $signatures = [];
        foreach ($index->sources() as $source) {
            foreach ($inspection->callables($source->statements) as $name => $callable) {
                $signatures[strtolower($name)] ??= [];
                foreach ($callable->params as $position => $parameter) {
                    $signatures[strtolower($name)][$position] = $parameter->byRef;
                    if ($parameter->variadic) {
                        $signatures[strtolower($name)]['...'] = $parameter->byRef;
                    }
                    if ($parameter->var instanceof Variable && is_string($parameter->var->name)) {
                        $signatures[strtolower($name)][$parameter->var->name] = $parameter->byRef;
                    }
                }
            }
        }
        $audit = ['files' => count($files), 'parsed' => count($index->sources()), 'accepted' => 0, 'rejected' => 0, 'violations' => []];
        foreach ($index->sources() as $source) {
            $text = file_get_contents($source->path);
            if ($text === false) {
                throw new RuntimeException('Cannot read '.$source->path);
            }
            foreach ($inspection->callables($source->statements) as $name => $callable) {
                try {
                    $graph = $inspection->analyze($callable, new DependencyGraph($name, $source->path, $text), new CallEffects($signatures, explode('::', $name)[0]));
                    ++$audit['accepted'];
                    foreach ($graph->edges as $edge) {
                        if (!isset($graph->nodes[$edge->from], $graph->nodes[$edge->to])) {
                            $audit['violations'][] = $name.': dangling edge';
                        } elseif ($edge->kind === 'reaching-definition' && $graph->nodes[$edge->from]->variable !== $graph->nodes[$edge->to]->variable) {
                            $audit['violations'][] = $name.': definition belongs to a different variable';
                        } elseif ($edge->from === $edge->to) {
                            $audit['violations'][] = $name.': direct self dependency';
                        }
                    }
                } catch (InspectionException) {
                    ++$audit['rejected'];
                }
            }
        }

        yield 'all PHP files shipped in WordPress 7.1.1' => [$audit];
    }
}
