<?php

declare(strict_types=1);

namespace Tests\Fixture\Gql;

use App\Gql\Datum\Datum;
use App\Gql\Execution\QueryExecution;
use App\Gql\GqlException;
use App\Gql\Result\ResultColumn;
use App\Gql\Result\ResultRow;
use App\Gql\Result\ResultTable;

/**
 * A query run against the sample codebase, with its answer written on one line.
 *
 * A result table is columns, types and rows, and a test that asserted over the object
 * would spend five lines saying what one line of text says plainly. Written out, an
 * answer reads `[name:STRING] show; store` — the columns with their types, then the
 * rows — which is short enough to sit in a data provider beside the query that
 * produced it.
 */
final class AnsweredQuery
{
    /**
     * Runs a query against the sample codebase and writes its answer out.
     *
     * @param string $query The query, as it was written
     *
     * @return string The answer, on one line
     *
     * @throws GqlException If the query cannot be read or cannot be run
     */
    public static function of(string $query): string
    {
        return self::written(self::table($query));
    }

    /**
     * Runs a query against the sample codebase.
     *
     * @param string $query    The query, as it was written
     * @param int    $hopLimit How far a repetition goes when no upper bound was written
     *
     * @return ResultTable What it answered
     *
     * @throws GqlException If the query cannot be read or cannot be run
     */
    public static function table(string $query, int $hopLimit = 10): ResultTable
    {
        return (new QueryExecution(SampleGraph::elements(), $hopLimit))->query($query);
    }

    /**
     * Writes an answer out on one line.
     *
     * @param ResultTable $answered The answer
     *
     * @return string The answer, on one line
     */
    public static function written(ResultTable $answered): string
    {
        $headings = array_map(
            static fn (ResultColumn $column): string => $column->heading.':'.$column->type,
            $answered->columns,
        );
        $rows = array_map(
            static fn (ResultRow $row): string => implode(', ', array_map(
                static fn (Datum $value): string => $value->toText(),
                $row->values,
            )),
            $answered->rows,
        );

        return '['.implode(', ', $headings).'] '.implode('; ', $rows);
    }
}
