<?php

declare(strict_types=1);

namespace App\Gql\Execution;

use App\Gql\Binding\BindingRow;
use App\Gql\Binding\BindingTable;
use App\Gql\Datum\DatumIdentity;
use App\Gql\Evaluation\AggregateDetection;
use App\Gql\Evaluation\ExpressionEvaluation;
use App\Gql\GqlException;
use App\Gql\Syntax\Clause\Projection;
use App\Gql\Syntax\Clause\ReturnClause;
use App\Gql\Syntax\Expression;
use App\Gql\Syntax\Expression\VariableExpression;

/**
 * Running a RETURN: deciding what the reader is shown.
 *
 * The one real decision here is whether the projection summarises its rows or reports
 * them, and it is made from the shape of the expressions rather than from the data:
 * a projection holding a summary anywhere inside it produces one row per group, and
 * one holding none produces one row per row. Grouping without a `GROUP BY` makes a
 * single group of everything, which is why `RETURN count(*)` answers once.
 *
 * Projected columns are added to the row they came from rather than replacing it.
 * That is what lets an ordering written after the projection sort by something the
 * projection did not show — `RETURN a, b ORDER BY r.creationDate` — which GQL allows
 * and a reader reaches for more often than it sounds.
 *
 * @visibility App\Gql
 */
final readonly class ReturnExecution
{
    /**
     * @param RowExecution $rows How rows are ordered and paged
     */
    public function __construct(
        private RowExecution $rows,
    ) {}

    /**
     * Returns what the columns of the result are called.
     *
     * A projection that asks for everything is headed by the names that are in scope
     * where it is written, which is decided when the query runs rather than when it
     * is read.
     *
     * @param ReturnClause $clause The clause
     * @param BindingTable $table  The rows it is given
     *
     * @example A projection that asks for everything shows what is bound
     *     $row = \App\Gql\Binding\BindingRow::unit()->with('p', new \App\Gql\Datum\NullDatum());
     *     $execution = new \App\Gql\Execution\ReturnExecution(new \App\Gql\Execution\RowExecution(new \App\Gql\Evaluation\ExpressionEvaluation()));
     *     $execution->headings(new \App\Gql\Syntax\Clause\ReturnClause(), new \App\Gql\Binding\BindingTable([$row])) // => ['p']
     *
     * @return list<string> The headings, in the order they are shown
     */
    public function headings(ReturnClause $clause, BindingTable $table): array
    {
        if ($clause->everything()) {
            return $table->names();
        }

        return array_map(static fn (Projection $column): string => $column->heading(), $clause->columns);
    }

    /**
     * Returns the rows a RETURN produces.
     *
     * @param ReturnClause $clause     The clause
     * @param BindingTable $table      The rows it is given
     * @param list<string> $groupLists The names a repeating pattern bound to every relation it crossed
     *
     * @example A projection that summarises answers once, even over no rows at all
     *     $counted = new \App\Gql\Syntax\Expression\CallExpression('count', [], false, true);
     *     $clause = new \App\Gql\Syntax\Clause\ReturnClause([new \App\Gql\Syntax\Clause\Projection($counted, 'n', 'count(*)')]);
     *     $execution = new \App\Gql\Execution\ReturnExecution(new \App\Gql\Execution\RowExecution(new \App\Gql\Evaluation\ExpressionEvaluation()));
     *     $execution->run($clause, \App\Gql\Binding\BindingTable::nothing())->rows[0]->value('n')->toText() // => '0'
     *
     * @return BindingTable The rows it produces
     *
     * @throws GqlException If a column cannot be worked out
     */
    public function run(ReturnClause $clause, BindingTable $table, array $groupLists = []): BindingTable
    {
        $produced = $this->summarises($clause, $groupLists)
            ? $this->summarised($clause, $table)
            : $this->reported($clause, $table);

        if ($clause->distinct) {
            $produced = self::once($this->headings($clause, $table), $produced);
        }
        if ($clause->orderBy !== []) {
            $produced = $this->rows->sorted($clause->orderBy, $produced);
        }
        if ($clause->page !== null) {
            $produced = array_slice($produced, $clause->page->offset, $clause->page->limit);
        }

        return new BindingTable($produced);
    }

    /**
     * Reports whether a projection summarises its rows rather than reporting them.
     *
     * A summary written over a group list is not one of these. It summarises along the
     * row it is written for rather than down the table, which is GQL's rule that
     * horizontal aggregation takes precedence, so a projection holding nothing else
     * keeps one row per match.
     *
     * @param ReturnClause $clause     The clause
     * @param list<string> $groupLists The names a repeating pattern bound to every relation it crossed
     *
     * @example A projection that groups summarises
     *     $key = new \App\Gql\Syntax\Expression\VariableExpression('kind');
     *     $clause = new \App\Gql\Syntax\Clause\ReturnClause([], false, [$key]);
     *     $execution = new \App\Gql\Execution\ReturnExecution(new \App\Gql\Execution\RowExecution(new \App\Gql\Evaluation\ExpressionEvaluation()));
     *     $execution->summarises($clause) // => true
     * @example A projection that shows properties does not
     *     $execution = new \App\Gql\Execution\ReturnExecution(new \App\Gql\Execution\RowExecution(new \App\Gql\Evaluation\ExpressionEvaluation()));
     *     $execution->summarises(new \App\Gql\Syntax\Clause\ReturnClause()) // => false
     *
     * @return bool True when it produces one row per group
     */
    public function summarises(ReturnClause $clause, array $groupLists = []): bool
    {
        if ($clause->groupBy !== []) {
            return true;
        }
        foreach ($clause->columns as $column) {
            if (AggregateDetection::within($column->value, $groupLists)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Returns one row per row, with the projected columns added to it.
     *
     * @param ReturnClause $clause The clause
     * @param BindingTable $table  The rows it is given
     *
     * @example A projection that asks for everything leaves its rows alone
     *     $row = \App\Gql\Binding\BindingRow::unit()->with('p', new \App\Gql\Datum\IntegerDatum(1));
     *     $execution = new \App\Gql\Execution\ReturnExecution(new \App\Gql\Execution\RowExecution(new \App\Gql\Evaluation\ExpressionEvaluation()));
     *     $execution->reported(new \App\Gql\Syntax\Clause\ReturnClause(), new \App\Gql\Binding\BindingTable([$row]))[0]->value('p')->toText() // => '1'
     *
     * @return list<BindingRow> The rows
     *
     * @throws GqlException If a column cannot be worked out
     */
    public function reported(ReturnClause $clause, BindingTable $table): array
    {
        $evaluation = new ExpressionEvaluation();
        $produced = [];
        foreach ($table->rows as $row) {
            $values = [];
            foreach ($clause->columns as $column) {
                $values[$column->heading()] = $evaluation->evaluate($column->value, $row);
            }
            $produced[] = $row->withAll($values);
        }

        return $produced;
    }

    /**
     * Returns one row per group, with the summaries worked out over each group.
     *
     * @param ReturnClause $clause The clause
     * @param BindingTable $table  The rows it is given
     *
     * @example Summarising no rows at all still answers once
     *     $counted = new \App\Gql\Syntax\Expression\CallExpression('count', [], false, true);
     *     $clause = new \App\Gql\Syntax\Clause\ReturnClause([new \App\Gql\Syntax\Clause\Projection($counted, 'n', 'count(*)')]);
     *     $execution = new \App\Gql\Execution\ReturnExecution(new \App\Gql\Execution\RowExecution(new \App\Gql\Evaluation\ExpressionEvaluation()));
     *     count($execution->summarised($clause, \App\Gql\Binding\BindingTable::nothing())) // => 1
     *
     * @return list<BindingRow> The rows
     *
     * @throws GqlException If a column cannot be worked out
     */
    public function summarised(ReturnClause $clause, BindingTable $table): array
    {
        $produced = [];
        foreach (self::groups(self::groupKeys($clause), $table->rows) as $group) {
            $representative = $group[0] ?? BindingRow::unit();
            $over = ExpressionEvaluation::over($group);
            $values = [];
            foreach ($clause->columns as $column) {
                $values[$column->heading()] = $over->evaluate($column->value, $representative);
            }
            $produced[] = $representative->withAll($values);
        }

        return $produced;
    }

    /**
     * Returns what the rows are grouped by, with a named column read as what names it.
     *
     * GQL groups by names bound before the projection, which usually means names a
     * `LET` gave. Writing `GROUP BY kind` where `kind` is a column the same projection
     * names is the obvious thing to try, and refusing it would send a reader back to
     * add a `LET` that says nothing new — so a grouping key that names a column of
     * this projection is read as whatever that column shows.
     *
     * @param ReturnClause $clause The clause
     *
     * @example A grouping key that names a column of the projection is that column
     *     $shown = new \App\Gql\Syntax\Expression\PropertyExpression(new \App\Gql\Syntax\Expression\VariableExpression('p'), 'kind');
     *     $clause = new \App\Gql\Syntax\Clause\ReturnClause(
     *         [new \App\Gql\Syntax\Clause\Projection($shown, 'kind', 'p.kind')],
     *         false,
     *         [new \App\Gql\Syntax\Expression\VariableExpression('kind')],
     *     );
     *     \App\Gql\Execution\ReturnExecution::groupKeys($clause)[0] === $shown // => true
     *
     * @return list<Expression> What the rows are grouped by
     */
    public static function groupKeys(ReturnClause $clause): array
    {
        $named = [];
        foreach ($clause->columns as $column) {
            if ($column->alias !== null) {
                $named[$column->alias] = $column->value;
            }
        }

        $keys = [];
        foreach ($clause->groupBy as $key) {
            $keys[] = $key instanceof VariableExpression ? $named[$key->name] ?? $key : $key;
        }

        return $keys;
    }

    /**
     * Returns the rows gathered into the groups they are summarised over.
     *
     * A projection with no grouping key makes one group of everything, including when
     * there is nothing — which is what makes a count over an empty result zero rather
     * than no answer at all.
     *
     * @param list<Expression> $keys The grouping keys
     * @param list<BindingRow> $rows The rows
     *
     * @example Grouping by nothing makes one group
     *     count(\App\Gql\Execution\ReturnExecution::groups([], [])) // => 1
     *
     * @return list<list<BindingRow>> The groups
     *
     * @throws GqlException If a grouping key cannot be worked out
     */
    public static function groups(array $keys, array $rows): array
    {
        if ($keys === []) {
            return [$rows];
        }

        $evaluation = new ExpressionEvaluation();
        $groups = [];
        foreach ($rows as $row) {
            $values = [];
            foreach ($keys as $key) {
                $values[] = $evaluation->evaluate($key, $row);
            }
            $groups[DatumIdentity::keyOfAll($values)][] = $row;
        }

        return array_values($groups);
    }

    /**
     * Returns the rows with those that repeat shown once.
     *
     * Two rows repeat when the columns being shown are the same, whatever else the
     * rows carry: a projection of one property of two different symbols has two rows
     * and one distinct one.
     *
     * @param list<string>     $headings The columns being shown
     * @param list<BindingRow> $rows     The rows
     *
     * @example Rows that agree on what is shown are shown once
     *     $left = \App\Gql\Binding\BindingRow::unit()->with('n', new \App\Gql\Datum\IntegerDatum(1));
     *     $right = \App\Gql\Binding\BindingRow::unit()->with('n', new \App\Gql\Datum\IntegerDatum(1));
     *     count(\App\Gql\Execution\ReturnExecution::once(['n'], [$left, $right])) // => 1
     *
     * @return list<BindingRow> The rows, without repetition
     */
    public static function once(array $headings, array $rows): array
    {
        $seen = [];
        $kept = [];
        foreach ($rows as $row) {
            $values = [];
            foreach ($headings as $heading) {
                $values[] = $row->value($heading);
            }
            $key = DatumIdentity::keyOfAll($values);
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $kept[] = $row;
        }

        return $kept;
    }
}
