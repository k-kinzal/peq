<?php

declare(strict_types=1);

namespace App\Reporter\Query;

use App\Gql\Element\ElementGraph;
use App\Gql\Result\ResultTable;
use App\Reporter\Diagram\DiagramEdge;
use App\Reporter\Diagram\DiagramNode;
use Override;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Writes the piece of graph a query's answer holds, as a Graphviz digraph.
 *
 * The same piece of graph the terminal drawing shows, in the form a renderer takes.
 * A query is what makes this worth doing: a digraph of a whole codebase is a hairball,
 * and a digraph of the twelve symbols a question narrowed down to is a picture.
 *
 * @visibility App\Reporter
 */
final readonly class DotWriter implements QueryReporter
{
    /**
     * @param null|ElementGraph $graph The graph the answer came from, so the arrows between its symbols can be drawn
     */
    public function __construct(
        private ?ElementGraph $graph = null,
    ) {}

    /**
     * Writes the answer as a digraph.
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

        $output->writeln('digraph peq {', OutputInterface::OUTPUT_RAW);
        $output->writeln('    rankdir=LR;', OutputInterface::OUTPUT_RAW);
        foreach ($diagram->nodes() as $node) {
            $output->writeln(self::vertex($node), OutputInterface::OUTPUT_RAW);
        }
        foreach ($diagram->nodes() as $node) {
            foreach ($diagram->leaving($node->id) as $edge) {
                $output->writeln(self::arrow($edge), OutputInterface::OUTPUT_RAW);
            }
        }
        $output->writeln('}', OutputInterface::OUTPUT_RAW);
    }

    /**
     * Writes one symbol as a Graphviz statement.
     *
     * @param DiagramNode $node The symbol
     *
     * @example A symbol is a box labelled with its name and what it is
     *     \App\Reporter\Query\DotWriter::vertex(new \App\Reporter\Diagram\DiagramNode('App\\Invoice', 'class')) // => '    "App\\\\Invoice" [label="App\\\\Invoice\\nclass"];'
     *
     * @return string The statement
     */
    public static function vertex(DiagramNode $node): string
    {
        $label = $node->kind === '' ? self::quoted($node->id) : self::quoted($node->id).'\n'.self::quoted($node->kind);

        return sprintf('    "%s" [label="%s"];', self::quoted($node->id), $label);
    }

    /**
     * Writes one relation as a Graphviz statement.
     *
     * @param DiagramEdge $edge The relation
     *
     * @example A relation is an arrow labelled with what it is
     *     \App\Reporter\Query\DotWriter::arrow(new \App\Reporter\Diagram\DiagramEdge('a', 'b', 'calls')) // => '    "a" -> "b" [label="calls"];'
     *
     * @return string The statement
     */
    public static function arrow(DiagramEdge $edge): string
    {
        return sprintf(
            '    "%s" -> "%s" [label="%s"];',
            self::quoted($edge->origin),
            self::quoted($edge->target),
            self::quoted($edge->label),
        );
    }

    /**
     * Writes a piece of text so that Graphviz reads it as text.
     *
     * A PHP name is full of backslashes and a Graphviz string treats a backslash as
     * an escape, so a name written straight through would come back as something else
     * — or not at all.
     *
     * @param string $text The text
     *
     * @example A namespace separator survives being written
     *     \App\Reporter\Query\DotWriter::quoted('App\\Invoice') // => 'App\\\\Invoice'
     * @example And so does a quotation mark
     *     \App\Reporter\Query\DotWriter::quoted('say "hi"') // => 'say \\"hi\\"'
     *
     * @return string The text, escaped for Graphviz
     */
    public static function quoted(string $text): string
    {
        return str_replace(['\\', '"'], ['\\\\', '\"'], $text);
    }
}
