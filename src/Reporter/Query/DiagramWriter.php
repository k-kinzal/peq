<?php

declare(strict_types=1);

namespace App\Reporter\Query;

use App\Gql\Element\ElementGraph;
use App\Gql\Result\ResultTable;
use App\Reporter\Diagram\DiagramRenderer;
use App\Reporter\Diagram\TerminalRenderer;
use Override;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Writes the piece of graph a query's answer holds, as a drawing.
 *
 * This is the format a query was narrowed down for. A table of forty symbols says
 * what was found; a drawing of them says how they are wired, which is the question
 * that comes next — and because the query has already cut the graph down to what
 * matters, the drawing is small enough to read.
 *
 * A nonempty answer that holds no symbols or relations cannot be drawn. Report that
 * mismatch so a reader can return elements or choose a table for names and counts.
 *
 * @visibility App\Reporter
 */
final readonly class DiagramWriter implements QueryReporter
{
    /**
     * @param null|ElementGraph $graph    The graph the answer came from, so the arrows between its symbols can be drawn
     * @param DiagramRenderer   $renderer How a drawing is written
     */
    public function __construct(
        private ?ElementGraph $graph = null,
        private DiagramRenderer $renderer = new TerminalRenderer(),
    ) {}

    /**
     * Writes the answer as a drawing.
     *
     * @param ResultTable     $result What the query answered
     * @param OutputInterface $output Where it is written
     *
     * @throws QueryOutputException When a nonempty result contains no graph elements
     */
    #[Override]
    public function report(ResultTable $result, OutputInterface $output): void
    {
        $diagram = ResultElements::of($result, $this->graph);
        if ($diagram->empty()) {
            if ($result->rows !== []) {
                throw new QueryOutputException('Graph output requires nodes, edges or paths, but the result contains none. Return elements (for example, RETURN n instead of RETURN n.id), or use --output=table or --output=json.');
            }

            return;
        }

        $this->renderer->render($diagram, $output);
    }
}
