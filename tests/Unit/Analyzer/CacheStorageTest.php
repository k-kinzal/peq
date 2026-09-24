<?php

declare(strict_types=1);

namespace Tests\Unit\Analyzer;

use App\Analyzer\CacheStorage;
use App\Analyzer\PhpStanAnalyzer\WorkingDirectory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(CacheStorage::class)]
#[UsesClass(WorkingDirectory::class)]
#[Small]
final class CacheStorageTest extends TestCase
{
    public function testLockedVersionChangesRemoveEveryPhaseBeforeUse(): void
    {
        $directory = WorkingDirectory::at(sys_get_temp_dir().'/peq-cache-'.uniqid());
        $old = new CacheStorage($directory->path, 'v1');
        $old->locked(static fn (): ?string => null);
        $old->write('syntax.cache', 'syntax');
        $old->write('graph.cache', 'graph');
        WorkingDirectory::at($directory->path.'/old-format')->write('entry', 'old');
        $new = new CacheStorage($directory->path, 'v2');

        $new->locked(static fn (): string => 'ready');

        self::assertSame(['.', '..', '.lock', 'version'], scandir($directory->path));
        self::assertSame('v2', file_get_contents($directory->path.'/version'));
        $directory->delete();
    }

    public function testPrepareMissingVersionAlsoDiscardsTheOldContents(): void
    {
        $directory = WorkingDirectory::at(sys_get_temp_dir().'/peq-cache-'.uniqid());
        $directory->write('old.cache', 'unknown format');
        $storage = new CacheStorage($directory->path, 'v1');

        self::assertSame('ready', $storage->locked(static fn (): string => 'ready'));
        self::assertFileDoesNotExist($directory->path.'/old.cache');
        self::assertSame('v1', file_get_contents($directory->path.'/version'));
        $directory->delete();
    }

    public function testWriteTheSameVersionRetainsPhaseEntries(): void
    {
        $directory = WorkingDirectory::at(sys_get_temp_dir().'/peq-cache-'.uniqid());
        $storage = new CacheStorage($directory->path, 'v1');
        $storage->locked(static fn (): ?string => null);
        $storage->write('graph.cache', 'graph');

        (new CacheStorage($directory->path, 'v1'))->locked(static fn (): ?string => null);

        self::assertSame('graph', file_get_contents($directory->path.'/graph.cache'));
        $directory->delete();
    }

    public function testRemoveInvalidationUnlinksNestedSymlinksWithoutDeletingTheirTargets(): void
    {
        $directory = WorkingDirectory::at(sys_get_temp_dir().'/peq-cache-'.uniqid());
        $outside = WorkingDirectory::at(sys_get_temp_dir().'/peq-outside-'.uniqid());
        $outside->write('keep', 'user data');
        symlink($outside->path, $directory->path.'/linked');

        (new CacheStorage($directory->path, 'v1'))->locked(static fn (): ?string => null);

        self::assertSame('user data', file_get_contents($outside->path.'/keep'));
        self::assertFalse(is_link($directory->path.'/linked'));
        $directory->delete();
        $outside->delete();
    }

    public function testLockedASymlinkedCacheIsDisabled(): void
    {
        $directory = WorkingDirectory::at(sys_get_temp_dir().'/peq-cache-'.uniqid());
        $outside = WorkingDirectory::at(sys_get_temp_dir().'/peq-outside-'.uniqid());
        symlink($outside->path, $directory->path.'/cache');

        $result = (new CacheStorage($directory->path.'/cache', 'v1'))->locked(static fn (): string => 'must not run');

        self::assertNull($result);
        self::assertSame(['.', '..'], scandir($outside->path));
        unlink($directory->path.'/cache');
        $directory->delete();
        $outside->delete();
    }
}
