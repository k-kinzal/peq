<?php

declare(strict_types=1);

namespace App\Gql\Execution;

use App\Gql\Binding\BindingRow;
use App\Gql\Binding\BindingTable;
use App\Gql\Datum\Datum;
use App\Gql\Datum\DatumOrder;
use App\Gql\Evaluation\ExpressionEvaluation;
use App\Gql\Evaluation\Logic;
use App\Gql\GqlException;
use App\Gql\Syntax\Clause\FilterClause;
use App\Gql\Syntax\Clause\LetClause;
use App\Gql\Syntax\Clause\OrderByClause;
use App\Gql\Syntax\Clause\PageClause;
use App\Gql\Syntax\Clause\SortKey;

/**
 * The clauses that work on rows without touching the graph.
 *
 * Naming, filtering, ordering and paging are one class because they are one kind of
 * thing: each takes the rows it is given and hands back rows, with no reference to
 * what a pattern is or how a graph is walked. That separation is worth keeping — it
 * is the reason a query's shape is readable in the order it is written.
 *
 * @visibility App\Gql
 */
final class RowExecution
{
    /**
     * @param ExpressionEvaluation $evaluation How an expression is worked out for a row
     */
    public function __construct(
        private readonly ExpressionEvaluation $evaluation,
    ) {}

    /**
     * Returns the rows a LET produces: the ones it was given, with more names bound.
     *
     * Every name in one clause is worked out from the row as it arrived, so none of
     * them can see another. That is GQL's rule, and it is why a value built from
     * another needs a clause of its own.
     *
     * @param LetClause    $clause The clause
     * @param BindingTable $table  The rows it is given
     *
     * @example A naming adds a column to every row it is given
     *     $parser = new \App\Gql\Parsing\ExpressionParser(\App\Gql\Parsing\TokenReader::of('1'));
     *     $binding = new \App\Gql\Syntax\Clause\VariableBinding('n', $parser->parse());
     *     $execution = new \App\Gql\Execution\RowExecution(new \App\Gql\Evaluation\ExpressionEvaluation());
     *     $execution->bind(new \App\Gql\Syntax\Clause\LetClause([$binding]), \App\Gql\Binding\BindingTable::unit())->names() // => ['n']
     *
     * @return BindingTable The rows it produces
     *
     * @throws GqlException If a named value cannot be worked out
     */
    public function bind(LetClause $clause, BindingTable $table): BindingTable
    {
        $produced = [];
        foreach ($table->rows as $row) {
            $bound = [];
            foreach ($clause->bindings as $binding) {
                $bound[$binding->name] = $this->evaluation->evaluate($binding->value, $row);
            }
            $produced[] = $row->withAll($bound);
        }

        return new BindingTable($produced);
    }

    /**
     * Returns the rows a FILTER keeps.
     *
     * Only the rows the predicate is true of survive. A row it is false of is
     * dropped, and so is a row it could not be decided for — which is what makes a
     * filter over a property some symbols do not carry narrow rather than widen.
     *
     * @param FilterClause $clause The clause
     * @param BindingTable $table  The rows it is given
     *
     * @example A predicate that cannot be decided keeps nothing
     *     $parser = new \App\Gql\Parsing\ExpressionParser(\App\Gql\Parsing\TokenReader::of('NULL'));
     *     $execution = new \App\Gql\Execution\RowExecution(new \App\Gql\Evaluation\ExpressionEvaluation());
     *     $execution->keep(new \App\Gql\Syntax\Clause\FilterClause($parser->parse()), \App\Gql\Binding\BindingTable::unit())->rows // => []
     *
     * @return BindingTable The rows it keeps
     *
     * @throws GqlException If the predicate cannot be worked out, or is not a predicate
     */
    public function keep(FilterClause $clause, BindingTable $table): BindingTable
    {
        $kept = [];
        foreach ($table->rows as $row) {
            if (Logic::holds($this->evaluation->evaluate($clause->predicate, $row))) {
                $kept[] = $row;
            }
        }

        return new BindingTable($kept);
    }

    /**
     * Returns the rows an ORDER BY produces, in the order it asks for.
     *
     * @param OrderByClause $clause The clause
     * @param BindingTable  $table  The rows it is given
     *
     * @example Ordering nothing produces nothing
     *     $parser = new \App\Gql\Parsing\ExpressionParser(\App\Gql\Parsing\TokenReader::of('1'));
     *     $key = new \App\Gql\Syntax\Clause\SortKey($parser->parse());
     *     $execution = new \App\Gql\Execution\RowExecution(new \App\Gql\Evaluation\ExpressionEvaluation());
     *     $execution->order(new \App\Gql\Syntax\Clause\OrderByClause([$key]), \App\Gql\Binding\BindingTable::nothing())->rows // => []
     *
     * @return BindingTable The rows, ordered
     *
     * @throws GqlException If a sort key cannot be worked out
     */
    public function order(OrderByClause $clause, BindingTable $table): BindingTable
    {
        return new BindingTable($this->sorted($clause->keys, $table->rows));
    }

    /**
     * Returns rows put in the order a list of sort keys asks for.
     *
     * The keys are worked out once per row rather than on every comparison, which
     * matters on a query that sorts by something expensive — a path length, a
     * concatenation of names.
     *
     * @param list<SortKey>    $keys The keys, most significant first
     * @param list<BindingRow> $rows The rows
     *
     * @example Ordering no rows produces no rows
     *     (new \App\Gql\Execution\RowExecution(new \App\Gql\Evaluation\ExpressionEvaluation()))->sorted([], []) // => []
     *
     * @return list<BindingRow> The rows, ordered
     *
     * @throws GqlException If a sort key cannot be worked out
     */
    public function sorted(array $keys, array $rows): array
    {
        $keyed = [];
        foreach ($rows as $index => $row) {
            $values = [];
            foreach ($keys as $key) {
                $values[] = $this->evaluation->evaluate($key->value, $row);
            }
            $keyed[] = ['row' => $row, 'values' => $values, 'index' => $index];
        }

        usort($keyed, static fn (array $left, array $right): int => self::against($keys, $left, $right));

        return array_map(static fn (array $entry): BindingRow => $entry['row'], $keyed);
    }

    /**
     * Compares two rows by their sort keys, most significant first.
     *
     * @param list<SortKey>                                           $keys  The keys, most significant first
     * @param array{row: BindingRow, values: list<Datum>, index: int} $left  The row on the left
     * @param array{row: BindingRow, values: list<Datum>, index: int} $right The row on the right
     *
     * @example Rows that tie on every key keep the order they arrived in
     *     $left = ['row' => \App\Gql\Binding\BindingRow::unit(), 'values' => [], 'index' => 0];
     *     $right = ['row' => \App\Gql\Binding\BindingRow::unit(), 'values' => [], 'index' => 1];
     *     \App\Gql\Execution\RowExecution::against([], $left, $right) // => -1
     *
     * @return int Negative, zero or positive
     */
    public static function against(array $keys, array $left, array $right): int
    {
        foreach ($keys as $place => $key) {
            $order = DatumOrder::sort($left['values'][$place], $right['values'][$place]) * $key->direction->factor();
            if ($order !== 0) {
                return $order;
            }
        }

        return $left['index'] <=> $right['index'];
    }

    /**
     * Returns the stretch of rows a page asks for.
     *
     * @param PageClause   $clause The clause
     * @param BindingTable $table  The rows it is given
     *
     * @example A page that keeps nothing keeps nothing
     *     $execution = new \App\Gql\Execution\RowExecution(new \App\Gql\Evaluation\ExpressionEvaluation());
     *     $execution->page(new \App\Gql\Syntax\Clause\PageClause(0, 0), \App\Gql\Binding\BindingTable::unit())->rows // => []
     *
     * @return BindingTable The stretch
     */
    public function page(PageClause $clause, BindingTable $table): BindingTable
    {
        return new BindingTable(array_slice($table->rows, $clause->offset, $clause->limit));
    }
}
