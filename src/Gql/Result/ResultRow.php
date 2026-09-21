<?php

declare(strict_types=1);

namespace App\Gql\Result;

use App\Gql\Datum\Datum;
use App\Gql\Datum\NullDatum;

/**
 * One row of a result, in the order its columns are shown.
 *
 * Rows are positional rather than named because the columns of a result are decided
 * once, for the whole table, and a row that carried its own names could disagree with
 * them. Everything that reads a result — a table, a JSON document, a drawing —
 * reads the columns and then reads the rows against them.
 */
final readonly class ResultRow
{
    /**
     * @param list<Datum> $values What the row holds, one per column
     */
    public function __construct(
        public array $values,
    ) {}

    /**
     * Returns what the row holds in one column.
     *
     * A column the row does not reach reads as absent, which keeps a reporter from
     * having to check the width of every row it writes.
     *
     * @param int $column Which column, counting from zero
     *
     * @example A row holds what it was built with
     *     (new \App\Gql\Result\ResultRow([new \App\Gql\Datum\IntegerDatum(1)]))->value(0)->toText() // => '1'
     * @example A column past the end of the row is absent
     *     (new \App\Gql\Result\ResultRow([]))->value(3)->kind() // => \App\Gql\Datum\DatumKind::Null
     *
     * @return Datum The value, or the absence of one
     */
    public function value(int $column): Datum
    {
        return $this->values[$column] ?? new NullDatum();
    }
}
