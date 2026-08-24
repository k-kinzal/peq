<?php

declare(strict_types=1);

namespace App\Reporter\TreeReporter;

use App\Analyzer\Graph\Graph;
use App\Analyzer\Graph\Node;
use App\Analyzer\Graph\NodeId;
use App\Reporter\Reporter;
use App\Reporter\Traversal;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Reports analysis results as a text-based tree structure.
 *
 * This reporter visualizes the dependency graph the way the Unix `tree` command
 * visualizes a directory: one line per node, with box-drawing characters showing
 * which branches continue below. It owns the traversal it was given and the line
 * format, and delegates the bookkeeping a tree needs — how deep the walk is, which
 * node is the last of its siblings, which nodes were already expanded — to a
 * cursor created for the duration of one report.
 */
final class TreeReporter implements Reporter
{
    /**
     * @param TreeReporterOptions $options      How much of the tree to print
     * @param Traversal           $traversal    The strategy deciding which relations the tree follows
     * @param LineRenderer        $lineRenderer The format of a single line
     */
    public function __construct(
        private readonly TreeReporterOptions $options,
        private readonly Traversal $traversal,
        private readonly LineRenderer $lineRenderer = new LineRenderer(),
    ) {}

    /**
     * Writes the dependency tree rooted at the given symbol.
     *
     * @param Graph           $graph  The dependency graph to report on
     * @param NodeId<Node>    $symbol The symbol the tree is rooted at
     * @param OutputInterface $output Where the tree is written
     */
    public function report(Graph $graph, NodeId $symbol, OutputInterface $output): void
    {
        $cursor = new TreeCursor(
            graph: $graph,
            traversal: $this->traversal,
            lineRenderer: $this->lineRenderer,
            output: $output,
            level: $this->options->level,
        );

        $this->traversal->traverse($graph, $symbol, $cursor->visit(...));
    }
}
