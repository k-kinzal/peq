<?php

declare(strict_types=1);

namespace App\Reporter\Diagram\Layout;

use App\Reporter\Diagram\Diagram;
use App\Reporter\Diagram\DiagramNode;

/**
 * Lays a drawing out in columns, left to right, the way a dependency is read.
 *
 * This is the layered drawing graph drawing has used since Sugiyama, Tagawa and Toda
 * (1981), with its columns chosen the way peq counts levels:
 *
 * 1. Each symbol goes in the column of its distance from where the drawing starts —
 *    the symbols nothing points at, or else the first symbol drawn — so the columns of
 *    a walk are its levels. Every arrow from one column to the next is drawn; every
 *    other arrow points at a reference to its symbol in the next column instead, and
 *    no arrow ever has to skip a column or turn back.
 * 2. Each column is put in the order that crosses the fewest arrows.
 * 3. Each place is given a line near the middle of what it is joined to, so that a
 *    symbol with two arrows leaving it sits between the two symbols they arrive at.
 *    Those lines are then doubled, so that every place is on an even line and the odd
 *    lines between are free for an arrow that has to go over from one lane to another.
 *
 * @visibility App\Reporter\Diagram
 */
final class LayeredLayout
{
    /**
     * Lays a drawing out.
     *
     * @param Diagram $diagram The symbols and relations to lay out
     *
     * @example A symbol pointing at another is drawn a column to its left
     *     $diagram = new \App\Reporter\Diagram\Diagram();
     *     $diagram->add(new \App\Reporter\Diagram\DiagramNode('a'));
     *     $diagram->add(new \App\Reporter\Diagram\DiagramNode('b'));
     *     $diagram->relate(new \App\Reporter\Diagram\DiagramEdge('a', 'b'));
     *     count(\App\Reporter\Diagram\Layout\LayeredLayout::of($diagram)->layers) // => 2
     *
     * @return DiagramLayout Where everything goes
     */
    public static function of(Diagram $diagram): DiagramLayout
    {
        [$order, $layerOf] = self::layers($diagram);
        $items = [];
        foreach ($order as $id) {
            $item = LayoutItem::symbol($id, $layerOf[$id]);
            $items[$item->key] = $item;
        }

        $links = [];
        foreach ($order as $origin) {
            foreach (self::targets($diagram, $origin) as $target) {
                if ($layerOf[$target] === $layerOf[$origin] + 1) {
                    $links[] = [LayoutItem::keyOf($origin), LayoutItem::keyOf($target)];

                    continue;
                }
                $reference = LayoutItem::reference($origin, $target, $layerOf[$origin] + 1, self::reaches($diagram, $target, $origin));
                $items[$reference->key] = $reference;
                $links[] = [LayoutItem::keyOf($origin), $reference->key];
            }
        }

        $layers = LayerOrdering::order(self::columns($items), $links);
        $rows = array_map(static fn (int $row): int => $row * 2, RowPlacement::rows($layers, $links));

        return new DiagramLayout($items, $layers, $rows, $links);
    }

    /**
     * Puts each symbol in the column of its distance from where the drawing starts.
     *
     * A drawing starts at the symbols nothing points at, all at once, or at the first
     * symbol drawn when every symbol is pointed at. Symbols no start reaches are then
     * started from in turn, in the order they were drawn.
     *
     * @param Diagram $diagram The symbols and relations
     *
     * @example A symbol is as far right as the fewest arrows it takes to reach it
     *     $diagram = new \App\Reporter\Diagram\Diagram();
     *     $diagram->add(new \App\Reporter\Diagram\DiagramNode('a'));
     *     $diagram->add(new \App\Reporter\Diagram\DiagramNode('b'));
     *     $diagram->add(new \App\Reporter\Diagram\DiagramNode('c'));
     *     $diagram->relate(new \App\Reporter\Diagram\DiagramEdge('a', 'b'));
     *     $diagram->relate(new \App\Reporter\Diagram\DiagramEdge('b', 'c'));
     *     $diagram->relate(new \App\Reporter\Diagram\DiagramEdge('a', 'c'));
     *     \App\Reporter\Diagram\Layout\LayeredLayout::layers($diagram) // => [['a', 'b', 'c'], ['a' => 0, 'b' => 1, 'c' => 1]]
     *
     * @return array{list<string>, array<string, int>} The symbols in the order they were reached, and the column of each
     */
    public static function layers(Diagram $diagram): array
    {
        $symbols = array_map(static fn (DiagramNode $node): string => $node->id, $diagram->nodes());
        $pointedAt = [];
        foreach ($symbols as $id) {
            foreach (self::targets($diagram, $id) as $target) {
                $pointedAt[$target] = true;
            }
        }
        $starts = array_values(array_filter($symbols, static fn (string $id): bool => !isset($pointedAt[$id])));

        [$order, $layer] = self::spread($diagram, $starts, [], []);
        foreach ($symbols as $symbol) {
            if (!isset($layer[$symbol])) {
                [$order, $layer] = self::spread($diagram, [$symbol], $order, $layer);
            }
        }

        return [$order, $layer];
    }

    /**
     * Starts from some symbols at once, and puts everything they reach in the column of its distance from them.
     *
     * @param Diagram            $diagram The symbols and relations
     * @param list<string>       $starts  The symbols to start from, in column zero
     * @param list<string>       $order   The symbols already placed, in the order they were reached
     * @param array<string, int> $layer   The column of each symbol already placed
     *
     * @example Everything reached is as far right as the fewest arrows it takes
     *     $diagram = new \App\Reporter\Diagram\Diagram();
     *     $diagram->add(new \App\Reporter\Diagram\DiagramNode('a'));
     *     $diagram->add(new \App\Reporter\Diagram\DiagramNode('b'));
     *     $diagram->add(new \App\Reporter\Diagram\DiagramNode('c'));
     *     $diagram->relate(new \App\Reporter\Diagram\DiagramEdge('a', 'b'));
     *     $diagram->relate(new \App\Reporter\Diagram\DiagramEdge('b', 'c'));
     *     $diagram->relate(new \App\Reporter\Diagram\DiagramEdge('c', 'a'));
     *     \App\Reporter\Diagram\Layout\LayeredLayout::spread($diagram, ['b'], [], []) // => [['b', 'c', 'a'], ['b' => 0, 'c' => 1, 'a' => 2]]
     *
     * @return array{list<string>, array<string, int>} Every symbol placed so far, in the order reached, and the column of each
     */
    public static function spread(Diagram $diagram, array $starts, array $order, array $layer): array
    {
        $queue = [];
        foreach ($starts as $start) {
            $layer[$start] = 0;
            $order[] = $start;
            $queue[] = $start;
        }
        while ($queue !== []) {
            $id = array_shift($queue);
            foreach (self::targets($diagram, $id) as $target) {
                if (!isset($layer[$target])) {
                    $layer[$target] = $layer[$id] + 1;
                    $order[] = $target;
                    $queue[] = $target;
                }
            }
        }

        return [$order, $layer];
    }

    /**
     * Returns the symbols a symbol points at, each once.
     *
     * Two relations between the same two symbols — a call and a parameter type — are
     * one arrow in a drawing of how things are wired.
     *
     * @param Diagram $diagram The symbols and relations
     * @param string  $id      What identifies the symbol
     *
     * @example Two relations to one symbol are one arrow
     *     $diagram = new \App\Reporter\Diagram\Diagram();
     *     $diagram->add(new \App\Reporter\Diagram\DiagramNode('a'));
     *     $diagram->add(new \App\Reporter\Diagram\DiagramNode('b'));
     *     $diagram->relate(new \App\Reporter\Diagram\DiagramEdge('a', 'b', 'calls'));
     *     $diagram->relate(new \App\Reporter\Diagram\DiagramEdge('a', 'b', 'names'));
     *     \App\Reporter\Diagram\Layout\LayeredLayout::targets($diagram, 'a') // => ['b']
     *
     * @return list<string> The symbols pointed at, in the order the relations were drawn
     */
    public static function targets(Diagram $diagram, string $id): array
    {
        $targets = [];
        foreach ($diagram->leaving($id) as $edge) {
            if (!in_array($edge->target, $targets, true)) {
                $targets[] = $edge->target;
            }
        }

        return $targets;
    }

    /**
     * Reports whether one symbol reaches another by following arrows.
     *
     * An arrow to a symbol that reaches back to where the arrow started closes a
     * cycle, and is marked as recursion rather than as a plain reference.
     *
     * @param Diagram $diagram The symbols and relations
     * @param string  $from    What identifies the symbol to start from
     * @param string  $to      What identifies the symbol to reach
     *
     * @example A symbol reaches what it points at through another
     *     $diagram = new \App\Reporter\Diagram\Diagram();
     *     $diagram->add(new \App\Reporter\Diagram\DiagramNode('a'));
     *     $diagram->add(new \App\Reporter\Diagram\DiagramNode('b'));
     *     $diagram->add(new \App\Reporter\Diagram\DiagramNode('c'));
     *     $diagram->relate(new \App\Reporter\Diagram\DiagramEdge('a', 'b'));
     *     $diagram->relate(new \App\Reporter\Diagram\DiagramEdge('b', 'c'));
     *     \App\Reporter\Diagram\Layout\LayeredLayout::reaches($diagram, 'a', 'c') // => true
     * @example And does not reach what only points at it
     *     $diagram = new \App\Reporter\Diagram\Diagram();
     *     $diagram->add(new \App\Reporter\Diagram\DiagramNode('a'));
     *     $diagram->add(new \App\Reporter\Diagram\DiagramNode('b'));
     *     $diagram->relate(new \App\Reporter\Diagram\DiagramEdge('a', 'b'));
     *     \App\Reporter\Diagram\Layout\LayeredLayout::reaches($diagram, 'b', 'a') // => false
     *
     * @return bool True when it does, as every symbol reaches itself
     */
    public static function reaches(Diagram $diagram, string $from, string $to): bool
    {
        $seen = [$from => true];
        $queue = [$from];
        while ($queue !== []) {
            $id = array_shift($queue);
            if ($id === $to) {
                return true;
            }
            foreach (self::targets($diagram, $id) as $target) {
                if (!isset($seen[$target])) {
                    $seen[$target] = true;
                    $queue[] = $target;
                }
            }
        }

        return false;
    }

    /**
     * Gathers the places into their columns, in the order they were made.
     *
     * @param array<string, LayoutItem> $items The places
     *
     * @example Places are gathered by column
     *     $a = \App\Reporter\Diagram\Layout\LayoutItem::symbol('a', 0);
     *     $b = \App\Reporter\Diagram\Layout\LayoutItem::symbol('b', 1);
     *     count(\App\Reporter\Diagram\Layout\LayeredLayout::columns([$a->key => $a, $b->key => $b])) // => 2
     *
     * @return list<list<string>> The places in each column
     */
    public static function columns(array $items): array
    {
        $depth = 0;
        foreach ($items as $item) {
            $depth = max($depth, $item->layer + 1);
        }
        $columns = array_fill(0, $depth, []);
        foreach ($items as $item) {
            $columns[$item->layer][] = $item->key;
        }

        return $columns;
    }
}
