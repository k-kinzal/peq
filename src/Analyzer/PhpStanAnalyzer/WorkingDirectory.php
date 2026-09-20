<?php

declare(strict_types=1);

namespace App\Analyzer\PhpStanAnalyzer;

use RuntimeException;

/**
 * The directory PHPStan works in when peq runs it.
 *
 * PHPStan is configured through files on disk and keeps what it compiles and
 * caches on disk as well, so running it programmatically means giving it a place
 * for both. The place is the same for every run of one user: PHPStan compiles a
 * container class per configuration path and caches what it read of the analysed
 * sources, and the next run — the next command, or the next analysis of a test
 * suite — reuses both instead of compiling and reading them again. It is never
 * taken away behind PHPStan's back: a cache directory removed under a running
 * analysis is silently rebuilt, which leaves one directory behind per analysis.
 *
 * @visibility namespace
 */
final readonly class WorkingDirectory
{
    /**
     * @param string $path The absolute path of the directory
     */
    public function __construct(
        public string $path,
    ) {}

    /**
     * Returns the directory the running user shares between all runs of peq.
     *
     * The user is part of the name because the system temporary directory may be
     * shared between users, and a directory one user created is not writable by
     * another.
     *
     * @return self The directory, created if it did not exist
     *
     * @throws RuntimeException If the user is unknown or the directory cannot be created
     */
    public static function shared(): self
    {
        $user = getmyuid();
        if ($user === false) {
            throw new RuntimeException('Unable to determine the user running peq');
        }

        return self::at(sys_get_temp_dir().'/peq-phpstan-'.$user);
    }

    /**
     * Returns the directory at the given path, creating it if it does not exist.
     *
     * @param string $path The absolute path of the directory
     *
     * @return self The directory
     *
     * @throws RuntimeException If the directory cannot be created
     */
    public static function at(string $path): self
    {
        if (!is_dir($path) && !mkdir($path, 0o777, true) && !is_dir($path)) {
            throw new RuntimeException(sprintf('Unable to create the directory "%s"', $path));
        }

        return new self($path);
    }

    /**
     * Writes a file into the directory.
     *
     * The contents are written next to the file first and moved over it in one
     * step, so another process reading the file — or writing the same contents at
     * the same time — never sees it half written.
     *
     * @param string $name     The file name, without a directory part
     * @param string $contents What to write into it
     *
     * @return string The absolute path of the written file
     *
     * @throws RuntimeException If the file cannot be written
     */
    public function write(string $name, string $contents): string
    {
        $file = $this->path.'/'.$name;
        $staged = $file.'.'.uniqid('', true).'.tmp';
        if (file_put_contents($staged, $contents) === false || !rename($staged, $file)) {
            throw new RuntimeException(sprintf('Unable to write the file "%s"', $file));
        }

        return $file;
    }

    /**
     * Removes the directory and everything below it.
     *
     * Removal is best effort and never raises: a directory that is already gone,
     * or partly gone, is the outcome asked for.
     */
    public function delete(): void
    {
        $entries = is_dir($this->path) ? scandir($this->path) : false;
        if ($entries === false) {
            return;
        }

        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $child = $this->path.'/'.$entry;
            if (is_dir($child)) {
                (new self($child))->delete();

                continue;
            }
            unlink($child);
        }
        rmdir($this->path);
    }
}
