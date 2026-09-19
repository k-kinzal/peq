<?php

declare(strict_types=1);

namespace App\Reporter\DotReporter;

use App\Analyzer\Graph\Graph;
use App\Analyzer\Graph\Node;
use App\Analyzer\Graph\NodeId;
use App\Reporter\Reporter;
use App\Reporter\Traversal;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Reports analysis results as a Graphviz digraph.
 *
 * A terminal draws a tree well and a graph badly. Once a symbol is reached from three
 * places the tree has to choose one of them and write the other two off as repeats,
 * and the shape of the thing — the hub every branch runs through, the cycle two
 * classes are caught in — stops being visible at exactly the point it starts to
 * matter. This reporter writes the same walk in a language a renderer understands,
 * and leaves the drawing to something with two dimensions to draw in:
 *
 *     peq 'App\Domain\Invoice' src --output=dot | dot -Tsvg -o invoice.svg
 *
 * What it writes is the part of the graph the walk covered, not a tree: the nodes the
 * walk reported, and every relation the graph holds between two of them.
 */
final class DotReporter implements Reporter
{
    /**
     * @param Traversal         $traversal The strategy deciding which relations the report follows
     * @param null|int          $level     Deepest level to report, or null for the whole graph
     * @param StatementRenderer $renderer  The format of a single statement
     */
    public function __construct(
        private readonly Traversal $traversal,
        private readonly ?int $level = null,
        private readonly StatementRenderer $renderer = new StatementRenderer(),
    ) {}

    /**
     * Writes the walk rooted at the given symbol as a digraph.
     *
     * A symbol the graph does not hold produces no digraph at all rather than an
     * empty one, so a renderer is never handed a picture of nothing.
     *
     * @param Graph           $graph  The dependency graph to report on
     * @param NodeId<Node>    $symbol The symbol the walk starts at
     * @param OutputInterface $output Where the digraph is written
     */
    public function report(Graph $graph, NodeId $symbol, OutputInterface $output): void
    {
        $cursor = new DotCursor($graph, $this->traversal, $this->level);
        $this->traversal->traverse($graph, $symbol, $cursor->visit(...));

        $nodes = $cursor->nodes();
        if ($nodes === []) {
            return;
        }

        $statements = $this->renderer->open($symbol->toString());
        foreach ($nodes as $node) {
            $statements[] = $this->renderer->node($node);
        }
        foreach ($cursor->edges() as $edge) {
            $statements[] = $this->renderer->edge($edge);
        }
        $statements[] = $this->renderer->close();

        $output->writeln($statements, OutputInterface::OUTPUT_RAW);
    }
}
