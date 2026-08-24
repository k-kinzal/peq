<?php

declare(strict_types=1);

namespace App\Analyzer\PhpStanAnalyzer;

use App\Analyzer\Graph\Edge;
use App\Analyzer\Graph\Graph;
use App\Analyzer\Graph\Node;

/**
 * Assembles a graph from what PHPStan's collectors reported.
 *
 * PHPStan hands collected data back as nested arrays of whatever each collector
 * returned, keyed by file and by collector class. Turning that into a graph means
 * two things: recognising the nodes and edges inside it, and recording them in an
 * order that leaves no relation pointing at a symbol the graph has not heard of.
 *
 * Recognition is done by checking each value, not by asserting the shape of the
 * arrays: a collector that reported something unexpected is skipped rather than
 * trusted.
 *
 * @visibility namespace
 */
final class GraphBuilder
{
    /**
     * Builds the graph reported by the given collectors.
     *
     * Nodes are recorded before edges, so a symbol that was actually analysed is
     * recorded with everything known about it rather than as the placeholder an
     * edge would otherwise have created for it.
     *
     * @param array<string, mixed> $collectedData What the analysis reported, keyed by file
     * @param list<class-string>   $collectors    The collectors whose findings to read
     *
     * @return Graph The graph those findings describe
     */
    public function build(array $collectedData, array $collectors): Graph
    {
        $reported = [];
        foreach ($collectedData as $perFile) {
            if (!is_array($perFile)) {
                continue;
            }
            foreach ($collectors as $collector) {
                foreach ($this->itemsOf($perFile[$collector] ?? null) as $item) {
                    $reported[] = $item;
                }
            }
        }

        $graph = new Graph();
        foreach ($reported as $item) {
            if ($item instanceof Node) {
                $graph->addNode($item);
            }
        }
        foreach ($reported as $item) {
            if ($item instanceof Edge) {
                $graph->addEdge($item);
            }
        }

        return $graph;
    }

    /**
     * Reads the nodes and edges out of what one collector reported for one file.
     *
     * A collector reports one batch per analysed AST node, so the findings arrive
     * two levels deep. Anything that is neither a node nor an edge is left out.
     *
     * @param mixed $collected What the collector reported for the file
     *
     * @return list<Edge|Node> The nodes and edges it reported
     */
    public function itemsOf(mixed $collected): array
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
}
