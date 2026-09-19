<?php

declare(strict_types=1);

namespace Tests\Fixture\Analyzer;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * The source trees of the packages this checkout has installed.
 *
 * A corpus written by hand covers what its author thought of, and a codebase peq's
 * author did not write covers what nobody thought of: the constructs real libraries
 * reach for, in the proportions they reach for them. The dependencies already on
 * disk are such a codebase, and they cost nothing to obtain.
 *
 * They are not a fixed list. What is installed changes as dependencies are added and
 * updated, which is the point: a package that arrives tomorrow is read tomorrow.
 *
 * Reading all of them costs more memory than one process can hold: the reference
 * engine keeps what it reflected over, and thirty-odd whole-package analyses in one
 * process come to nearly two gigabytes. The check that uses this list therefore runs
 * each package in a process of its own, which costs under a second each and holds
 * fourteen megabytes.
 */
final class InstalledPackages
{
    /**
     * The fewest files a package must hold before it is worth reading.
     *
     * A package of three files exercises nothing a package of thirty does not, and
     * each one read costs a whole run of the reference engine.
     */
    private const SMALLEST_WORTH_READING = 8;

    /**
     * Finds the source tree of every installed package worth reading.
     *
     * A package is read at the directory it keeps its sources in, rather than at its
     * root, so that its own tests and fixtures — which are written to be broken — are
     * not read as if they were library code.
     *
     * @param string $projectPath The root of the checkout
     *
     * @return array<string, string> The source tree of each package, by package name
     */
    public static function sourceRoots(string $projectPath): array
    {
        $roots = [];
        foreach (glob($projectPath.'/vendor/*/*', GLOB_ONLYDIR) ?: [] as $package) {
            foreach (['src', 'lib', 'source'] as $directory) {
                $path = $package.'/'.$directory;
                if (!is_dir($path) || self::countFiles($path) < self::SMALLEST_WORTH_READING) {
                    continue;
                }

                $resolved = realpath($path);
                $roots[substr($package, strlen($projectPath.'/vendor/'))] = $resolved === false ? $path : $resolved;

                break;
            }
        }
        ksort($roots);

        return $roots;
    }

    /**
     * Counts the PHP files under a directory.
     *
     * @param string $path The directory to count
     *
     * @return int How many PHP files it holds, at any depth
     */
    public static function countFiles(string $path): int
    {
        $count = 0;
        $found = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS));
        foreach ($found as $file) {
            if (str_ends_with((string) $file, '.php')) {
                ++$count;
            }
        }

        return $count;
    }
}
