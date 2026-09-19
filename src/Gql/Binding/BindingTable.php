<?php

declare(strict_types=1);

namespace App\Gql\Binding;

/**
 * The rows a clause was given, or the rows it produced.
 *
 * GQL's own description of a query is that every clause takes a table and returns
 * one, and that the query starts from a table of one empty row. Saying it that way in
 * the code as well is what makes each clause independently understandable: none of
 * them knows what came before it, and none of them can tell whether the rows it was
 * given came from a pattern, a naming or a filter.
 *
 * @visibility App\Gql
 */
final class BindingTable
{
    /**
     * @param list<BindingRow> $rows The rows, in order
     */
    public function __construct(
        public readonly array $rows = [],
    ) {}

    /**
     * Returns the table a query starts from: one row, binding nothing.
     *
     * @example A query starts with one row, so its first clause runs once
     *     count(\App\Gql\Binding\BindingTable::unit()->rows) // => 1
     *
     * @return self The table a query starts from
     */
    public static function unit(): self
    {
        return new self([BindingRow::unit()]);
    }

    /**
     * Returns a table of no rows.
     *
     * A clause that drops everything produces this, and so does a pattern that
     * matches nothing. It is not the same as the table a query starts from: a query
     * over this produces nothing, which is the honest answer to a question with no
     * answers.
     *
     * @example Finding nothing is a table, not the absence of one
     *     \App\Gql\Binding\BindingTable::nothing()->rows // => []
     *
     * @return self The empty table
     */
    public static function nothing(): self
    {
        return new self();
    }

    /**
     * Returns the names bound in the table, in the order they were first bound.
     *
     * Rows of one table can bind different names, because an optional match binds
     * nothing on the rows it did not match. The columns of the table are therefore
     * the union of what its rows bind rather than what any one row does.
     *
     * @example The columns are everything any row binds
     *     $left = \App\Gql\Binding\BindingRow::unit()->with('p', new \App\Gql\Datum\NullDatum());
     *     $right = \App\Gql\Binding\BindingRow::unit()->with('q', new \App\Gql\Datum\NullDatum());
     *     (new \App\Gql\Binding\BindingTable([$left, $right]))->names() // => ['p', 'q']
     *
     * @return list<string> The names
     */
    public function names(): array
    {
        $names = [];
        foreach ($this->rows as $row) {
            foreach ($row->names() as $name) {
                $names[$name] = true;
            }
        }

        return array_keys($names);
    }
}
