<?php

declare(strict_types=1);

namespace App\Reporter\Diagram\Layout;

/**
 * What takes a place in a column of a drawing.
 *
 * Every symbol is drawn once, in one column, and every arrow is drawn from one column
 * to the next. An arrow that cannot be — one to a symbol in the same column, in a
 * column further left, or further right than the next — points instead at a reference
 * to its symbol, the way the tree points at a symbol it has already drawn.
 *
 * @visibility App\Reporter\Diagram
 */
enum LayoutItemKind
{
    /** A symbol the drawing holds */
    case Symbol;

    /** A symbol drawn in another column, pointed at again */
    case Reference;

    /** A symbol drawn in another column, pointed at again by an arrow that closes a cycle through it */
    case Recursion;
}
