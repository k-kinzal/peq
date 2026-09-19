<?php

declare(strict_types=1);

namespace App\Gql\Invocation;

use App\Gql\Datum\Datum;
use App\Gql\Datum\DatumKind;
use App\Gql\Datum\IntegerDatum;
use Override;

/**
 * How many there were.
 *
 * Counting is the one summary that answers zero rather than nothing when it was
 * offered nothing, which is what makes it usable as a test: a group with no matching
 * rows counts zero, and a filter can say so.
 *
 * Written over rows rather than over a value — `count(*)` — it counts every row,
 * including the ones whose value is not there. That difference is the reason GQL has
 * the star at all, and it is what a query asking "how many symbols, including the
 * ones with no line" needs.
 *
 * @visibility App\Gql
 */
final class CountAccumulator implements Accumulator
{
    /**
     * How many values have been counted.
     */
    private int $counted = 0;

    /**
     * @param bool $rows Whether to count rows rather than the values they carry
     */
    public function __construct(
        private readonly bool $rows = false,
    ) {}

    /**
     * Counts one value, or one row.
     *
     * @param Datum $value The value
     *
     * @example Counting values passes over the ones that are not there
     *     $counter = new \App\Gql\Invocation\CountAccumulator();
     *     $counter->accept(new \App\Gql\Datum\NullDatum());
     *     $counter->result()->toText() // => '0'
     * @example Counting rows counts them whatever they carry
     *     $counter = new \App\Gql\Invocation\CountAccumulator(true);
     *     $counter->accept(new \App\Gql\Datum\NullDatum());
     *     $counter->result()->toText() // => '1'
     */
    #[Override]
    public function accept(Datum $value): void
    {
        if ($this->rows || $value->kind() !== DatumKind::Null) {
            ++$this->counted;
        }
    }

    /**
     * Returns how many there were.
     *
     * @example Nothing counted is zero, not nothing
     *     (new \App\Gql\Invocation\CountAccumulator())->result()->toText() // => '0'
     *
     * @return Datum The count
     */
    #[Override]
    public function result(): Datum
    {
        return new IntegerDatum($this->counted);
    }
}
