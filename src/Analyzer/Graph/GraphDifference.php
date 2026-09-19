<?php

declare(strict_types=1);

namespace App\Analyzer\Graph;

/**
 * What one graph holds and another does not, in both directions.
 *
 * A difference is reported rather than merely detected, because the question it
 * answers is never "are these two graphs equal" alone: when a second analyzer is
 * checked against the first, the answer has to say which symbol or relation one of
 * them invented and which one it lost, or the check cannot be acted on.
 *
 * The two directions are kept apart for the same reason. A relation missing from an
 * impact analysis is a symbol that will be reported as safe to change when it is not,
 * which is the failure that matters; a relation too many is noise. Telling them apart
 * is the difference between a bug and a nuisance.
 */
final class GraphDifference
{
    /**
     * How many lines of each kind are written out before the rest is summarised.
     *
     * A difference between two analyses of a whole codebase can run to thousands of
     * lines, and reading the first few of each kind is what locates the cause.
     */
    private const REPORTED_LINES = 12;

    /**
     * @param list<string> $missingNodes    Symbols the expected graph holds and this one does not
     * @param list<string> $unexpectedNodes Symbols this graph holds and the expected one does not
     * @param list<string> $missingEdges    Relations the expected graph holds and this one does not
     * @param list<string> $unexpectedEdges Relations this graph holds and the expected one does not
     */
    public function __construct(
        public readonly array $missingNodes,
        public readonly array $unexpectedNodes,
        public readonly array $missingEdges,
        public readonly array $unexpectedEdges,
    ) {}

    /**
     * Reports whether the two graphs say exactly the same thing.
     *
     * @example Nothing on either side is no difference
     *     (new \App\Analyzer\Graph\GraphDifference([], [], [], []))->isEmpty() // => true
     * @example One symbol on one side is a difference
     *     (new \App\Analyzer\Graph\GraphDifference(['class A resolved=yes at nowhere'], [], [], []))->isEmpty() // => false
     *
     * @return bool True when neither graph holds anything the other lacks
     */
    public function isEmpty(): bool
    {
        return $this->missingNodes === []
            && $this->unexpectedNodes === []
            && $this->missingEdges === []
            && $this->unexpectedEdges === [];
    }

    /**
     * Counts everything the two graphs disagree about.
     *
     * @example Agreement counts as nothing
     *     (new \App\Analyzer\Graph\GraphDifference([], [], [], []))->count() // => 0
     *
     * @return int The number of symbols and relations only one of the graphs holds
     */
    public function count(): int
    {
        return count($this->missingNodes)
            + count($this->unexpectedNodes)
            + count($this->missingEdges)
            + count($this->unexpectedEdges);
    }

    /**
     * Describes the difference in a form a failing check can print.
     *
     * @example Agreement describes itself as such
     *     (new \App\Analyzer\Graph\GraphDifference([], [], [], []))->describe() // => 'the graphs are identical'
     *
     * @return string The difference, written out
     */
    public function describe(): string
    {
        if ($this->isEmpty()) {
            return 'the graphs are identical';
        }

        return implode("\n", array_merge(
            [sprintf('%d differences', $this->count())],
            self::section('symbols only the reference engine found', $this->missingNodes),
            self::section('symbols only this engine found', $this->unexpectedNodes),
            self::section('relations only the reference engine found', $this->missingEdges),
            self::section('relations only this engine found', $this->unexpectedEdges),
        ));
    }

    /**
     * Writes out one side of the difference, shortened when it is long.
     *
     * @param string       $title The heading of the section
     * @param list<string> $lines What that side holds
     *
     * @example A side that holds nothing is left out
     *     \App\Analyzer\Graph\GraphDifference::section('lost', []) // => []
     *
     * @return list<string> The lines of that section, or none when the side is empty
     */
    public static function section(string $title, array $lines): array
    {
        if ($lines === []) {
            return [];
        }

        $shown = array_slice($lines, 0, self::REPORTED_LINES);
        $rest = count($lines) - count($shown);

        return array_merge(
            [sprintf('  %s (%d):', $title, count($lines))],
            array_map(static fn (string $line): string => '    '.$line, $shown),
            $rest > 0 ? [sprintf('    ... and %d more', $rest)] : [],
        );
    }
}
