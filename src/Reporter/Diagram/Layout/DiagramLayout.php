<?php

declare(strict_types=1);

namespace App\Reporter\Diagram\Layout;

/**
 * Where everything in a drawing goes: which column, and which line.
 *
 * A layout says nothing about characters. It places symbols, references and passing
 * arrows in columns and on lines, and records every arrow as a link between two
 * neighbouring columns; how wide a column is and what an arrow is drawn with are left
 * to whatever draws it.
 *
 * @visibility App\Reporter\Diagram
 */
final readonly class DiagramLayout
{
    /**
     * @param array<string, LayoutItem>   $items  Everything drawn, by what identifies its place
     * @param list<list<string>>          $layers The places in each column, top to bottom
     * @param array<string, int>          $rows   The line each place is drawn on, the first being line zero
     * @param list<array{string, string}> $links  The arrows, each from a place to one in the next column
     */
    public function __construct(
        public array $items,
        public array $layers,
        public array $rows,
        public array $links,
    ) {}

    /**
     * Returns the arrows that leave one column for the next.
     *
     * @param int $layer The column they leave
     *
     * @example A column nothing leaves has no arrows leaving it
     *     (new \App\Reporter\Diagram\Layout\DiagramLayout([], [], [], []))->linksFrom(0) // => []
     *
     * @return list<array{string, string}> The arrows
     */
    public function linksFrom(int $layer): array
    {
        return array_values(array_filter(
            $this->links,
            fn (array $link): bool => $this->items[$link[0]]->layer === $layer,
        ));
    }

    /**
     * Reports whether an arrow leaves a place.
     *
     * @param string $key What identifies the place
     *
     * @example A place nothing leaves is a leaf
     *     (new \App\Reporter\Diagram\Layout\DiagramLayout([], [], [], []))->leaves('a') // => false
     *
     * @return bool True when one does
     */
    public function leaves(string $key): bool
    {
        foreach ($this->links as [$origin]) {
            if ($origin === $key) {
                return true;
            }
        }

        return false;
    }
}
