<?php

declare(strict_types=1);

namespace App\Reporter\TableReporter;

use App\Analyzer\Graph\Graph;
use App\Analyzer\Graph\Node;
use App\Analyzer\Graph\NodeId;
use App\Reporter\Reporter;
use App\Reporter\Traversal;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Reports analysis results as a table, one row per symbol the walk reached.
 *
 * The tree answers "what does this reach" by showing the shape of the reaching. Often
 * the shape is not the question: a reviewer wants the list of what a change touches,
 * with the file and line to open, in an order they can sort and a form they can paste
 * into a pull request. This reporter writes that list, over exactly the walk the tree
 * draws, so the two never disagree about what is affected — only about what is worth
 * showing about it.
 */
final class TableReporter implements Reporter
{
    /**
     * What each column of the table holds.
     */
    private const HEADERS = ['Depth', 'Symbol', 'Kind', 'Location'];

    /**
     * @param Traversal $traversal The strategy deciding which relations the report follows
     * @param null|int  $level     Deepest level to report, or null for the whole graph
     */
    public function __construct(
        private readonly Traversal $traversal,
        private readonly ?int $level = null,
    ) {}

    /**
     * Writes the walk rooted at the given symbol as a table.
     *
     * A symbol the graph does not hold produces no table at all rather than an empty
     * one, so a run that found nothing is not mistaken for a run that found nothing
     * to say about something.
     *
     * @param Graph           $graph  The dependency graph to report on
     * @param NodeId<Node>    $symbol The symbol the walk starts at
     * @param OutputInterface $output Where the table is written
     */
    public function report(Graph $graph, NodeId $symbol, OutputInterface $output): void
    {
        $cursor = new TableCursor($this->level, $graph, $this->traversal->direction());
        $this->traversal->traverse($graph, $symbol, $cursor->visit(...));

        $rows = $cursor->rows();
        if ($rows === []) {
            return;
        }

        (new Table($output))
            ->setHeaders(self::HEADERS)
            ->setRows($rows)
            ->render()
        ;
    }
}
