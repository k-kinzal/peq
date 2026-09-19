<?php

declare(strict_types=1);

namespace App\Gql\Invocation;

use App\Gql\Argument\NumberArgument;
use App\Gql\Datum\Datum;
use App\Gql\Datum\DatumKind;
use App\Gql\Datum\FloatDatum;
use App\Gql\Datum\IntegerDatum;
use App\Gql\Datum\NullDatum;
use App\Gql\GqlException;
use Override;

/**
 * What they add up to.
 *
 * The running total stays whole for as long as every value offered is whole, which
 * keeps a sum of line counts a line count. One approximate value makes the whole sum
 * approximate, which is the same rule arithmetic follows everywhere else in GQL.
 *
 * @visibility App\Gql
 */
final class SumAccumulator implements Accumulator
{
    /**
     * What the values offered so far add up to.
     */
    private float|int $total = 0;

    /**
     * Whether any value has been offered at all.
     */
    private bool $material = false;

    /**
     * Whether any value offered was approximate.
     */
    private bool $approximate = false;

    /**
     * Adds one value to the total.
     *
     * @param Datum $value The value
     *
     * @example Values that are not there are passed over
     *     $sum = new \App\Gql\Invocation\SumAccumulator();
     *     $sum->accept(new \App\Gql\Datum\NullDatum());
     *     $sum->result()->kind() // => \App\Gql\Datum\DatumKind::Null
     * @example Whole numbers add up to a whole number
     *     $sum = new \App\Gql\Invocation\SumAccumulator();
     *     $sum->accept(new \App\Gql\Datum\IntegerDatum(2));
     *     $sum->accept(new \App\Gql\Datum\IntegerDatum(3));
     *     $sum->result()->toText() // => '5'
     *
     * @throws GqlException If the value is not a number
     */
    #[Override]
    public function accept(Datum $value): void
    {
        if ($value->kind() === DatumKind::Null) {
            return;
        }
        $this->material = true;
        $this->approximate = $this->approximate || NumberArgument::approximate($value);
        $this->total += NumberArgument::of($value);
    }

    /**
     * Returns what they add up to.
     *
     * @example Nothing added up is nothing, not zero
     *     (new \App\Gql\Invocation\SumAccumulator())->result()->kind() // => \App\Gql\Datum\DatumKind::Null
     *
     * @return Datum The total, or the absence of one
     */
    #[Override]
    public function result(): Datum
    {
        if (!$this->material) {
            return new NullDatum();
        }

        return $this->approximate ? new FloatDatum((float) $this->total) : new IntegerDatum((int) $this->total);
    }
}
