<?php

declare(strict_types=1);

namespace App\Gql\Execution;

use App\Gql\Binding\BindingRow;
use App\Gql\Binding\BindingTable;
use App\Gql\Datum\Datum;
use App\Gql\Datum\NullDatum;
use App\Gql\Element\ElementGraph;
use App\Gql\Evaluation\ExpressionEvaluation;
use App\Gql\Evaluation\Logic;
use App\Gql\GqlException;
use App\Gql\Matching\PatternMatching;
use App\Gql\Matching\PatternVariables;
use App\Gql\Syntax\Clause\MatchClause;

/**
 * Running a MATCH: finding a shape, and joining it to what is already known.
 *
 * Every row that comes in is matched separately and contributes as many rows as the
 * graph matches it, which is what makes a chain of matches a join: the names a
 * pattern shares with the rows it is given are already bound, so the search starts
 * from them rather than from the whole graph.
 *
 * An optional match keeps the rows that matched nothing, exactly as they were. That
 * is what a left outer join is, and it is the only way to ask "and what, if anything,
 * does each of these reach" without silently dropping the ones that reach nothing —
 * which, in impact analysis, are often the interesting ones.
 *
 * @visibility App\Gql
 */
final readonly class MatchExecution
{
    /**
     * Finding every way a pattern matches.
     */
    private PatternMatching $patterns;

    /**
     * @param ElementGraph         $graph      The graph being queried
     * @param ExpressionEvaluation $evaluation How a predicate is worked out
     */
    public function __construct(
        ElementGraph $graph,
        private ExpressionEvaluation $evaluation,
    ) {
        $this->patterns = new PatternMatching($graph, $evaluation);
    }

    /**
     * Returns the rows a MATCH produces from the rows it is given.
     *
     * @param MatchClause  $clause The clause
     * @param BindingTable $table  The rows it is given
     *
     * @example A pattern over a graph with no symbols produces no rows
     *     $execution = new \App\Gql\Execution\MatchExecution(new \App\Gql\Element\ElementGraph([], [], []), new \App\Gql\Evaluation\ExpressionEvaluation());
     *     $pattern = new \App\Gql\Syntax\Pattern\GraphPattern([new \App\Gql\Syntax\Pattern\PathPattern([new \App\Gql\Syntax\Pattern\NodePattern('p')])]);
     *     $execution->run(new \App\Gql\Syntax\Clause\MatchClause($pattern), \App\Gql\Binding\BindingTable::unit())->rows // => []
     * @example An optional pattern that matches nothing keeps the row it was given
     *     $execution = new \App\Gql\Execution\MatchExecution(new \App\Gql\Element\ElementGraph([], [], []), new \App\Gql\Evaluation\ExpressionEvaluation());
     *     $pattern = new \App\Gql\Syntax\Pattern\GraphPattern([new \App\Gql\Syntax\Pattern\PathPattern([new \App\Gql\Syntax\Pattern\NodePattern('p')])]);
     *     count($execution->run(new \App\Gql\Syntax\Clause\MatchClause($pattern, null, true), \App\Gql\Binding\BindingTable::unit())->rows) // => 1
     *
     * @return BindingTable The rows it produces
     *
     * @throws GqlException If a requirement written in the pattern cannot be worked out
     */
    public function run(MatchClause $clause, BindingTable $table): BindingTable
    {
        $produced = [];
        foreach ($table->rows as $row) {
            $matched = $this->kept($clause, $row);
            if ($matched === [] && $clause->optional) {
                $produced[] = $row->withAll(self::absent($clause, $row));

                continue;
            }
            array_push($produced, ...$matched);
        }

        return new BindingTable($produced);
    }

    /**
     * Returns the names a pattern would have bound, bound to nothing.
     *
     * A row an optional pattern did not match keeps every name the pattern would have
     * bound, bound to the absence of a value. That is what makes `RETURN parent.name`
     * answer with nothing for a class that extends nothing, rather than reporting a
     * name the query plainly did bind.
     *
     * A name the row already carries is left alone. An optional pattern usually
     * continues from something an earlier clause found — `(c)-[:extends]->(parent)` —
     * and forgetting what `c` was because the pattern found no parent would throw away
     * the row the query was asking about.
     *
     * @param MatchClause $clause The clause
     * @param BindingRow  $row    The row the pattern did not match
     *
     * @example A pattern that binds nothing has nothing to leave absent
     *     $pattern = new \App\Gql\Syntax\Pattern\GraphPattern([new \App\Gql\Syntax\Pattern\PathPattern([new \App\Gql\Syntax\Pattern\NodePattern()])]);
     *     \App\Gql\Execution\MatchExecution::absent(new \App\Gql\Syntax\Clause\MatchClause($pattern, null, true), \App\Gql\Binding\BindingRow::unit()) // => []
     * @example A name the row already carries is left as it was
     *     $pattern = new \App\Gql\Syntax\Pattern\GraphPattern([new \App\Gql\Syntax\Pattern\PathPattern([new \App\Gql\Syntax\Pattern\NodePattern('c')])]);
     *     $row = \App\Gql\Binding\BindingRow::unit()->with('c', new \App\Gql\Datum\IntegerDatum(1));
     *     \App\Gql\Execution\MatchExecution::absent(new \App\Gql\Syntax\Clause\MatchClause($pattern, null, true), $row) // => []
     *
     * @return array<string, Datum> The names the pattern would have bound and the row does not carry
     */
    public static function absent(MatchClause $clause, BindingRow $row): array
    {
        $bindings = [];
        foreach (PatternVariables::of($clause->pattern) as $name) {
            if (!$row->has($name)) {
                $bindings[$name] = new NullDatum();
            }
        }

        return $bindings;
    }

    /**
     * Returns the ways a pattern matched one row, after the clause's own narrowing.
     *
     * A `WHERE` written after the pattern narrows the matches rather than the search,
     * which is the difference between it and a `WHERE` written inside the pattern:
     * the one inside stops the search early, and this one sees whole matches.
     *
     * @param MatchClause $clause The clause
     * @param BindingRow  $row    The row being matched from
     *
     * @example A pattern over a graph with no symbols matches nothing
     *     $execution = new \App\Gql\Execution\MatchExecution(new \App\Gql\Element\ElementGraph([], [], []), new \App\Gql\Evaluation\ExpressionEvaluation());
     *     $pattern = new \App\Gql\Syntax\Pattern\GraphPattern([new \App\Gql\Syntax\Pattern\PathPattern([new \App\Gql\Syntax\Pattern\NodePattern('p')])]);
     *     $execution->kept(new \App\Gql\Syntax\Clause\MatchClause($pattern), \App\Gql\Binding\BindingRow::unit()) // => []
     *
     * @return list<BindingRow> The matches the clause keeps
     *
     * @throws GqlException If a requirement written in the pattern cannot be worked out
     */
    public function kept(MatchClause $clause, BindingRow $row): array
    {
        $matched = $this->patterns->match($clause->pattern, $row);
        if ($clause->where === null) {
            return $matched;
        }

        $kept = [];
        foreach ($matched as $candidate) {
            if (Logic::holds($this->evaluation->evaluate($clause->where, $candidate))) {
                $kept[] = $candidate;
            }
        }

        return $kept;
    }
}
