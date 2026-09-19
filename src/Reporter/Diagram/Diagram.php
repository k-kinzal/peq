<?php

declare(strict_types=1);

namespace App\Reporter\Diagram;

/**
 * The symbols and relations a drawing is made of.
 *
 * A dependency graph is not a tree, and drawing it as one loses the two things that
 * make it a graph: a symbol several branches reach is one symbol, and a cycle is a
 * relation rather than a word at the end of a branch. A drawing numbers every symbol
 * once and shows every relation between the symbols it holds, so both come back.
 *
 * Only relations between symbols the drawing holds are kept. A relation pointing out
 * of what was drawn would be an arrow to nowhere, which says less than leaving it
 * out: the reader would not be able to tell a symbol that was left out from one that
 * does not exist.
 *
 * @visibility App\Reporter
 */
final class Diagram
{
    /**
     * @var array<string, DiagramNode> The symbols, by what identifies them, in the order they were drawn
     */
    private array $nodes = [];

    /**
     * @var list<DiagramEdge> The relations, in the order they were drawn
     */
    private array $edges = [];

    /**
     * @var array<string, true> The relations already drawn, by what identifies them
     */
    private array $drawn = [];

    /**
     * Records a symbol in the drawing.
     *
     * A symbol drawn twice is drawn once: the first drawing keeps its number, so a
     * walk that reaches the same symbol again does not renumber everything after it.
     *
     * @param DiagramNode $node The symbol
     *
     * @example A symbol drawn twice keeps the number it was first given
     *     $diagram = new \App\Reporter\Diagram\Diagram();
     *     $diagram->add(new \App\Reporter\Diagram\DiagramNode('a'));
     *     $diagram->add(new \App\Reporter\Diagram\DiagramNode('a'));
     *     count($diagram->nodes()) // => 1
     */
    public function add(DiagramNode $node): void
    {
        if (!isset($this->nodes[$node->id])) {
            $this->nodes[$node->id] = $node;
        }
    }

    /**
     * Records a relation in the drawing.
     *
     * @param DiagramEdge $edge The relation
     *
     * @example A relation drawn twice is drawn once
     *     $diagram = new \App\Reporter\Diagram\Diagram();
     *     $diagram->relate(new \App\Reporter\Diagram\DiagramEdge('a', 'b', 'calls'));
     *     $diagram->relate(new \App\Reporter\Diagram\DiagramEdge('a', 'b', 'calls'));
     *     count($diagram->edges()) // => 1
     */
    public function relate(DiagramEdge $edge): void
    {
        $signature = $edge->signature();
        if (isset($this->drawn[$signature])) {
            return;
        }
        $this->drawn[$signature] = true;
        $this->edges[] = $edge;
    }

    /**
     * Returns the symbols in the drawing, in the order they were drawn.
     *
     * @example A drawing with nothing in it holds no symbols
     *     (new \App\Reporter\Diagram\Diagram())->nodes() // => []
     *
     * @return list<DiagramNode> The symbols
     */
    public function nodes(): array
    {
        return array_values($this->nodes);
    }

    /**
     * Returns the relations in the drawing, in the order they were drawn.
     *
     * @example A drawing with nothing in it holds no relations
     *     (new \App\Reporter\Diagram\Diagram())->edges() // => []
     *
     * @return list<DiagramEdge> The relations
     */
    public function edges(): array
    {
        return $this->edges;
    }

    /**
     * Returns the relations leaving a symbol, to symbols the drawing also holds.
     *
     * @param string $id What identifies the symbol
     *
     * @example A relation to a symbol that was not drawn is left out
     *     $diagram = new \App\Reporter\Diagram\Diagram();
     *     $diagram->add(new \App\Reporter\Diagram\DiagramNode('a'));
     *     $diagram->relate(new \App\Reporter\Diagram\DiagramEdge('a', 'b', 'calls'));
     *     $diagram->leaving('a') // => []
     *
     * @return list<DiagramEdge> The relations
     */
    public function leaving(string $id): array
    {
        $leaving = [];
        foreach ($this->edges as $edge) {
            if ($edge->origin === $id && isset($this->nodes[$edge->target])) {
                $leaving[] = $edge;
            }
        }

        return $leaving;
    }

    /**
     * Returns the number a symbol is drawn under.
     *
     * Numbers start at one, because they are read by a person rather than indexed
     * into by a program.
     *
     * @param string $id What identifies the symbol
     *
     * @example The first symbol drawn is the first symbol
     *     $diagram = new \App\Reporter\Diagram\Diagram();
     *     $diagram->add(new \App\Reporter\Diagram\DiagramNode('a'));
     *     $diagram->numberOf('a') // => 1
     * @example A symbol that was never drawn has no number
     *     (new \App\Reporter\Diagram\Diagram())->numberOf('a') // => null
     *
     * @return null|int The number, or null when the drawing does not hold it
     */
    public function numberOf(string $id): ?int
    {
        $place = array_search($id, array_keys($this->nodes), true);

        return $place === false ? null : $place + 1;
    }

    /**
     * Reports whether the drawing holds nothing at all.
     *
     * @example A drawing nothing was added to holds nothing
     *     (new \App\Reporter\Diagram\Diagram())->empty() // => true
     *
     * @return bool True when no symbol was drawn
     */
    public function empty(): bool
    {
        return $this->nodes === [];
    }
}
