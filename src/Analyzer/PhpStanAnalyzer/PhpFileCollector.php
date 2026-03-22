<?php

declare(strict_types=1);

namespace App\Analyzer\PhpStanAnalyzer;

use Symfony\Component\Finder\Finder;

/**
 * Collects PHP files from a given set of paths.
 */
final class PhpFileCollector
{
    /**
     * Collects PHP files from the specified paths.
     *
     * @param string[] $paths    List of file or directory paths to scan
     * @param string[] $includes List of glob patterns to include
     * @param string[] $excludes List of glob patterns to exclude
     *
     * @return string[] List of absolute paths to collected PHP files
     */
    public function collect(array $paths, array $includes = [], array $excludes = []): array
    {
        if ($paths === []) {
            return [];
        }

        $files = [];
        $directories = [];

        foreach ($paths as $path) {
            if (is_file($path)) {
                $files[] = realpath($path);
            } elseif (is_dir($path)) {
                $directories[] = $path;
            }
        }

        if ($directories !== []) {
            $finder = new Finder();
            $finder->files()
                ->in($directories)
                ->name('*.php')
            ;

            if ($includes !== []) {
                $finder->name($includes);
            }

            if ($excludes !== []) {
                $finder->notName($excludes);
                $finder->exclude($excludes);
            }

            foreach ($finder as $file) {
                $files[] = $file->getRealPath();
            }
        }

        return array_unique(array_filter($files, fn ($file) => is_string($file)));
    }
}
