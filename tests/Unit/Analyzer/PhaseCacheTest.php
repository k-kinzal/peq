<?php

declare(strict_types=1);

namespace Tests\Unit\Analyzer;

use App\Analyzer\CacheCodec;
use App\Analyzer\CachedSyntax;
use App\Analyzer\CacheStorage;
use App\Analyzer\Graph\Graph;
use App\Analyzer\PhaseCache;
use App\Analyzer\PhpStanAnalyzer\WorkingDirectory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * @internal
 */
#[CoversClass(PhaseCache::class)]
#[UsesClass(CacheStorage::class)]
#[UsesClass(CacheCodec::class)]
#[UsesClass(CachedSyntax::class)]
#[UsesClass(Graph::class)]
#[UsesClass(\App\Analyzer\ExecutionVersion::class)]
#[UsesClass(WorkingDirectory::class)]
#[Small]
final class PhaseCacheTest extends TestCase
{
    public function testInWorkingDirectoryDoesNotCreateFilesUntilAValueIsRequested(): void
    {
        self::assertInstanceOf(PhaseCache::class, PhaseCache::inWorkingDirectory());
    }

    public function testRememberAFreshInstanceReusesAPhaseWithoutRunningItsComputation(): void
    {
        $directory = WorkingDirectory::at(sys_get_temp_dir().'/peq-cache-'.uniqid());
        $cache = new PhaseCache(new CacheStorage($directory->path, 'v1'));
        $cache->remember('graph', 'project', 'contents', Graph::class, static fn (): Graph => new Graph());

        $restored = (new PhaseCache(new CacheStorage($directory->path, 'v1')))->remember(
            'graph',
            'project',
            'contents',
            Graph::class,
            static fn (): Graph => throw new RuntimeException('A cache hit must not analyze the project.'),
        );
        $directory->delete();

        self::assertSame([], $restored->nodes());
    }

    public function testRememberPhasesSlotsAndInputFingerprintsAreIndependent(): void
    {
        $directory = WorkingDirectory::at(sys_get_temp_dir().'/peq-cache-'.uniqid());
        $cache = new PhaseCache(new CacheStorage($directory->path, 'v1'));
        $calls = 0;
        $compute = static function () use (&$calls): Graph {
            ++$calls;

            return new Graph();
        };
        $cache->remember('graph', 'first', 'old', Graph::class, $compute);
        $cache->remember('enriched', 'first', 'old', Graph::class, $compute);
        $cache->remember('graph', 'second', 'old', Graph::class, $compute);
        $cache->remember('graph', 'first', 'new', Graph::class, $compute);
        $cache->remember('graph', 'first', 'new', Graph::class, $compute);
        $entries = glob($directory->path.'/*.cache');
        $directory->delete();

        self::assertSame(4, $calls);
        self::assertNotFalse($entries);
        self::assertCount(3, $entries);
    }

    public function testRememberWorksWhenTheCacheLocationCannotBeCreated(): void
    {
        $directory = WorkingDirectory::at(sys_get_temp_dir().'/peq-cache-'.uniqid());
        $file = $directory->write('occupied', 'user data');
        $cache = new PhaseCache(new CacheStorage($file, 'v1'));

        $graph = $cache->remember('graph', 'project', 'contents', Graph::class, static fn (): Graph => new Graph());

        self::assertSame([], $graph->nodes());
        self::assertSame('user data', file_get_contents($file));
        $directory->delete();
    }

    public function testRememberFailuresAreNotCached(): void
    {
        $directory = WorkingDirectory::at(sys_get_temp_dir().'/peq-cache-'.uniqid());
        $cache = new PhaseCache(new CacheStorage($directory->path, 'v1'));

        try {
            $cache->remember('graph', 'project', 'contents', Graph::class, static fn (): Graph => throw new RuntimeException('analysis failed'));
            self::fail('The analysis failure must propagate.');
        } catch (RuntimeException $failure) {
            self::assertSame('analysis failed', $failure->getMessage());
        }
        self::assertSame([], glob($directory->path.'/*.cache'));
        $restored = $cache->remember('graph', 'project', 'contents', Graph::class, static fn (): Graph => new Graph());
        $directory->delete();

        self::assertSame([], $restored->nodes());
    }

    public function testRememberCorruptionAndWrongValueTypesAreRecomputed(): void
    {
        $directory = WorkingDirectory::at(sys_get_temp_dir().'/peq-cache-'.uniqid());
        $cache = new PhaseCache(new CacheStorage($directory->path, 'v1'));
        $cache->remember('graph', 'project', 'contents', CachedSyntax::class, static fn (): CachedSyntax => new CachedSyntax(null));
        $calls = 0;
        $compute = static function () use (&$calls): Graph {
            ++$calls;

            return new Graph();
        };
        $cache->remember('graph', 'project', 'contents', Graph::class, $compute);
        $entries = glob($directory->path.'/*.cache');
        self::assertNotFalse($entries);
        self::assertCount(1, $entries);
        file_put_contents($entries[0], 'incomplete');
        $cache->remember('graph', 'project', 'contents', Graph::class, $compute);
        $directory->delete();

        self::assertSame(2, $calls);
    }
}
