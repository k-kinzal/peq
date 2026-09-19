<?php

declare(strict_types=1);

namespace App\Analyzer\PhpStanAnalyzer;

use App\Analyzer\Graph\Edge;
use App\Analyzer\Graph\Node;

/**
 * What peq's collectors reported during one analysis, read back as symbols.
 *
 * PHPStan keeps what a collector returned as data of no particular type: an array
 * per file, an array per collector inside it, and inside that whatever the collector
 * handed back. That is the shape peq's own types have to be recovered from, and this
 * is where it happens — once, at the edge of the analyzer package, so that nothing
 * further in reads an array position or checks a type to find out what it was given.
 *
 * Recovery is by recognition rather than by trust: a value that is neither a symbol
 * nor a relation is left out instead of being asserted to be one. A collector cannot
 * report something peq does not understand, but PHPStan's own result carries the
 * findings of every registered collector, and only peq's own are asked for here.
 */
final class CollectorReport
{
    /**
     * @param list<Edge|Node> $symbols The symbols and relations the collectors reported
     */
    public function __construct(
        private readonly array $symbols,
    ) {}

    /**
     * Reads the report of the named collectors out of one analysis result.
     *
     * @param array<string, mixed> $collectedData What the analysis reported, keyed by file
     * @param list<class-string>   $collectors    The collectors whose findings to read
     *
     * @return self The findings of those collectors
     *
     * @example An analysis that reported nothing describes an empty graph
     *     (new \App\Analyzer\PhpStanAnalyzer\CollectorReport([]))->symbols() // => []
     */
    public static function of(array $collectedData, array $collectors): self
    {
        $symbols = [];
        foreach ($collectedData as $perFile) {
            if (!is_array($perFile)) {
                continue;
            }
            foreach ($collectors as $collector) {
                foreach (self::itemsOf($perFile[$collector] ?? null) as $item) {
                    $symbols[] = $item;
                }
            }
        }

        return new self($symbols);
    }

    /**
     * Reads the symbols and relations one collector reported for one file.
     *
     * A collector reports one batch per analysed syntax node, so the findings arrive
     * two levels deep.
     *
     * @param mixed $collected What the collector reported for the file
     *
     * @return list<Edge|Node> The symbols and relations it reported
     *
     * @example Anything that is not a symbol or a relation is left out
     *     \App\Analyzer\PhpStanAnalyzer\CollectorReport::itemsOf([['not a symbol']]) // => []
     */
    public static function itemsOf(mixed $collected): array
    {
        if (!is_array($collected)) {
            return [];
        }

        $items = [];
        foreach ($collected as $batch) {
            if (!is_array($batch)) {
                continue;
            }
            foreach ($batch as $item) {
                if ($item instanceof Node || $item instanceof Edge) {
                    $items[] = $item;
                }
            }
        }

        return $items;
    }

    /**
     * Returns everything the collectors reported, symbols and relations alike.
     *
     * @return list<Edge|Node> The findings, in the order they were reported
     */
    public function symbols(): array
    {
        return $this->symbols;
    }
}
