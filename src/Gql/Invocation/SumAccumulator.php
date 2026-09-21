<?php

declare(strict_types=1);

namespace App\Gql\Invocation;

use App\Gql\Argument\ExactArithmetic;
use App\Gql\Argument\NumberArgument;
use App\Gql\Datum\Datum;
use App\Gql\Datum\DatumKind;
use App\Gql\Datum\DecimalDatum;
use App\Gql\Datum\FloatDatum;
use App\Gql\Datum\IntegerDatum;
use App\Gql\Datum\NullDatum;
use App\Gql\GqlException;
use Override;

/**
 * What they add up to.
 *
 * The total stays exact for as long as every value offered is exact, which keeps a
 * sum of line counts a line count and a sum of decimals a decimal: ISO/IEC 39075 makes
 * the sum of exact numbers exact (ID095). One approximate value makes the whole sum
 * approximate, which is the same rule arithmetic follows everywhere else in GQL.
 *
 * @visibility App\Gql
 */
final class SumAccumulator implements Accumulator
{
    /**
     * What the exact values offered so far add up to.
     */
    private DecimalDatum|IntegerDatum $exact;

    /**
     * What the values add up to once one of them was approximate.
     */
    private ?float $approximate = null;

    /**
     * Whether any value has been offered at all.
     */
    private bool $material = false;

    /**
     * A sum starts at an exact nothing, so that a sum of nothing but exact numbers stays exact.
     */
    public function __construct()
    {
        $this->exact = new IntegerDatum(0);
    }

    /**
     * Adds one value to the total.
     *
     * @param Datum $value The value
     *
     * @example Values that are not there are passed over
     *     $sum = new \App\Gql\Invocation\SumAccumulator();
     *     $sum->accept(new \App\Gql\Datum\NullDatum());
     *     $sum->result()->kind() // => \App\Gql\Datum\DatumKind::Null
     * @example Exact numbers add up exactly
     *     $sum = new \App\Gql\Invocation\SumAccumulator();
     *     $sum->accept(new \App\Gql\Datum\DecimalDatum(1, 1));
     *     $sum->accept(new \App\Gql\Datum\DecimalDatum(2, 1));
     *     $sum->result()->toText() // => '0.3'
     *
     * @throws GqlException If the value is not a number, or the total is out of range
     */
    #[Override]
    public function accept(Datum $value): void
    {
        if ($value->kind() === DatumKind::Null) {
            return;
        }
        $this->material = true;
        $exact = NumberArgument::exact($value);
        if ($this->approximate === null && $exact !== null) {
            $this->exact = ExactArithmetic::add($this->exact, $exact);

            return;
        }
        $this->approximate = ($this->approximate ?? (float) NumberArgument::of($this->exact)) + (float) NumberArgument::of($value);
    }

    /**
     * Returns what they add up to.
     *
     * @example Nothing added up is nothing, not zero
     *     (new \App\Gql\Invocation\SumAccumulator())->result()->kind() // => \App\Gql\Datum\DatumKind::Null
     * @example One approximate value makes the total approximate
     *     $sum = new \App\Gql\Invocation\SumAccumulator();
     *     $sum->accept(new \App\Gql\Datum\IntegerDatum(1));
     *     $sum->accept(new \App\Gql\Datum\FloatDatum(0.5));
     *     $sum->result()->kind() // => \App\Gql\Datum\DatumKind::Float
     *
     * @return Datum The total, or the absence of one
     */
    #[Override]
    public function result(): Datum
    {
        if (!$this->material) {
            return new NullDatum();
        }

        return $this->approximate === null ? $this->exact : new FloatDatum($this->approximate);
    }
}
