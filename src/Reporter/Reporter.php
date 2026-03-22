<?php

declare(strict_types=1);

namespace App\Reporter;

use App\Analyzer\Graph\Graph;
use App\Analyzer\Graph\Node;
use App\Analyzer\Graph\NodeId;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Interface for reporting analysis results.
 *
 * Reporters are responsible for taking a dependency graph and a starting symbol,
 * and outputting the analysis results in a specific format (e.g., text tree, JSON, DOT).
 */
interface Reporter
{
    /**
     * Generates a report for the given dependency graph.
     *
     * @param Graph           $graph  The dependency graph to report on
     * @param NodeId<Node>    $symbol The starting symbol (root node) for the report
     * @param OutputInterface $output The console output interface
     */
    public function report(Graph $graph, NodeId $symbol, OutputInterface $output): void;
}
