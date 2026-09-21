<?php

declare(strict_types=1);

namespace App\Gql\Execution;

use App\Gql\Datum\DatumIdentity;
use App\Gql\GqlException;
use App\Gql\Result\ResultColumn;
use App\Gql\Result\ResultRow;
use App\Gql\Result\ResultTable;
use App\Gql\StatusCode;
use App\Gql\Syntax\SetOperator;

/**
 * Combining what two runs of clauses answered.
 *
 * Set operations are how one query asks a question with two shapes: "everything that
 * reaches this class, whether by calling it or by extending it" is two patterns and
 * one answer. The difference and the intersection are the ones a question about
 * layering reaches for — what the controllers reach that the domain does not.
 *
 * Both sides must have the same number of columns, because a row of one has to be a
 * row of the other for any of this to mean anything. The headings are taken from the
 * left, which is the side a reader wrote first.
 *
 * @visibility App\Gql
 */
final class SetOperation
{
    /**
     * Returns the two results combined.
     *
     * @param SetOperator $operator How to combine them
     * @param ResultTable $left     What the run on the left answered
     * @param ResultTable $right    What the run on the right answered
     *
     * @example Keeping everything keeps the rows of both
     *     $row = new \App\Gql\Result\ResultRow([new \App\Gql\Datum\IntegerDatum(1)]);
     *     $column = [new \App\Gql\Result\ResultColumn('n', 'INT64')];
     *     $table = new \App\Gql\Result\ResultTable($column, [$row]);
     *     count(\App\Gql\Execution\SetOperation::combine(\App\Gql\Syntax\SetOperator::UnionAll, $table, $table)->rows) // => 2
     * @example Dropping what repeats keeps one of them
     *     $row = new \App\Gql\Result\ResultRow([new \App\Gql\Datum\IntegerDatum(1)]);
     *     $column = [new \App\Gql\Result\ResultColumn('n', 'INT64')];
     *     $table = new \App\Gql\Result\ResultTable($column, [$row]);
     *     count(\App\Gql\Execution\SetOperation::combine(\App\Gql\Syntax\SetOperator::Union, $table, $table)->rows) // => 1
     *
     * @return ResultTable The two combined
     *
     * @throws GqlException If the two sides do not have the same number of columns
     */
    public static function combine(SetOperator $operator, ResultTable $left, ResultTable $right): ResultTable
    {
        $rows = match ($operator) {
            SetOperator::Otherwise => null,
            SetOperator::UnionAll => [...$left->rows, ...$right->rows],
            SetOperator::Union => self::once([...$left->rows, ...$right->rows]),
            SetOperator::Intersect, SetOperator::Except => self::once(self::sharing($left->rows, $right->rows, $operator === SetOperator::Intersect)),
        };
        if ($rows === null) {
            return $left->rows === [] ? $right : $left;
        }
        self::requireSameShape($left, $right);

        return new ResultTable(self::retyped($left->columns, $rows), $rows);
    }

    /**
     * Returns the columns of a combined result, typed by what they hold once combined.
     *
     * A column's type describes its values, and a union holds the values of both
     * sides: `RETURN 1 AS n UNION ALL RETURN 'a' AS n` holds a number and a string, so
     * its column holds any value rather than the whole numbers the left side held.
     *
     * @param list<ResultColumn> $columns The columns, named as the left side names them
     * @param list<ResultRow>    $rows    The rows of the combined result
     *
     * @example A column holding the values of two kinds holds any value
     *     $columns = [new \App\Gql\Result\ResultColumn('n', 'INT64')];
     *     $rows = [new \App\Gql\Result\ResultRow([new \App\Gql\Datum\IntegerDatum(1)]), new \App\Gql\Result\ResultRow([new \App\Gql\Datum\StringDatum('a')])];
     *     \App\Gql\Execution\SetOperation::retyped($columns, $rows)[0]->type // => 'ANY'
     *
     * @return list<ResultColumn> The columns
     */
    public static function retyped(array $columns, array $rows): array
    {
        $retyped = [];
        foreach ($columns as $index => $column) {
            $retyped[] = new ResultColumn($column->heading, ResultTable::typeOf($rows, $index));
        }

        return $retyped;
    }

    /**
     * Reports two results that cannot be combined as the mistake they are.
     *
     * @param ResultTable $left  What the run on the left answered
     * @param ResultTable $right What the run on the right answered
     *
     * @example Two results of the same shape pass without comment
     *     $table = new \App\Gql\Result\ResultTable([new \App\Gql\Result\ResultColumn('n', 'INT64')], []);
     *     \App\Gql\Execution\SetOperation::requireSameShape($table, $table) // => null
     * @example Two of different shapes are reported rather than lined up
     *     $left = new \App\Gql\Result\ResultTable([new \App\Gql\Result\ResultColumn('n', 'INT64')], []);
     *     \App\Gql\Execution\SetOperation::requireSameShape($left, \App\Gql\Result\ResultTable::nothing()) // throws \App\Gql\GqlException: syntax error
     *
     * @throws GqlException If the two sides do not have the same number of columns
     */
    public static function requireSameShape(ResultTable $left, ResultTable $right): void
    {
        if (count($left->columns) === count($right->columns)) {
            return;
        }

        throw GqlException::because(
            StatusCode::SyntaxError,
            sprintf(
                'two results can only be combined when they have the same columns, and these have %d and %d',
                count($left->columns),
                count($right->columns),
            ),
        );
    }

    /**
     * Returns the rows with those that repeat shown once.
     *
     * @param list<ResultRow> $rows The rows
     *
     * @example Rows that hold the same values are shown once
     *     $rows = [new \App\Gql\Result\ResultRow([new \App\Gql\Datum\IntegerDatum(1)]), new \App\Gql\Result\ResultRow([new \App\Gql\Datum\IntegerDatum(1)])];
     *     count(\App\Gql\Execution\SetOperation::once($rows)) // => 1
     *
     * @return list<ResultRow> The rows, without repetition
     */
    public static function once(array $rows): array
    {
        $seen = [];
        $kept = [];
        foreach ($rows as $row) {
            $key = DatumIdentity::keyOfAll($row->values);
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $kept[] = $row;
        }

        return $kept;
    }

    /**
     * Returns the rows of one side that the other side does or does not also have.
     *
     * @param list<ResultRow> $left   The rows on the left
     * @param list<ResultRow> $right  The rows on the right
     * @param bool            $shared Whether to keep the rows both sides have, rather than the rest
     *
     * @example The rows both sides have
     *     $row = new \App\Gql\Result\ResultRow([new \App\Gql\Datum\IntegerDatum(1)]);
     *     count(\App\Gql\Execution\SetOperation::sharing([$row], [$row], true)) // => 1
     * @example The rows only the left side has
     *     $row = new \App\Gql\Result\ResultRow([new \App\Gql\Datum\IntegerDatum(1)]);
     *     \App\Gql\Execution\SetOperation::sharing([$row], [$row], false) // => []
     *
     * @return list<ResultRow> The rows kept
     */
    public static function sharing(array $left, array $right, bool $shared): array
    {
        $known = [];
        foreach ($right as $row) {
            $known[DatumIdentity::keyOfAll($row->values)] = true;
        }

        $kept = [];
        foreach ($left as $row) {
            if (isset($known[DatumIdentity::keyOfAll($row->values)]) === $shared) {
                $kept[] = $row;
            }
        }

        return $kept;
    }
}
