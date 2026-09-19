<?php

declare(strict_types=1);

namespace App\Gql\Invocation;

use App\Gql\Datum\Datum;
use App\Gql\GqlException;

/**
 * Something that summarises a run of values into one.
 *
 * Aggregates are written as function calls and behave like nothing else in the
 * language: they see every row of a group rather than one row, and they are the only
 * expression whose answer depends on rows other than the one being evaluated. Giving
 * them a shape of their own — offered values one at a time, asked for an answer once —
 * is what lets the same six summaries be used over a whole table, over a group, and
 * over the list of edges a variable-length pattern bound.
 *
 * Every one of them ignores values that are not there, as GQL requires, and answers
 * with nothing when it was offered nothing material. Counting is the exception the
 * standard makes: nothing counted is zero, not nothing.
 */
interface Accumulator
{
    /**
     * Offers one value to the summary.
     *
     * @param Datum $value The value
     *
     * @throws GqlException If the value is not one this summary can take
     */
    public function accept(Datum $value): void;

    /**
     * Returns what the values offered so far summarise to.
     *
     * @return Datum The summary
     */
    public function result(): Datum;
}
