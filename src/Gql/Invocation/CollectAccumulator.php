<?php

declare(strict_types=1);

namespace App\Gql\Invocation;

use App\Gql\Datum\Datum;
use App\Gql\Datum\DatumKind;
use App\Gql\Datum\ListDatum;
use App\Gql\Datum\NullDatum;
use Override;

/**
 * All of them, as a list.
 *
 * This is the summary that keeps the detail. A query that groups controllers by the
 * repository they reach usually wants both the count and the names, and the names are
 * this. It is also how a group list — the edges a variable-length pattern bound —
 * becomes an ordinary list that the list functions can work on.
 *
 * Like the other summaries and unlike counting, it answers nothing when it was
 * offered nothing material. A reader who wants an empty list there writes
 * `coalesce(collect_list(x), [])`, which is the standard's own way of saying so.
 *
 * @visibility App\Gql
 */
final class CollectAccumulator implements Accumulator
{
    /**
     * @var list<Datum> The values offered so far
     */
    private array $collected = [];

    /**
     * Collects one value.
     *
     * @param Datum $value The value
     *
     * @example Values that are not there are passed over
     *     $collected = new \App\Gql\Invocation\CollectAccumulator();
     *     $collected->accept(new \App\Gql\Datum\NullDatum());
     *     $collected->accept(new \App\Gql\Datum\IntegerDatum(1));
     *     $collected->result()->toText() // => '[1]'
     */
    #[Override]
    public function accept(Datum $value): void
    {
        if ($value->kind() === DatumKind::Null) {
            return;
        }
        $this->collected[] = $value;
    }

    /**
     * Returns all of them, as a list.
     *
     * @example Nothing collected is nothing, not an empty list
     *     (new \App\Gql\Invocation\CollectAccumulator())->result()->kind() // => \App\Gql\Datum\DatumKind::Null
     *
     * @return Datum The list, or the absence of one
     */
    #[Override]
    public function result(): Datum
    {
        return $this->collected === [] ? new NullDatum() : new ListDatum($this->collected);
    }
}
