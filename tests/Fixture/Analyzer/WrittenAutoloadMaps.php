<?php

declare(strict_types=1);

namespace Tests\Fixture\Analyzer;

use RuntimeException;

/**
 * A project that says how it is autoloaded, written for a test to read.
 *
 * What an installed project can reach is answered from the maps its package manager
 * wrote, and the maps of a real checkout only exercise the paths a real checkout
 * happens to need: everything peq depends on is in the class map, so the rules for
 * namespace prefixes are never reached by reading peq itself. Writing the maps makes
 * every rule reachable, and makes each of them reachable on its own.
 */
final class WrittenAutoloadMaps
{
    /**
     * Writes a project with the given maps and returns its directory.
     *
     * @param array<array-key, mixed> $classMap   The classes mapped to a file of their own
     * @param array<array-key, mixed> $prefixes   The directories each namespace prefix maps to
     * @param array<array-key, mixed> $namespaces The directories each older-style prefix maps to
     * @param list<string>            $files      Paths, relative to the project, to write empty files at
     *
     * @return string The directory of the written project
     *
     * @throws RuntimeException If the project cannot be written
     */
    public static function writeTo(array $classMap, array $prefixes, array $namespaces, array $files = []): string
    {
        $project = sys_get_temp_dir().'/peq-autoload-'.md5(serialize([$classMap, $prefixes, $namespaces, $files]));
        self::makeDirectory($project.'/vendor/composer');

        self::write($project.'/vendor/composer/autoload_classmap.php', $classMap);
        self::write($project.'/vendor/composer/autoload_psr4.php', $prefixes);
        self::write($project.'/vendor/composer/autoload_namespaces.php', $namespaces);

        foreach ($files as $file) {
            self::makeDirectory(dirname($project.'/'.$file));
            if (file_put_contents($project.'/'.$file, "<?php\n") === false) {
                throw new RuntimeException(sprintf('Unable to write the file "%s"', $project.'/'.$file));
            }
        }

        return $project;
    }

    /**
     * Returns the directory a written project keeps its sources in.
     *
     * @param string $project The directory of the written project
     *
     * @return string The directory the maps point at
     */
    public static function sourceDirectory(string $project): string
    {
        return $project.'/src';
    }

    /**
     * Writes one map out as the file a package manager would have written.
     *
     * @param string                  $file     Absolute path of the map
     * @param array<array-key, mixed> $contents What the map says
     *
     * @throws RuntimeException If the map cannot be written
     */
    public static function write(string $file, array $contents): void
    {
        if (file_put_contents($file, "<?php\n\nreturn ".var_export($contents, true).";\n") === false) {
            throw new RuntimeException(sprintf('Unable to write the file "%s"', $file));
        }
    }

    /**
     * Makes a directory and every directory above it.
     *
     * @param string $path Absolute path of the directory
     *
     * @throws RuntimeException If the directory cannot be made
     */
    public static function makeDirectory(string $path): void
    {
        if (!is_dir($path) && !mkdir($path, 0o777, true) && !is_dir($path)) {
            throw new RuntimeException(sprintf('Unable to create the directory "%s"', $path));
        }
    }
}
