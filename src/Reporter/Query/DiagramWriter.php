<?php

declare(strict_types=1);

namespace App\Reporter\Query;

use App\Gql\Element\ElementGraph;
use App\Gql\Result\ResultTable;
use App\Reporter\Diagram\DiagramRenderer;
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
 * An answer that holds no symbols and no relations draws nothing. A query that
 * returned names or counts has an answer, and it is a table; drawing an empty diagram
 * for it would suggest the query found nothing.
 *
 * @visibility App\Reporter
 */
final class DiagramWriter implements QueryReporter
{
    /**
     * @param null|ElementGraph $graph    The graph the answer came from, so the arrows between its symbols can be drawn
     * @param DiagramRenderer   $renderer How a drawing is written
     */
    public function __construct(
        private readonly ?ElementGraph $graph = null,
        private readonly DiagramRenderer $renderer = new DiagramRenderer(),
    ) {}

    /**
     * Writes the answer as a drawing.
     *
     * @param ResultTable     $result What the query answered
     * @param OutputInterface $output Where it is written
     */
    #[Override]
    public function report(ResultTable $result, OutputInterface $output): void
    {
        $diagram = ResultElements::of($result, $this->graph);
        if ($diagram->empty()) {
            return;
        }

        $this->renderer->render($diagram, $output);
    }
}
