<?php

declare(strict_types=1);

namespace Tests\Fixture\Analyzer;

use App\Analyzer\Graph\Edge;
use App\Analyzer\Graph\Node;

/**
 * What an analysis found, written out as lines a test can compare.
 *
 * A test about what an analysis records is a test about a list of symbols and
 * relations, and comparing objects one field at a time says less than comparing the
 * one line each of them amounts to. Writing that line is the same everywhere, so it
 * is written once.
 */
final class GraphSpelling
{
    /**
     * Writes out what an analysis recorded.
     *
     * @param list<Edge|Node> $recorded The symbols and relations it recorded
     *
     * @return list<string> One line each, in the order they were recorded
     */
    public static function of(array $recorded): array
    {
        return array_map(
            static fn (Edge|Node $item): string => $item instanceof Node
                ? $item->kind()->value.' '.$item->id()->toString()
                : $item->from()->toString().' -['.$item->kind()->value.']-> '.$item->to()->toString(),
            $recorded,
        );
    }
}
