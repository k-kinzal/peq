<?php

declare(strict_types=1);

namespace App\Config;

/**
 * Which analyzer implementation builds the dependency graph.
 *
 * The kinds are closed, so every place that reacts to the choice — the use case
 * that constructs the analyzer above all — has to name an arm for each of them,
 * and adding a fourth analyzer is reported at every such place instead of falling
 * through a default.
 *
 * Closed is not the same as always available. One of these analyzers is built on
 * PHPStan, which peq's distributed binary does not carry: bundling a static analyser
 * to run a parser is most of the download for none of the answers. A kind whose
 * analyzer is not installed is not offered and not accepted, so the choice a build
 * can actually make is the choice it is asked to make.
 */
enum AnalyzerKind: string
{
    /**
     * The class that stands for PHPStan being installed.
     *
     * Asking for the class rather than for the package is what makes the question
     * answerable from inside a PHAR, where there is no installer left to ask.
     */
    private const PHPSTAN_CONTAINER = 'PHPStan\DependencyInjection\ContainerFactory';


    /** Builds the graph from real sources, using PHPStan to resolve types */
    case PhpStan = 'phpstan';

    /** Builds the same graph from real sources, reading them directly instead of through PHPStan */
    case Native = 'native';

    /** Builds a synthetic graph, for exercising output without parsing sources */
    case Debug = 'debug';

    /**
     * Names the class an installation must have for this kind to run.
     *
     * An analyzer is available exactly when what it is built on is installed, and
     * what it is built on is a class it can ask for. Naming that class rather than
     * asking for it makes the rule readable from the outside, and checkable from a
     * test that cannot uninstall anything.
     *
     * @example The analyzer built on PHPStan needs PHPStan
     *     \App\Config\AnalyzerKind::PhpStan->requires() // => 'PHPStan\DependencyInjection\ContainerFactory'
     * @example The analyzer that reads sources directly needs nothing of the kind
     *     \App\Config\AnalyzerKind::Native->requires() // => null
     *
     * @return null|class-string The class this kind is built on, or null when it needs none
     */
    public function requires(): ?string
    {
        return match ($this) {
            self::PhpStan => self::PHPSTAN_CONTAINER,
            self::Native, self::Debug => null,
        };
    }

    /**
     * Reports whether the analyzer this kind names can run in this installation.
     *
     * @example The analyzer that reads sources directly carries everything it needs
     *     \App\Config\AnalyzerKind::Native->isAvailable() // => true
     *
     * @return bool True when this kind can be chosen here
     */
    public function isAvailable(): bool
    {
        $required = $this->requires();

        return $required === null || class_exists($required);
    }

    /**
     * Returns the kinds this installation can actually be asked for.
     *
     * @example Reading sources directly is always among them
     *     in_array(\App\Config\AnalyzerKind::Native, \App\Config\AnalyzerKind::available(), true) // => true
     *
     * @return list<self> The available kinds, in the order they are declared
     */
    public static function available(): array
    {
        return array_values(array_filter(self::cases(), static fn (self $kind): bool => $kind->isAvailable()));
    }

    /**
     * Returns the kind an installation uses when the user names none.
     *
     * The kinds are declared in the order they are preferred, and an installation
     * prefers the first one it carries. A build without PHPStan therefore falls to
     * reading sources directly rather than to a default it cannot honour.
     *
     * @example An installation that carries nothing else still has an analyzer
     *     \App\Config\AnalyzerKind::preferred() instanceof \App\Config\AnalyzerKind // => true
     *
     * @return self The kind to use when none is asked for
     */
    public static function preferred(): self
    {
        return self::available()[0] ?? self::Native;
    }

    /**
     * Writes out the kinds this installation can be asked for, as they are written.
     *
     * @example The choices are written the way the command line spells them
     *     str_contains(\App\Config\AnalyzerKind::spellAvailable(), 'native') // => true
     *
     * @return string The available kinds, separated by the character that separates them on the command line
     */
    public static function spellAvailable(): string
    {
        return implode('|', array_map(static fn (self $kind): string => $kind->value, self::available()));
    }
}
