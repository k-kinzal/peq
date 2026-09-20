<?php

declare(strict_types=1);

namespace Spec\Context;

use App\Gql\Datum\Datum;
use App\Gql\Result\ResultColumn;
use App\Gql\Result\ResultRow;
use App\Gql\Result\ResultTable;

/**
 * A result table written out the way a scenario states one.
 *
 * ISO/IEC 39075 says a result table carries the name and the value type of every column
 * and then its rows, so that is what a scenario states and this is what writes it:
 * `name:STRING, line:INT64` for the columns, one line per row for the rest. Nothing
 * about peq's own objects reaches a feature file, which is what keeps the specification
 * a statement about GQL rather than about this implementation of it.
 */
final class SpecificationOutcome
{
    /**
     * Writes the columns of a result table out.
     *
     * @param ResultTable $table The table
     *
     * @return string The columns, written `name:TYPE` and separated by commas
     */
    public static function columnsOf(ResultTable $table): string
    {
        return implode(', ', array_map(
            static fn (ResultColumn $column): string => $column->heading.':'.$column->type,
            $table->columns,
        ));
    }

    /**
     * Writes the rows of a result table out, one per line.
     *
     * @param ResultTable $table The table
     *
     * @return string The rows
     */
    public static function rowsOf(ResultTable $table): string
    {
        return implode("\n", array_map(
            static fn (ResultRow $row): string => implode(', ', array_map(
                static fn (Datum $value): string => $value->toText(),
                $row->values,
            )),
            $table->rows,
        ));
    }
}
