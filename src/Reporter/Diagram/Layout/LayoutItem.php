<?php

declare(strict_types=1);

namespace App\Reporter\Diagram\Layout;

/**
 * One place in a column of a drawing, and what takes it.
 *
 * Every place is identified by a key no symbol can spell, so that a symbol and a
 * reference to it can share a drawing without being mistaken for one another.
 *
 * @visibility App\Reporter\Diagram
 */
final class LayoutItem
{
    /**
     * @param string         $key     What identifies the place in the layout
     * @param LayoutItemKind $kind    What takes it
     * @param string         $subject The symbol it stands for: itself, or the one referred to
     * @param int            $layer   The column it is drawn in, the first being column zero
     */
    public function __construct(
        public readonly string $key,
        public readonly LayoutItemKind $kind,
        public readonly string $subject,
        public readonly int $layer,
    ) {}

    /**
     * Returns the place a symbol takes.
     *
     * @param string $id    What identifies the symbol
     * @param int    $layer The column it is drawn in
     *
     * @example A symbol stands for itself
     *     \App\Reporter\Diagram\Layout\LayoutItem::symbol('App\\Invoice', 0)->subject // => 'App\\Invoice'
     *
     * @return self The place
     */
    public static function symbol(string $id, int $layer): self
    {
        return new self(self::keyOf($id), LayoutItemKind::Symbol, $id, $layer);
    }

    /**
     * Returns the place a reference takes, where an arrow points at a symbol drawn in another column.
     *
     * @param string $origin    What identifies the symbol the arrow leaves
     * @param string $target    What identifies the symbol it points at
     * @param int    $layer     The column the reference is drawn in
     * @param bool   $recursion Whether the arrow closes a cycle, the target reaching the symbol it leaves
     *
     * @example A reference stands for the symbol it points at
     *     \App\Reporter\Diagram\Layout\LayoutItem::reference('App\\B', 'App\\A', 2, true)->kind // => \App\Reporter\Diagram\Layout\LayoutItemKind::Recursion
     *
     * @return self The place
     */
    public static function reference(string $origin, string $target, int $layer, bool $recursion): self
    {
        return new self(
            "reference\0".$origin."\0".$target,
            $recursion ? LayoutItemKind::Recursion : LayoutItemKind::Reference,
            $target,
            $layer,
        );
    }

    /**
     * Returns the key the place of a symbol is identified by.
     *
     * @param string $id What identifies the symbol
     *
     * @example The key of a symbol is the key of its place
     *     \App\Reporter\Diagram\Layout\LayoutItem::keyOf('App\\A') === \App\Reporter\Diagram\Layout\LayoutItem::symbol('App\\A', 3)->key // => true
     *
     * @return string The key
     */
    public static function keyOf(string $id): string
    {
        return "symbol\0".$id;
    }
}
