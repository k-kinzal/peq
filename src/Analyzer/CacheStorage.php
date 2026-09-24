<?php

declare(strict_types=1);

namespace App\Analyzer;

use Closure;

/**
 * Best-effort disk storage, with version changes and writes protected by one lock.
 *
 * The lock file survives invalidation so concurrent processes keep locking the same
 * inode. No cached file is executed, and deletion never follows a symbolic link.
 */
final readonly class CacheStorage
{
    /**
     * Names the cache directory and the executable that owns its contents.
     */
    public function __construct(public string $directory, public string $version) {}

    /**
     * Reads or replaces an entry while holding the version lock.
     *
     * @param Closure(): ?string $operation The operation within this cache directory
     */
    public function locked(Closure $operation): ?string
    {
        if (is_link($this->directory) || is_file($this->directory) || (!is_dir($this->directory) && !@mkdir($this->directory, 0o700, true) && !is_dir($this->directory))) {
            return null;
        }
        $lockPath = $this->directory.'/.lock';
        $lock = is_link($lockPath) ? false : @fopen($lockPath, 'c');
        if ($lock === false) {
            return null;
        }

        try {
            if (!flock($lock, LOCK_EX) || !$this->prepare()) {
                return null;
            }

            return $operation();
        } finally {
            fclose($lock);
        }
    }

    /**
     * Discards every phase when the creating executable is different or unknown.
     */
    public function prepare(): bool
    {
        $file = $this->directory.'/version';
        if (!is_link($file) && is_file($file) && @file_get_contents($file) === $this->version) {
            return true;
        }
        $entries = @scandir($this->directory);
        if ($entries === false) {
            return false;
        }
        foreach ($entries as $entry) {
            if ($entry !== '.' && $entry !== '..' && $entry !== '.lock' && !self::remove($this->directory.'/'.$entry)) {
                return false;
            }
        }

        return $this->write('version', $this->version);
    }

    /**
     * Removes stale cache data without traversing links out of the cache.
     */
    public static function remove(string $path): bool
    {
        if (is_link($path) || !is_dir($path)) {
            return @unlink($path);
        }
        $entries = @scandir($path);
        if ($entries === false) {
            return false;
        }
        foreach ($entries as $entry) {
            if ($entry !== '.' && $entry !== '..' && !self::remove($path.'/'.$entry)) {
                return false;
            }
        }

        return @rmdir($path);
    }

    /**
     * Atomically replaces one file; a failed write leaves the previous entry intact.
     */
    public function write(string $name, string $contents): bool
    {
        $temporary = @tempnam($this->directory, '.write-');
        if ($temporary === false) {
            return false;
        }
        $written = @file_put_contents($temporary, $contents) === strlen($contents)
            && @rename($temporary, $this->directory.'/'.$name);
        if (!$written) {
            @unlink($temporary);
        }

        return $written;
    }
}
