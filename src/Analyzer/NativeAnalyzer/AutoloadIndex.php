<?php

declare(strict_types=1);

namespace App\Analyzer\NativeAnalyzer;

/**
 * The classes a project can reach, read from how it says it is autoloaded.
 *
 * Analysis has one question that cannot be answered from the analysed files alone:
 * whether a class it has not read is a class that exists. It matters in exactly one
 * place — `parent` names the parent class when analysis can find a declaration for
 * it, and names nothing at all when it cannot — and getting it wrong either invents a
 * relation to a class no codebase declares or loses one to a class every codebase
 * has.
 *
 * The question is answered the way the reference engine answers it: by reading the
 * autoload maps the project's package manager wrote, which say where a class would
 * live without saying what is in it. Nothing of the analysed project is loaded to
 * find out — running a codebase in order to describe it is what a static analyzer
 * exists not to do.
 *
 * @visibility namespace
 */
final class AutoloadIndex
{
    /**
     * @param array<string, true>         $classMap Which classes are mapped to a file of their own
     * @param array<string, list<string>> $prefixes The directories each namespace prefix maps to, longest prefix first
     */
    public function __construct(
        private readonly array $classMap,
        private readonly array $prefixes,
    ) {}

    /**
     * Reads the autoload maps of the project the analysis runs in.
     *
     * A project with no maps to read — one that is not managed by Composer, or one
     * whose dependencies have not been installed — leaves analysis with nothing but
     * PHP's own classes, which is the same position the reference engine would be in.
     *
     * @param string $workingDirectory The directory the analysis runs in
     *
     * @return self What that project can reach
     */
    public static function at(string $workingDirectory): self
    {
        $classMap = [];
        foreach (self::read($workingDirectory.'/vendor/composer/autoload_classmap.php') as $className => $file) {
            if (is_string($className)) {
                $classMap[$className] = true;
            }
        }

        $prefixes = [];
        foreach ([...self::read($workingDirectory.'/vendor/composer/autoload_psr4.php'), ...self::read($workingDirectory.'/vendor/composer/autoload_namespaces.php')] as $prefix => $directories) {
            if (!is_string($prefix) || !is_array($directories)) {
                continue;
            }
            $prefixes[$prefix] = array_values(array_filter($directories, is_string(...)));
        }
        uksort($prefixes, static fn (string $first, string $second): int => strlen($second) <=> strlen($first));

        return new self($classMap, $prefixes);
    }

    /**
     * Reads one autoload map, or nothing when the project wrote none.
     *
     * @param string $file Absolute path of the map
     *
     * @return array<array-key, array<array-key, string>|string> What the map says, or nothing
     */
    public static function read(string $file): array
    {
        if (!is_file($file)) {
            return [];
        }

        $contents = @include $file;
        if (!is_array($contents)) {
            return [];
        }

        $read = [];
        foreach ($contents as $key => $value) {
            if (is_string($value)) {
                $read[$key] = $value;

                continue;
            }
            if (is_array($value)) {
                $read[$key] = array_filter($value, is_string(...));
            }
        }

        return $read;
    }

    /**
     * Reports whether a class is one the project can reach.
     *
     * @param string $className The fully qualified class name
     *
     * @return bool True when a declaration for that class could be found
     */
    public function knows(string $className): bool
    {
        if (isset($this->classMap[$className]) || class_exists($className, false) || interface_exists($className, false)) {
            return true;
        }

        foreach ($this->prefixes as $prefix => $directories) {
            if (!str_starts_with($className, $prefix)) {
                continue;
            }
            $relative = str_replace('\\', '/', substr($className, strlen($prefix))).'.php';
            foreach ($directories as $directory) {
                if (is_file($directory.'/'.$relative)) {
                    return true;
                }
            }
        }

        return false;
    }
}
