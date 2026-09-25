<?php

declare(strict_types=1);

namespace App\Analyzer;

use Composer\Autoload\ClassLoader;
use Symfony\Component\Finder\Finder;

/**
 * Content fingerprints of the selected files and the code reflection can see.
 *
 * Discovery runs on every invocation: directory timestamps cannot reliably detect
 * additions, deletions or same-size edits. Unselected autoloadable sources matter
 * too, because an external parent or trait can change the selected graph.
 */
final class AnalysisInputs
{
    /**
     * @param list<string> $files          The ordered selection, including single-file inputs
     * @param list<mixed>  $options        Engine, target PHP version and selection settings
     * @param bool         $reflectSources Whether external source contents affect resolution;
     *                                     native only tests their existence against autoload maps
     */
    public static function fingerprint(array $files, array $options, bool $reflectSources = true): string
    {
        $directory = getcwd();
        $hash = hash_init('sha256');
        hash_update($hash, serialize([$directory, PHP_VERSION_ID, array_map(phpversion(...), array_combine(get_loaded_extensions(), get_loaded_extensions())), $files, $options, $reflectSources]));
        $paths = array_fill_keys($files, true);
        foreach (['composer.json', 'composer.lock'] as $name) {
            $file = ($directory === false ? '' : $directory).'/'.$name;
            if (is_file($file)) {
                $paths[$file] = true;
            }
        }
        foreach (self::mappedFiles($directory === false ? '' : $directory) as $file) {
            $paths[$file] ??= $reflectSources;
        }
        foreach (self::roots($directory === false ? '' : $directory) as $root) {
            foreach ((new Finder())->files()->in($root)->exclude('.peq.cache')->name(['*.php', 'composer.json', 'composer.lock'])->ignoreUnreadableDirs() as $file) {
                $paths[$file->getPathname()] ??= $reflectSources
                    || in_array($file->getFilename(), ['composer.json', 'composer.lock'], true)
                    || str_contains($file->getPathname(), '/vendor/composer/');
            }
        }
        ksort($paths);
        foreach ($paths as $path => $readContents) {
            hash_update($hash, $path."\0");
            if ($readContents && !@hash_update_file($hash, $path)) {
                /** Never reuse an answer based on an unreadable or disappearing input. */
                hash_update($hash, random_bytes(32));
            }
        }

        return hash_final($hash);
    }

    /**
     * Composer classmaps may point to source files without a PHP extension.
     *
     * @return list<string>
     */
    public static function mappedFiles(string $directory): array
    {
        $files = [];
        foreach (ClassLoader::getRegisteredLoaders() as $loader) {
            array_push($files, ...array_values($loader->getClassMap()));
        }
        $map = $directory.'/vendor/composer/autoload_classmap.php';
        $entries = is_file($map) ? include $map : [];
        if (is_array($entries)) {
            foreach ($entries as $entry) {
                if (is_string($entry)) {
                    $files[] = $entry;
                }
            }
        }

        return array_values(array_unique($files));
    }

    /**
     * Includes Composer paths outside the working tree, including linked packages.
     *
     * @return list<string>
     */
    public static function roots(string $directory): array
    {
        $roots = is_dir($directory.'/vendor/composer') ? [$directory.'/vendor/composer'] : [];
        foreach (ClassLoader::getRegisteredLoaders() as $vendor => $loader) {
            $roots[] = $vendor;
            foreach ([...$loader->getPrefixesPsr4(), ...$loader->getPrefixes()] as $paths) {
                array_push($roots, ...$paths);
            }
            array_push($roots, ...$loader->getFallbackDirsPsr4(), ...$loader->getFallbackDirs());
            foreach ($loader->getClassMap() as $file) {
                $roots[] = dirname($file);
            }
        }
        foreach (['autoload_psr4.php', 'autoload_namespaces.php', 'autoload_classmap.php'] as $map) {
            $file = $directory.'/vendor/composer/'.$map;
            $entries = is_file($file) ? include $file : [];
            if (!is_array($entries)) {
                continue;
            }
            foreach ($entries as $entry) {
                foreach (is_array($entry) ? $entry : [$entry] as $path) {
                    if (is_string($path)) {
                        $roots[] = is_file($path) ? dirname($path) : $path;
                    }
                }
            }
        }

        return self::minimalRoots($roots);
    }

    /**
     * Removes duplicate and nested roots while retaining resolved symlink targets.
     *
     * @param list<string> $roots
     *
     * @return list<string>
     */
    public static function minimalRoots(array $roots): array
    {
        $resolved = [];
        foreach ($roots as $root) {
            $path = realpath($root);
            if ($path !== false && is_dir($path)) {
                $resolved[$path] = true;
            }
        }
        $paths = array_keys($resolved);
        sort($paths);
        $minimal = [];
        foreach ($paths as $path) {
            foreach ($minimal as $parent) {
                if (str_starts_with($path, $parent.DIRECTORY_SEPARATOR)) {
                    continue 2;
                }
            }
            $minimal[] = $path;
        }

        return $minimal;
    }
}
