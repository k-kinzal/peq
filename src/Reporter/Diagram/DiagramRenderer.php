<?php

declare(strict_types=1);

namespace App\Reporter\Diagram;

use Symfony\Component\Console\Output\OutputInterface;

/**
 * Drawing a graph in a terminal, as numbered symbols and the arrows between them.
 *
 * A picture of a graph needs a plane; a terminal has lines. The way out is to give
 * every symbol a number, list it once, and draw its relations under it as arrows to
 * numbers. Nothing is lost: a symbol several branches reach appears once with one
 * number, a cycle is an arrow back to a lower number, and a hub is visibly a hub
 * because it is pointed at from everywhere.
 *
 * What it is not is a tree. The tree answers "what does this reach, and in what
 * shape"; this answers "what is here, and how is it wired" — the question that comes
 * up when a reader has already narrowed the graph down with a query and wants to see
 * the piece they narrowed it to.
 *
 * @visibility App\Reporter
 */
final class DiagramRenderer
{
    /**
     * Writes a drawing.
     *
     * @param Diagram         $diagram The symbols and relations to draw
     * @param OutputInterface $output  Where the drawing is written
     */
    public function render(Diagram $diagram, OutputInterface $output): void
    {
        foreach ($diagram->nodes() as $node) {
            $output->writeln($this->heading($diagram, $node), OutputInterface::OUTPUT_RAW);

            $leaving = $diagram->leaving($node->id);
            foreach ($leaving as $place => $edge) {
                $output->writeln(
                    $this->arrow($diagram, $edge, $place === count($leaving) - 1),
                    OutputInterface::OUTPUT_RAW,
                );
            }
        }
    }

    /**
     * Writes the line that introduces one symbol.
     *
     * @param Diagram     $diagram The drawing it belongs to
     * @param DiagramNode $node    The symbol
     *
     * @example A symbol is introduced by its number, its name and what it is
     *     $diagram = new \App\Reporter\Diagram\Diagram();
     *     $node = new \App\Reporter\Diagram\DiagramNode('App\\Invoice', 'class', 'src/Invoice.php:12');
     *     $diagram->add($node);
     *     (new \App\Reporter\Diagram\DiagramRenderer())->heading($diagram, $node) // => '(1) App\\Invoice [class] src/Invoice.php:12'
     * @example One with nothing known about it is introduced by its name alone
     *     $diagram = new \App\Reporter\Diagram\Diagram();
     *     $node = new \App\Reporter\Diagram\DiagramNode('App\\Invoice');
     *     $diagram->add($node);
     *     (new \App\Reporter\Diagram\DiagramRenderer())->heading($diagram, $node) // => '(1) App\\Invoice'
     *
     * @return string The line, without a trailing newline
     */
    public function heading(Diagram $diagram, DiagramNode $node): string
    {
        $line = sprintf('(%d) %s', $diagram->numberOf($node->id) ?? 0, $node->id);
        if ($node->kind !== '') {
            $line .= sprintf(' [%s]', $node->kind);
        }

        return $node->location === null ? $line : $line.' '.$node->location;
    }

    /**
     * Writes the line that draws one relation.
     *
     * @param Diagram     $diagram The drawing it belongs to
     * @param DiagramEdge $edge    The relation
     * @param bool        $last    Whether no further relation follows it under the same symbol
     *
     * @example A relation is drawn as an arrow to a numbered symbol
     *     $diagram = new \App\Reporter\Diagram\Diagram();
     *     $diagram->add(new \App\Reporter\Diagram\DiagramNode('a'));
     *     $diagram->add(new \App\Reporter\Diagram\DiagramNode('b'));
     *     $edge = new \App\Reporter\Diagram\DiagramEdge('a', 'b', 'calls');
     *     (new \App\Reporter\Diagram\DiagramRenderer())->arrow($diagram, $edge, true) // => '    └── calls ──> (2) b'
     * @example One with another after it keeps the branch open
     *     $diagram = new \App\Reporter\Diagram\Diagram();
     *     $diagram->add(new \App\Reporter\Diagram\DiagramNode('a'));
     *     $diagram->add(new \App\Reporter\Diagram\DiagramNode('b'));
     *     $edge = new \App\Reporter\Diagram\DiagramEdge('a', 'b', 'calls');
     *     (new \App\Reporter\Diagram\DiagramRenderer())->arrow($diagram, $edge, false) // => '    ├── calls ──> (2) b'
     *
     * @return string The line, without a trailing newline
     */
    public function arrow(Diagram $diagram, DiagramEdge $edge, bool $last): string
    {
        return sprintf(
            '    %s %s ──> (%d) %s',
            $last ? '└──' : '├──',
            $edge->label,
            $diagram->numberOf($edge->target) ?? 0,
            $edge->target,
        );
    }
}
