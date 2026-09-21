<?php

declare(strict_types=1);

namespace App\Reporter\Diagram;

use Override;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Writes a drawing as a Mermaid flowchart, for a page that renders one.
 *
 * GitHub, GitLab and most documentation sites draw a `mermaid` code block as a
 * picture, which makes this the format for putting a piece of the graph in a pull
 * request or a design note. Symbols are numbered in the order they were drawn, as
 * `n1`, `n2` and so on, because a symbol's name is not something Mermaid accepts as an
 * identifier; the name is the label instead. Every relation is an arrow labelled with
 * what it is, and the flowchart runs left to right, as the terminal drawing does.
 *
 * @visibility App\Reporter
 */
final class MermaidRenderer implements DiagramRenderer
{
    /**
     * Writes the flowchart, or nothing when the drawing holds nothing.
     *
     * @param Diagram         $diagram The symbols and relations to write
     * @param OutputInterface $output  Where the flowchart is written
     */
    #[Override]
    public function render(Diagram $diagram, OutputInterface $output): void
    {
        foreach ($this->lines($diagram) as $line) {
            $output->writeln($line, OutputInterface::OUTPUT_RAW);
        }
    }

    /**
     * Returns the flowchart, one string to a line.
     *
     * @param Diagram $diagram The symbols and relations to write
     *
     * @example A relation is an arrow between numbered symbols, labelled with what it is
     *     $diagram = new \App\Reporter\Diagram\Diagram();
     *     $diagram->add(new \App\Reporter\Diagram\DiagramNode('App\\A'));
     *     $diagram->add(new \App\Reporter\Diagram\DiagramNode('App\\B'));
     *     $diagram->relate(new \App\Reporter\Diagram\DiagramEdge('App\\A', 'App\\B', 'calls'));
     *     (new \App\Reporter\Diagram\MermaidRenderer())->lines($diagram) // => ['flowchart LR', '    n1["App\\A"]', '    n2["App\\B"]', '    n1 -->|"calls"| n2']
     * @example A drawing that holds nothing is no flowchart
     *     (new \App\Reporter\Diagram\MermaidRenderer())->lines(new \App\Reporter\Diagram\Diagram()) // => []
     *
     * @return list<string> The lines
     */
    public function lines(Diagram $diagram): array
    {
        if ($diagram->empty()) {
            return [];
        }

        $lines = ['flowchart LR'];
        foreach ($diagram->nodes() as $node) {
            $lines[] = sprintf('    n%d["%s"]', $diagram->numberOf($node->id) ?? 0, self::escape($node->id));
        }
        foreach ($diagram->nodes() as $node) {
            foreach ($diagram->leaving($node->id) as $edge) {
                $lines[] = $edge->label === ''
                    ? sprintf('    n%d --> n%d', $diagram->numberOf($edge->origin) ?? 0, $diagram->numberOf($edge->target) ?? 0)
                    : sprintf(
                        '    n%d -->|"%s"| n%d',
                        $diagram->numberOf($edge->origin) ?? 0,
                        self::escape($edge->label),
                        $diagram->numberOf($edge->target) ?? 0,
                    );
            }
        }

        return $lines;
    }

    /**
     * Writes text so that Mermaid reads it as text, whatever it holds.
     *
     * Mermaid ends a quoted label at a double quote and reads angle brackets as HTML,
     * so those are written as the entity codes Mermaid defines, and so is the `#` that
     * begins one.
     *
     * @param string $text The text
     *
     * @example A double quote is written as an entity
     *     \App\Reporter\Diagram\MermaidRenderer::escape('say "hi"') // => 'say #quot;hi#quot;'
     * @example A name needs nothing done to it
     *     \App\Reporter\Diagram\MermaidRenderer::escape('App\\Http\\Controller::show') // => 'App\\Http\\Controller::show'
     *
     * @return string The text as Mermaid is to read it
     */
    public static function escape(string $text): string
    {
        return strtr($text, ['#' => '#35;', '"' => '#quot;', '<' => '#lt;', '>' => '#gt;']);
    }
}
