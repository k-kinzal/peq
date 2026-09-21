<?php

declare(strict_types=1);

namespace Spec\Context;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * Where each scenario of this suite says what it states is published.
 *
 * A scenario is a claim about GQL, and a claim nobody can trace to a published text is
 * a rule somebody made up. So every scenario carries one or more lines of the form
 *
 *     Source: <link> <what at that link>, <what at that link>
 *
 * naming a published text and the places in it the scenario rests on, and each place is
 * looked up in the text it names rather than believed:
 *
 * - the text of ISO/IEC 39075, by subclause number, checked against the numbers of its
 *   table of contents in `spec/iso/subclauses.txt`;
 * - ISO's grammar artifact, by production, checked against the productions it defines;
 * - ISO's feature, condition and implementation-defined artifacts, by code, checked
 *   against the codes they define;
 * - "Graph Pattern Matching in GQL and SQL/PGQ" (Deutsch et al., SIGMOD 2022), written by
 *   the editors of the standard and published under CC BY 4.0, by section, checked
 *   against the sections it has. It explains what the standard's text specifies; it
 *   never stands alone, so a scenario that cites it cites ISO/IEC 39075 as well.
 *
 * A scenario tagged with a subclause or a feature cites that subclause or feature too,
 * so that the tag is a link rather than a number somebody wrote down.
 */
final class SuiteSources
{
    /**
     * The text of ISO/IEC 39075:2024 on ISO's Online Browsing Platform.
     */
    public const TEXT = 'https://www.iso.org/obp/ui/en/#iso:std:iso-iec:39075:ed-1:v1:en';

    /**
     * The paper the editors of ISO/IEC 39075 wrote about its pattern matching.
     */
    public const PAPER = 'https://arxiv.org/abs/2112.06217';

    /**
     * The sections of the paper, by number, as its own headings number them.
     */
    public const PAPER_SECTIONS = [
        '1', '2', '3', '4', '4.1', '4.2', '4.3', '4.4', '4.5', '4.6', '4.7',
        '5', '5.1', '5.2', '5.3', '6', '6.1', '6.2', '6.3', '6.4', '6.5', '6.6',
        '7', '7.1', '7.2', '8',
    ];

    /**
     * Where the feature files sit.
     */
    private const FEATURES = __DIR__.'/../features';

    /**
     * Returns every scenario of the suite with its tags and the sources it cites.
     *
     * @return list<array{where: string, title: string, tags: list<string>, sources: list<string>}> The scenarios
     */
    public static function scenarios(): array
    {
        $scenarios = [];
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(self::FEATURES));
        foreach ($files as $file) {
            if (!$file instanceof SplFileInfo || $file->getExtension() !== 'feature') {
                continue;
            }
            $lines = file($file->getPathname(), FILE_IGNORE_NEW_LINES);
            array_push($scenarios, ...self::read($file->getFilename(), $lines === false ? [] : $lines));
        }

        return $scenarios;
    }

    /**
     * Reads the scenarios out of one feature file.
     *
     * @param string       $name  What the file is called
     * @param list<string> $lines The lines of the file
     *
     * @return list<array{where: string, title: string, tags: list<string>, sources: list<string>}> The scenarios
     */
    public static function read(string $name, array $lines): array
    {
        $scenarios = [];
        $tags = [];
        $current = null;
        foreach ($lines as $index => $line) {
            $text = trim($line);
            if (str_starts_with($text, '@')) {
                $tags = [...$tags, ...(preg_split('/\s+/', $text) ?: [])];

                continue;
            }
            if (preg_match('/^Scenario(?: Outline)?:\s*(.*)$/', $text, $matched) === 1) {
                if ($current !== null) {
                    $scenarios[] = $current;
                }
                $current = ['where' => $name.':'.($index + 1), 'title' => $matched[1], 'tags' => $tags, 'sources' => [], 'open' => true];
                $tags = [];

                continue;
            }
            if (str_starts_with($text, 'Feature:')) {
                $tags = [];

                continue;
            }
            if ($current === null || !$current['open']) {
                continue;
            }
            if (preg_match('/^Source:\s*(.+)$/', $text, $matched) === 1) {
                $current['sources'][] = $matched[1];

                continue;
            }
            if (preg_match('/^(Given|When|Then|And|But|Examples:|\*|\||""")/', $text) === 1) {
                $current['open'] = false;
            }
        }
        if ($current !== null) {
            $scenarios[] = $current;
        }

        return array_map(
            static fn (array $scenario): array => ['where' => $scenario['where'], 'title' => $scenario['title'], 'tags' => $scenario['tags'], 'sources' => $scenario['sources']],
            $scenarios,
        );
    }

    /**
     * Returns every place in one of ISO's artifacts that a scenario of this suite cites.
     *
     * @param string $name The name the artifact is known by in spec/iso/artifacts.txt
     *
     * @return list<string> The places, without repetition
     */
    public static function cited(string $name): array
    {
        $link = self::artifact($name);
        $cited = [];
        foreach (self::scenarios() as $scenario) {
            foreach ($scenario['sources'] as $source) {
                [$at, $places] = self::split($source);
                if ($at === $link) {
                    array_push($cited, ...$places);
                }
            }
        }

        return array_values(array_unique($cited));
    }

    /**
     * Returns what is wrong with the sources one scenario cites, if anything.
     *
     * @param array{where: string, title: string, tags: list<string>, sources: list<string>} $scenario The scenario
     *
     * @return list<string> What is wrong, one sentence each
     */
    public static function faults(array $scenario): array
    {
        if ($scenario['sources'] === []) {
            return ['cites no source'];
        }

        $faults = [];
        $cited = [];
        foreach ($scenario['sources'] as $source) {
            [$link, $places] = self::split($source);
            $fault = self::check($link, $places);
            if ($fault !== null) {
                $faults[] = $fault;
            }
            foreach ($places as $place) {
                $cited[$link.' '.$place] = true;
            }
        }
        $links = array_map(static fn (string $source): string => self::split($source)[0], $scenario['sources']);
        if (array_diff($links, [self::PAPER]) === []) {
            $faults[] = 'cites the editors\' paper without citing ISO/IEC 39075 itself';
        }
        foreach ($scenario['tags'] as $tag) {
            $needed = match (true) {
                str_starts_with($tag, '@iso:') => self::TEXT.' '.substr($tag, 5),
                str_starts_with($tag, '@feature:') => self::artifact('features.xml').' '.substr($tag, 9),
                default => null,
            };
            if ($needed !== null && !isset($cited[$needed])) {
                $faults[] = sprintf('is tagged %s but does not cite it', $tag);
            }
        }

        return $faults;
    }

    /**
     * Splits a source into the link it names and the places in it.
     *
     * @param string $source The source, as a scenario writes it
     *
     * @return array{string, list<string>} The link, and the places
     */
    public static function split(string $source): array
    {
        $parts = preg_split('/\s+/', trim($source), 2) ?: [''];
        $places = array_values(array_filter(
            array_map('trim', explode(',', $parts[1] ?? '')),
            static fn (string $place): bool => $place !== '',
        ));

        return [$parts[0], $places];
    }

    /**
     * Returns what is wrong with the places cited at one link, if anything.
     *
     * @param string       $link   The link
     * @param list<string> $places The places cited there
     *
     * @return null|string What is wrong, or null when nothing is
     */
    public static function check(string $link, array $places): ?string
    {
        if ($places === []) {
            return sprintf('cites %s without saying where in it', $link);
        }
        $known = match ($link) {
            self::TEXT => static fn (string $place): bool => IsoSubclauses::numbers($place),
            self::PAPER => static fn (string $place): bool => in_array(ltrim($place, '§'), self::PAPER_SECTIONS, true),
            self::artifact('gql.bnf.xml') => static fn (string $place): bool => preg_match('/^<(.+)>$/', $place, $name) === 1 && IsoGrammar::defines($name[1]),
            self::artifact('features.xml') => static fn (string $place): bool => isset(IsoFeatures::all()[$place]),
            self::artifact('conditions.xml') => static fn (string $place): bool => IsoConditions::defines($place),
            self::artifact('implementation-defined.xml') => static fn (string $place): bool => IsoImplementationDefined::itemOf($place) !== null,
            default => null,
        };
        if ($known === null) {
            return sprintf('cites %s, which is not a text this suite knows how to check', $link);
        }
        $unknown = array_values(array_filter($places, static fn (string $place): bool => !$known($place)));

        return $unknown === [] ? null : sprintf('cites %s at %s, which it does not have', $link, implode(', ', $unknown));
    }

    /**
     * Returns the link ISO publishes one of its artifacts at.
     *
     * @param string $name The name the artifact is known by in spec/iso/artifacts.txt
     *
     * @return string The link
     */
    public static function artifact(string $name): string
    {
        return IsoArtifacts::manifest()[$name]['url'] ?? '';
    }
}
