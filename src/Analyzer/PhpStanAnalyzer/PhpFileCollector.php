<?php

declare(strict_types=1);

namespace App\Analyzer\PhpStanAnalyzer;

use Symfony\Component\Finder\Finder;

/**
 * Selects the PHP files an analysis covers.
 *
 * A path may name a file or a directory, and a directory is searched for PHP files
 * subject to the project's include and exclude patterns. Every result is an absolute
 * path, and a path that cannot be resolved on disk is left out rather than passed on
 * — an analysis run should not fail on a symlink that was removed underneath it.
 *
 * @visibility namespace
 */
final class PhpFileCollector
{
    /**
     * Collects the PHP files under the given paths.
     *
     * Patterns are matched against paths relative to the scanned directories, so
     * `src` selects files below a `src/` directory and `vendor` excludes the
     * `vendor/` directory together with everything in it.
     *
     * @param list<string> $paths    Files or directories to scan
     * @param list<string> $includes Path patterns to include, or none to include everything
     * @param list<string> $excludes Path patterns to exclude
     *
     * @return list<string> Absolute paths of the collected PHP files, without duplicates
     */
    public function collect(array $paths, array $includes = [], array $excludes = []): array
    {
        $collected = [];
        $directories = [];

        foreach ($paths as $path) {
            if (is_dir($path)) {
                $directories[] = $path;

                continue;
            }

            $resolved = is_file($path) ? realpath($path) : false;
            if ($resolved !== false) {
                $collected[$resolved] = true;
            }
        }

        if ($directories === []) {
            return array_keys($collected);
        }

        $finder = (new Finder())->files()->in($directories)->name('*.php');
        if ($includes !== []) {
            $finder->path($includes);
        }
        if ($excludes !== []) {
            $finder->notPath($excludes);
        }

        foreach ($finder as $file) {
            $resolved = $file->getRealPath();
            if ($resolved !== false) {
                $collected[$resolved] = true;
            }
        }

        return array_keys($collected);
    }
}
