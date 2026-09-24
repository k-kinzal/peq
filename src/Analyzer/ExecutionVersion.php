<?php

declare(strict_types=1);

namespace App\Analyzer;

use Symfony\Component\Finder\Finder;

/**
 * Identifies the executable whose private cache formats are being read.
 */
final class ExecutionVersion
{
    /**
     * Box replaces the release token; source installs include uncommitted edits.
     */
    public static function current(string $release = '@git_version@'): string
    {
        if (!str_starts_with($release, '@')) {
            return $release;
        }

        $root = dirname(__DIR__, 2);
        $hash = hash_init('sha256');
        $files = (new Finder())->files()->in([$root.'/src', $root.'/config'])->sortByName();
        foreach ($files as $file) {
            hash_update($hash, $file->getRelativePathname());
            hash_update_file($hash, $file->getPathname());
        }
        foreach (['bin/console', 'composer.lock'] as $file) {
            hash_update_file($hash, $root.'/'.$file);
        }

        return 'source-'.hash_final($hash);
    }
}
