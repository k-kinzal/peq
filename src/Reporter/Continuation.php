<?php

declare(strict_types=1);

namespace App\Reporter;

/**
 * What a report does with a node the traversal has reached.
 *
 * A traversal hands a node over and asks one thing: keep going below it? The honest
 * answer carries more than a yes or a no, because a report stops below a node for
 * four different reasons and two of them are worth writing next to the node so the
 * reader knows the branch was cut rather than empty.
 *
 * Naming the reasons as a closed type is what keeps every format describing the same
 * walk. A tree, a JSON document, a digraph and a table each decide what to draw, but
 * none of them decides where the walk stops — that decision is made once, and each of
 * them is handed the result.
 */
enum Continuation
{
    /** The node is reported and the report goes on below it */
    case Descends;

    /** The node is already open on the path above it, so going on would not end */
    case Cycle;

    /** The node was expanded elsewhere in this report, so going on would repeat it */
    case Repeat;

    /** The node is outside the analyzed sources, so the graph holds nothing below it */
    case Leaf;

    /** The node sits deeper than the report is bounded at, so it is not reported at all */
    case Beyond;

    /**
     * Reports whether the report goes on below the node.
     *
     * @example A node the report has not met before is expanded
     *     \App\Reporter\Continuation::Descends->descends() // => true
     * @example A node that closes a cycle ends its branch
     *     \App\Reporter\Continuation::Cycle->descends() // => false
     *
     * @return bool True when the traversal should descend into the node
     */
    public function descends(): bool
    {
        return match ($this) {
            self::Descends => true,

            self::Cycle,
            self::Repeat,
            self::Leaf,
            self::Beyond => false,
        };
    }

    /**
     * Reports whether the node belongs to the report at all.
     *
     * A branch that ends is still written down: that a dependency is reached and
     * not followed is part of the answer. A node past the level bound is not, because
     * the bound is the reader saying they do not want to be told about it.
     *
     * @example A branch that ends is still part of the answer
     *     \App\Reporter\Continuation::Repeat->reported() // => true
     * @example What lies past the level bound is not
     *     \App\Reporter\Continuation::Beyond->reported() // => false
     *
     * @return bool True when the node is written into the report
     */
    public function reported(): bool
    {
        return match ($this) {
            self::Descends,
            self::Cycle,
            self::Repeat,
            self::Leaf => true,

            self::Beyond => false,
        };
    }

    /**
     * Names why a branch stops here, for the formats that write it down.
     *
     * Only the two reasons a reader cannot work out for themselves are named. That a
     * builtin type has nothing below it is visible from its kind; that a branch was
     * cut because it loops, or because the same symbol was already expanded further
     * up, is not.
     *
     * @example A cycle is named, so an ended branch is not read as an empty one
     *     \App\Reporter\Continuation::Cycle->marker() // => 'recursive'
     * @example A symbol already expanded elsewhere is named too
     *     \App\Reporter\Continuation::Repeat->marker() // => 'repeated'
     * @example A branch that simply has nothing below it needs no word
     *     \App\Reporter\Continuation::Leaf->marker() // => null
     *
     * @return null|string The word naming why the branch stops, or null when there is nothing to name
     */
    public function marker(): ?string
    {
        return match ($this) {
            self::Cycle => 'recursive',
            self::Repeat => 'repeated',

            self::Descends,
            self::Leaf,
            self::Beyond => null,
        };
    }
}
