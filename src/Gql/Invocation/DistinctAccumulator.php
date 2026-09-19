<?php

declare(strict_types=1);

namespace App\Gql\Invocation;

use App\Gql\Datum\Datum;
use App\Gql\Datum\DatumIdentity;
use App\Gql\GqlException;
use Override;

/**
 * A summary that is offered each value only once.
 *
 * `DISTINCT` inside an aggregate is written the same way whichever summary it wraps,
 * so it is written once here rather than six times. `count(DISTINCT p)` counts the
 * symbols rather than the matches, which on a graph is usually what is meant: a
 * method reached by four different paths is still one method.
 *
 * @visibility App\Gql
 */
final class DistinctAccumulator implements Accumulator
{
    /**
     * @var array<string, true> The values already offered, by what identifies them
     */
    private array $seen = [];

    /**
     * @param Accumulator $summary The summary being offered each value once
     */
    public function __construct(
        private readonly Accumulator $summary,
    ) {}

    /**
     * Offers a value, unless one equal to it has been offered already.
     *
     * @param Datum $value The value
     *
     * @example A value offered twice is summarised once
     *     $counter = new \App\Gql\Invocation\DistinctAccumulator(new \App\Gql\Invocation\CountAccumulator());
     *     $counter->accept(new \App\Gql\Datum\StringDatum('a'));
     *     $counter->accept(new \App\Gql\Datum\StringDatum('a'));
     *     $counter->result()->toText() // => '1'
     *
     * @throws GqlException If the value is not one the summary can take
     */
    #[Override]
    public function accept(Datum $value): void
    {
        $key = DatumIdentity::key($value);
        if (isset($this->seen[$key])) {
            return;
        }
        $this->seen[$key] = true;
        $this->summary->accept($value);
    }

    /**
     * Returns what the values summarise to.
     *
     * @example A summary offered nothing answers whatever it answers to nothing
     *     (new \App\Gql\Invocation\DistinctAccumulator(new \App\Gql\Invocation\CountAccumulator()))->result()->toText() // => '0'
     *
     * @return Datum The summary
     */
    #[Override]
    public function result(): Datum
    {
        return $this->summary->result();
    }
}
