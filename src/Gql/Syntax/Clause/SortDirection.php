<?php

declare(strict_types=1);

namespace App\Gql\Syntax\Clause;

/**
 * Which way a sort key orders rows.
 *
 * Ascending is the default, as GQL says, and descending has to be asked for. The
 * distinction matters more than usual in impact analysis, where the interesting end
 * of a sorted answer is almost always the top: the most-used symbol, the longest
 * chain, the widest blast radius.
 */
enum SortDirection
{
    /** Smallest first, which is what a sort key means when it says nothing */
    case Ascending;

    /** Largest first */
    case Descending;

    /**
     * Returns the multiplier that turns a comparison into this direction.
     *
     * @example Ascending leaves a comparison as it is
     *     \App\Gql\Syntax\Clause\SortDirection::Ascending->factor() // => 1
     * @example Descending turns it around
     *     \App\Gql\Syntax\Clause\SortDirection::Descending->factor() // => -1
     *
     * @return int One for ascending, minus one for descending
     */
    public function factor(): int
    {
        return match ($this) {
            self::Ascending => 1,
            self::Descending => -1,
        };
    }
}
