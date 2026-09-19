<?php

declare(strict_types=1);

namespace App\Gql\Invocation;

use App\Gql\Datum\Datum;
use App\Gql\Datum\DatumKind;
use App\Gql\Datum\DatumOrder;
use App\Gql\Datum\FloatDatum;
use App\Gql\Datum\NullDatum;
use App\Gql\Evaluation\Arithmetic;
use App\Gql\GqlException;
use Override;

/**
 * What they come to on average.
 *
 * An average is always approximate, even over whole numbers, because the average of
 * whole numbers usually is not one. Reporting it as whole would be rounding without
 * saying so.
 *
 * @visibility App\Gql
 */
final class AverageAccumulator implements Accumulator
{
    /**
     * What the values offered so far add up to.
     */
    private float $total = 0.0;

    /**
     * How many values have been offered.
     */
    private int $counted = 0;

    /**
     * Adds one value to the average.
     *
     * @param Datum $value The value
     *
     * @example Values that are not there are passed over, and do not count against the average
     *     $average = new \App\Gql\Invocation\AverageAccumulator();
     *     $average->accept(new \App\Gql\Datum\IntegerDatum(2));
     *     $average->accept(new \App\Gql\Datum\NullDatum());
     *     $average->result()->toText() // => '2.0'
     *
     * @throws GqlException If the value is not a number
     */
    #[Override]
    public function accept(Datum $value): void
    {
        if ($value->kind() === DatumKind::Null) {
            return;
        }
        Arithmetic::requireNumber($value);

        ++$this->counted;
        $this->total += DatumOrder::numberOf($value);
    }

    /**
     * Returns what they come to on average.
     *
     * @example An average of whole numbers is still approximate
     *     $average = new \App\Gql\Invocation\AverageAccumulator();
     *     $average->accept(new \App\Gql\Datum\IntegerDatum(1));
     *     $average->accept(new \App\Gql\Datum\IntegerDatum(2));
     *     $average->result()->toText() // => '1.5'
     * @example Nothing averaged is nothing
     *     (new \App\Gql\Invocation\AverageAccumulator())->result()->kind() // => \App\Gql\Datum\DatumKind::Null
     *
     * @return Datum The average, or the absence of one
     */
    #[Override]
    public function result(): Datum
    {
        return $this->counted === 0 ? new NullDatum() : new FloatDatum($this->total / $this->counted);
    }
}
