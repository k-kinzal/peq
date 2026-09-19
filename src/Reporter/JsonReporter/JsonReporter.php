<?php

declare(strict_types=1);

namespace App\Reporter\JsonReporter;

use App\Analyzer\Graph\Graph;
use App\Analyzer\Graph\Node;
use App\Analyzer\Graph\NodeId;
use App\Reporter\Reporter;
use App\Reporter\Traversal;
use JsonException;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Reports analysis results as a JSON document.
 *
 * This is the format written for a reader that is not a person: an agent deciding
 * what a change breaks, a script gating a pull request, a tool drawing something of
 * its own. It describes exactly the walk the tree draws — same root, same order, same
 * places the walk stops — but says in fields what the tree says in punctuation, and
 * adds what a terminal has no room for: the kind of each symbol, whether analysis
 * resolved it, where it is written, and through which relations it was reached.
 *
 * The document is written in one piece at the end rather than streamed, because a
 * half-written JSON document is not a JSON document, and a report that fails partway
 * should leave nothing to parse rather than something that parses wrongly.
 */
final class JsonReporter implements Reporter
{
    /**
     * How the document is encoded.
     *
     * Slashes and non-ASCII characters are left as they are, because a Windows path
     * and a non-English identifier should read as themselves. Bytes that are not text
     * are substituted rather than refused, so a file name no encoding explains costs
     * one character instead of the whole report.
     */
    private const ENCODING = JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR;

    /**
     * @param Traversal $traversal The strategy deciding which relations the report follows
     * @param null|int  $level     Deepest level to report, or null for the whole graph
     */
    public function __construct(
        private readonly Traversal $traversal,
        private readonly ?int $level = null,
    ) {}

    /**
     * Writes the walk rooted at the given symbol as a JSON document.
     *
     * A symbol the graph does not hold produces no document at all rather than an
     * empty one: nothing was analysed, so there is nothing to say, and a caller
     * reading the output can tell the two apart.
     *
     * @param Graph           $graph  The dependency graph to report on
     * @param NodeId<Node>    $symbol The symbol the walk starts at
     * @param OutputInterface $output Where the document is written
     *
     * @throws JsonException If the walk cannot be written as JSON
     */
    public function report(Graph $graph, NodeId $symbol, OutputInterface $output): void
    {
        $cursor = new JsonCursor($graph, $this->traversal, $this->level);
        $this->traversal->traverse($graph, $symbol, $cursor->visit(...));

        $reached = $cursor->reached();
        if ($reached === []) {
            return;
        }

        $output->writeln(json_encode([
            'direction' => $this->traversal->direction()->value,
            'symbol' => $symbol->toString(),
            'nodes' => $reached,
        ], self::ENCODING), OutputInterface::OUTPUT_RAW);
    }
}
