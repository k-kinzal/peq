<?php

declare(strict_types=1);

namespace App\Gql\Syntax\Pattern;

/**
 * How a label expression is built up.
 *
 * Labels are what a pattern selects by, and GQL lets them be combined the way a
 * boolean expression is: `Method|Function` for either, `Member&!Property` for one but
 * not the other, `%` for anything at all. On a graph of PHP symbols that is more
 * useful than it sounds — "every callable that is not a method" and "every class-like
 * that is not an interface" are both one pattern rather than two queries.
 */
enum LabelOperator
{
    /** The element carries a particular label */
    case Named;

    /** The element carries any label at all */
    case Anything;

    /** The element satisfies both of two requirements */
    case Both;

    /** The element satisfies at least one of two requirements */
    case Either;

    /** The element does not satisfy a requirement */
    case Neither;
}
