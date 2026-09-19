<?php

declare(strict_types=1);

namespace App\Analyzer\PhpStanAnalyzer;

use App\Analyzer\Graph\Edge;
use App\Analyzer\Graph\Graph;
use App\Analyzer\Graph\Node;

/**
 * Assembles a graph from what PHPStan's collectors reported.
 *
 * What an analysis found arrives here as a flat list of symbols and relations, in
 * whatever order the collectors happened to report them. Making a graph of it is one
 * decision: record every symbol before any relation, so that a relation is never the
 * thing that introduces one of its own ends.
 *
 * Reading PHPStan's result into that list is CollectorReport's work, not this class's.
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
     * @param list<Edge|Node> $reported The symbols and relations an analysis found
     *
     * @return Graph The graph those findings describe
     */
    public function build(array $reported): Graph
    {
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
}
