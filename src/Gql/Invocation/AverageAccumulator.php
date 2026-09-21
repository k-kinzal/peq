<?php

declare(strict_types=1);

namespace App\Gql\Invocation;

use App\Gql\Argument\ExactArithmetic;
use App\Gql\Argument\NumberArgument;
use App\Gql\Datum\Datum;
use App\Gql\Datum\DatumKind;
use App\Gql\Datum\FloatDatum;
use App\Gql\Datum\IntegerDatum;
use App\Gql\Datum\NullDatum;
use App\Gql\GqlException;
use Override;

/**
 * What they come to on average.
 *
 * The average of exact numbers is exact: ISO/IEC 39075 makes its declared type an
 * exact numeric one and leaves which to the implementation (ID096). peq's is a decimal
 * with the larger of the inputs' scales and at least six digits after the point, cut
 * off rather than rounded — so the average of 1 and 2 is `1.500000`, not a float. An
 * approximate value among them makes the average approximate.
 *
 * @visibility App\Gql
 */
final class AverageAccumulator implements Accumulator
{
    /**
     * What the values offered so far add up to.
     */
    private readonly SumAccumulator $total;

    /**
     * How many values have been offered.
     */
    private int $counted = 0;

    /**
     * An average starts with nothing offered and an exact total of nothing.
     */
    public function __construct()
    {
        $this->total = new SumAccumulator();
    }

    /**
     * Adds one value to the average.
     *
     * @param Datum $value The value
     *
     * @example Values that are not there are passed over, and do not count against the average
     *     $average = new \App\Gql\Invocation\AverageAccumulator();
     *     $average->accept(new \App\Gql\Datum\IntegerDatum(2));
     *     $average->accept(new \App\Gql\Datum\NullDatum());
     *     $average->result()->toText() // => '2.000000'
     *
     * @throws GqlException If the value is not a number, or the total is out of range
     */
    #[Override]
    public function accept(Datum $value): void
    {
        if ($value->kind() === DatumKind::Null) {
            return;
        }
        ++$this->counted;
        $this->total->accept($value);
    }

    /**
     * Returns what they come to on average.
     *
     * @example An average of whole numbers is an exact decimal
     *     $average = new \App\Gql\Invocation\AverageAccumulator();
     *     $average->accept(new \App\Gql\Datum\IntegerDatum(1));
     *     $average->accept(new \App\Gql\Datum\IntegerDatum(2));
     *     $average->result()->toText() // => '1.500000'
     * @example Nothing averaged is nothing
     *     (new \App\Gql\Invocation\AverageAccumulator())->result()->kind() // => \App\Gql\Datum\DatumKind::Null
     *
     * @return Datum The average, or the absence of one
     *
     * @throws GqlException If the average is out of range
     */
    #[Override]
    public function result(): Datum
    {
        if ($this->counted === 0) {
            return new NullDatum();
        }
        $total = $this->total->result();
        $exact = NumberArgument::exact($total);
        if ($exact === null) {
            return new FloatDatum((float) NumberArgument::of($total) / $this->counted);
        }

        return ExactArithmetic::divide($exact, new IntegerDatum($this->counted), true);
    }
}
