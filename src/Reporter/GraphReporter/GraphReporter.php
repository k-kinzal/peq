<?php

declare(strict_types=1);

namespace App\Reporter\GraphReporter;

use App\Analyzer\Graph\Graph;
use App\Analyzer\Graph\Node;
use App\Analyzer\Graph\NodeId;
use App\Reporter\Diagram\DiagramRenderer;
use App\Reporter\Reporter;
use App\Reporter\Traversal;
use Override;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Reports analysis results as a drawing of the graph, in the terminal.
 *
 * The tree is the right shape for the question "what does this reach, and how deep",
 * and the wrong one for "how is this wired". A symbol four branches reach is drawn
 * four times in a tree and marked as repeated three of them; a cycle is a word. Both
 * are the tree telling the truth about itself and not about the graph.
 *
 * This draws the graph instead: every symbol once, numbered, with every relation
 * between the symbols drawn as an arrow to a number. It needs no external renderer,
 * unlike the digraph, which is the point — it is for looking at, now, in the terminal
 * where the question was asked.
 */
final class GraphReporter implements Reporter
{
    /**
     * @param Traversal       $traversal The strategy deciding which relations the report follows
     * @param null|int        $level     Deepest level to report, or null for the whole graph
     * @param DiagramRenderer $renderer  How a drawing is written
     */
    public function __construct(
        private readonly Traversal $traversal,
        private readonly ?int $level = null,
        private readonly DiagramRenderer $renderer = new DiagramRenderer(),
    ) {}

    /**
     * Writes the walk rooted at the given symbol as a drawing.
     *
     * @param Graph           $graph  The dependency graph to report on
     * @param NodeId<Node>    $symbol The symbol the walk starts at
     * @param OutputInterface $output Where the drawing is written
     */
    #[Override]
    public function report(Graph $graph, NodeId $symbol, OutputInterface $output): void
    {
        $cursor = new GraphCursor($graph, $this->traversal, $this->level);
        $this->traversal->traverse($graph, $symbol, $cursor->visit(...));

        $this->renderer->render($cursor->diagram(), $output);
    }
}
