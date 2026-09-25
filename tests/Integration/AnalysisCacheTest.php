<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Analyzer\CacheStorage;
use App\Analyzer\Graph\Edge;
use App\Analyzer\Graph\EdgeKind;
use App\Analyzer\Graph\GraphSnapshot;
use App\Analyzer\NativeAnalyzer\NativeAnalyzer;
use App\Analyzer\PhaseCache;
use App\Analyzer\PhpStanAnalyzer\PhpStanAnalyzer;
use App\Analyzer\PhpStanAnalyzer\WorkingDirectory;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

/**
 * @internal
 */
#[CoversNothing]
#[Large]
final class AnalysisCacheTest extends TestCase
{
    #[DataProvider('providerEngines')]
    public function testColdWarmAndPartiallyEvictedCachesDescribeTheUncachedGraph(string $engine): void
    {
        $directory = WorkingDirectory::at(sys_get_temp_dir().'/peq-analysis-'.uniqid());
        $cache = new PhaseCache(new CacheStorage($directory->path, 'v1'));
        $path = dirname(__DIR__).'/Fixture/Sample';
        $uncached = $engine === 'native' ? new NativeAnalyzer() : new PhpStanAnalyzer();
        $cached = $engine === 'native' ? new NativeAnalyzer(cache: $cache) : new PhpStanAnalyzer(cache: $cache);
        $expected = GraphSnapshot::of($uncached->analyze($path));
        $cold = GraphSnapshot::of($cached->analyze($path));
        $entries = glob($directory->path.'/*.cache');
        self::assertNotFalse($entries);
        self::assertGreaterThan(2, count($entries));
        array_map(static fn (string $entry): bool => touch($entry, 1000000000), $entries);

        $warm = GraphSnapshot::of($cached->analyze($path));
        clearstatcache();
        self::assertSame([1000000000], array_values(array_unique(array_map(filemtime(...), $entries))));
        $enrichedEntries = glob($directory->path.'/'.hash('sha256', $engine.'-enriched').'-*.cache');
        self::assertNotFalse($enrichedEntries);
        self::assertCount(1, $enrichedEntries);
        unlink($enrichedEntries[0]);
        $partial = GraphSnapshot::of($cached->analyze($path));
        $graphEntries = glob($directory->path.'/'.hash('sha256', $engine.'-graph').'-*.cache');
        self::assertNotFalse($graphEntries);
        self::assertCount(1, $graphEntries);
        clearstatcache();
        self::assertSame(1000000000, filemtime($graphEntries[0]), 'Enrichment must reuse the preceding graph phase.');
        $directory->delete();

        self::assertEquals($expected, $cold);
        self::assertEquals($expected, $warm);
        self::assertEquals($expected, $partial);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function providerEngines(): iterable
    {
        yield 'native' => ['native'];

        yield 'phpstan' => ['phpstan'];
    }

    #[DataProvider('providerEngines')]
    public function testPhpDocDependenciesSurviveCacheReuseAndCommentOnlyEdits(string $engine): void
    {
        $directory = WorkingDirectory::at(sys_get_temp_dir().'/peq-phpdoc-cache-'.uniqid());
        $text = <<<'PHP'
            <?php
            class Alpha {}
            class Bravo {}
            /** @return Alpha */
            function run() {}
            PHP;
        $source = $directory->write('source.php', $text);
        touch($source, 1000000000);
        $cache = new PhaseCache(new CacheStorage($directory->path.'/.peq.cache', 'v1'));
        $analyzer = $engine === 'native' ? new NativeAnalyzer(cache: $cache) : new PhpStanAnalyzer(cache: $cache);
        $cold = $analyzer->analyze($source);
        $warm = $analyzer->analyze($source);
        $entries = glob($directory->path.'/.peq.cache/'.hash('sha256', $engine.'-enriched').'-*.cache');
        self::assertNotFalse($entries);
        self::assertCount(1, $entries);
        unlink($entries[0]);
        $partial = $analyzer->analyze($source);
        $references = array_values(array_filter($partial->forwardEdges(), static fn (Edge $edge): bool => $edge->kind() === EdgeKind::PhpDoc));

        self::assertEquals(GraphSnapshot::of($cold), GraphSnapshot::of($warm));
        self::assertEquals(GraphSnapshot::of($cold), GraphSnapshot::of($partial));
        self::assertSame([['run', 'Alpha', 4]], array_map(static fn (Edge $edge): array => [$edge->from()->toString(), $edge->to()->toString(), $edge->meta()->line], $references));

        file_put_contents($source, str_replace('@return Alpha', '@return Bravo', $text));
        touch($source, 1000000000);
        $edited = $analyzer->analyze($source);
        $references = array_values(array_filter($edited->forwardEdges(), static fn (Edge $edge): bool => $edge->kind() === EdgeKind::PhpDoc));
        $fresh = ($engine === 'native' ? new NativeAnalyzer() : new PhpStanAnalyzer())->analyze($source);

        self::assertSame([['run', 'Bravo', 4]], array_map(static fn (Edge $edge): array => [$edge->from()->toString(), $edge->to()->toString(), $edge->meta()->line], $references));
        self::assertEquals(GraphSnapshot::of($fresh), GraphSnapshot::of($edited));
        self::assertEquals(GraphSnapshot::of($edited), GraphSnapshot::of($analyzer->analyze($source)));
        $directory->delete();
    }

    public function testEditsRebuildCrossFileRelationsWhileReusingUnchangedSyntax(): void
    {
        $directory = WorkingDirectory::at(sys_get_temp_dir().'/peq-analysis-'.uniqid());
        $sources = WorkingDirectory::at($directory->path.'/src');
        $sources->write('Consumer.php', '<?php class Consumer { use Shared; }');
        $trait = $sources->write('Shared.php', '<?php trait Shared { function before() {} }');
        $cache = new PhaseCache(new CacheStorage($directory->path.'/.peq.cache', 'v1'));
        $analyzer = new NativeAnalyzer(cache: $cache);
        $before = $analyzer->analyze($sources->path);
        self::assertNotNull($before->nodeNamed('Consumer::before'));
        $entries = glob($directory->path.'/.peq.cache/'.hash('sha256', 'syntax').'-*.cache');
        self::assertNotFalse($entries);
        self::assertCount(2, $entries);
        array_map(static fn (string $entry): bool => touch($entry, 1000000000), $entries);
        file_put_contents($trait, '<?php trait Shared { function after() {} }');

        $after = $analyzer->analyze($sources->path);
        clearstatcache();
        $reused = array_filter(array_map(filemtime(...), $entries), static fn (false|int $time): bool => $time === 1000000000);
        $fresh = (new NativeAnalyzer())->analyze($sources->path);
        $directory->delete();

        self::assertCount(1, $reused);
        self::assertNull($after->nodeNamed('Consumer::before'));
        self::assertNotNull($after->nodeNamed('Consumer::after'));
        self::assertEquals(GraphSnapshot::of($fresh), GraphSnapshot::of($after));
    }

    public function testAdditionsDeletionsAndSelectionChangesDoNotReturnStaleNodes(): void
    {
        $directory = WorkingDirectory::at(sys_get_temp_dir().'/peq-analysis-'.uniqid());
        $sources = WorkingDirectory::at($directory->path.'/src');
        $first = $sources->write('First.php', '<?php class First {}');
        $cache = new PhaseCache(new CacheStorage($directory->path.'/.peq.cache', 'v1'));
        $analyzer = new NativeAnalyzer(cache: $cache);
        $analyzer->analyze($sources->path);
        $sources->write('Second.php', '<?php class Second {}');
        $added = $analyzer->analyze($sources->path);
        unlink($first);
        $deleted = $analyzer->analyze($sources->path);
        $excluded = (new NativeAnalyzer(excludes: ['Second.php'], cache: $cache))->analyze($sources->path);
        $directory->delete();

        self::assertNotNull($added->nodeNamed('First'));
        self::assertNotNull($added->nodeNamed('Second'));
        self::assertNull($deleted->nodeNamed('First'));
        self::assertNotNull($deleted->nodeNamed('Second'));
        self::assertSame([], $excluded->nodes());
    }

    public function testCachedSyntaxRebuildsAnonymousClassObjectIdentities(): void
    {
        $directory = WorkingDirectory::at(sys_get_temp_dir().'/peq-analysis-'.uniqid());
        $source = $directory->write('source.php', '<?php $a = new class { function one() {} }; $b = new class { function two() {} };');
        $cache = new PhaseCache(new CacheStorage($directory->path.'/.peq.cache', 'v1'));
        $analyzer = new NativeAnalyzer(cache: $cache);
        $cold = GraphSnapshot::of($analyzer->analyze($source));
        $graphEntries = glob($directory->path.'/.peq.cache/'.hash('sha256', 'native-graph').'-*.cache');
        $enrichedEntries = glob($directory->path.'/.peq.cache/'.hash('sha256', 'native-enriched').'-*.cache');
        self::assertNotFalse($graphEntries);
        self::assertNotFalse($enrichedEntries);
        array_map(unlink(...), [...$graphEntries, ...$enrichedEntries]);

        $restored = GraphSnapshot::of($analyzer->analyze($source));
        $directory->delete();

        self::assertEquals($cold, $restored);
    }

    public function testCliInspectionAndQueryShareCacheAndRebuildOnVersionMismatch(): void
    {
        $directory = WorkingDirectory::at(sys_get_temp_dir().'/peq-cli-'.uniqid());
        $directory->write('source.php', '<?php class Invoice {}');
        $console = dirname(__DIR__, 2).'/bin/console';
        $inspect = new Process([PHP_BINARY, $console, 'Invoice', '.', '--type=native', '--output=json'], $directory->path);
        $inspect->mustRun();
        $entries = glob($directory->path.'/.peq.cache/*.cache');
        self::assertNotFalse($entries);
        self::assertCount(3, $entries);
        array_map(static fn (string $entry): bool => touch($entry, 1000000000), $entries);

        $query = new Process([PHP_BINARY, $console, 'graph', 'MATCH (c:Class) RETURN c.id', $directory->path, '--type=native'], $directory->path);
        $query->mustRun();
        self::assertStringContainsString('Invoice', $query->getOutput());
        clearstatcache();
        self::assertSame([1000000000], array_values(array_unique(array_map(filemtime(...), $entries))));
        file_put_contents($directory->path.'/.peq.cache/version', 'previous-release');
        file_put_contents($directory->path.'/.peq.cache/stale-phase.cache', 'obsolete');
        $inspect->mustRun();

        self::assertFileDoesNotExist($directory->path.'/.peq.cache/stale-phase.cache');
        self::assertStringContainsString('Invoice', $inspect->getOutput());
        self::assertNotSame('previous-release', file_get_contents($directory->path.'/.peq.cache/version'));
        $directory->delete();
    }
}
