<?php

declare(strict_types=1);

namespace App\Gql\Invocation;

use App\Gql\Datum\Datum;
use App\Gql\Datum\DatumKind;
use App\Gql\Datum\DatumOrder;
use App\Gql\Datum\NullDatum;
use Override;

/**
 * The smallest, or the largest.
 *
 * Both are one summary, because they differ only in which way they compare. They
 * order the way sorting does, which passes over nothing but the absence of a value:
 * values of kinds GQL gives no order between have no smallest, so a column holding a
 * number on one symbol and a string on another is a data exception, "values not
 * comparable" (22G04), rather than an answer that depends on which came first.
 *
 * @visibility App\Gql
 */
final class ExtremeAccumulator implements Accumulator
{
    /**
     * The smallest or largest value offered so far.
     */
    private ?Datum $chosen = null;

    /**
     * @param bool $largest Whether to keep the largest rather than the smallest
     */
    public function __construct(
        private readonly bool $largest = false,
    ) {}

    /**
     * Offers one value.
     *
     * @param Datum $value The value
     *
     * @example Values that are not there are passed over
     *     $least = new \App\Gql\Invocation\ExtremeAccumulator();
     *     $least->accept(new \App\Gql\Datum\NullDatum());
     *     $least->accept(new \App\Gql\Datum\IntegerDatum(3));
     *     $least->result()->toText() // => '3'
     */
    #[Override]
    public function accept(Datum $value): void
    {
        if ($value->kind() === DatumKind::Null) {
            return;
        }
        if ($this->chosen === null) {
            $this->chosen = $value;

            return;
        }

        $order = DatumOrder::sort($value, $this->chosen);
        if ($this->largest ? $order > 0 : $order < 0) {
            $this->chosen = $value;
        }
    }

    /**
     * Returns the smallest, or the largest.
     *
     * @example The smallest of what was offered
     *     $least = new \App\Gql\Invocation\ExtremeAccumulator();
     *     $least->accept(new \App\Gql\Datum\IntegerDatum(3));
     *     $least->accept(new \App\Gql\Datum\IntegerDatum(1));
     *     $least->result()->toText() // => '1'
     * @example The largest, when that is what was asked for
     *     $most = new \App\Gql\Invocation\ExtremeAccumulator(true);
     *     $most->accept(new \App\Gql\Datum\IntegerDatum(3));
     *     $most->accept(new \App\Gql\Datum\IntegerDatum(1));
     *     $most->result()->toText() // => '3'
     *
     * @return Datum The value, or the absence of one
     */
    #[Override]
    public function result(): Datum
    {
        return $this->chosen ?? new NullDatum();
    }
}
