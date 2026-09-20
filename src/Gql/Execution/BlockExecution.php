<?php

declare(strict_types=1);

namespace App\Gql\Execution;

use App\Gql\Binding\BindingTable;
use App\Gql\Element\ElementGraph;
use App\Gql\Evaluation\ExpressionEvaluation;
use App\Gql\GqlException;
use App\Gql\Matching\PatternVariables;
use App\Gql\Result\ResultTable;
use App\Gql\StatusCode;
use App\Gql\Syntax\Clause;
use App\Gql\Syntax\Clause\FilterClause;
use App\Gql\Syntax\Clause\LetClause;
use App\Gql\Syntax\Clause\MatchClause;
use App\Gql\Syntax\Clause\OrderByClause;
use App\Gql\Syntax\Clause\PageClause;
use App\Gql\Syntax\Clause\ReturnClause;
use App\Gql\Syntax\QueryBlock;

/**
 * Running one straight run of clauses, from one row in to a result out.
 *
 * The run starts from a single row binding nothing, which is what makes the first
 * clause run exactly once, and each clause afterwards is handed what the last one
 * produced. Nothing here knows which clause came before it, which is the property
 * that makes a long query readable in the order it is written.
 *
 * Every run ends in a `RETURN`, because GQL's linear query does: the reader who writes
 * `MATCH (p:Method WHERE p.deprecated)` and stops there is asking a question the
 * language has no way to answer, and `RETURN *` is how the language writes it.
 *
 * @visibility App\Gql
 */
final class BlockExecution
{
    /**
     * Running the clause that looks for a shape in the graph.
     */
    private readonly MatchExecution $matches;

    /**
     * Running the clauses that work on rows without touching the graph.
     */
    private readonly RowExecution $rows;

    /**
     * Running the clause that decides what the reader is shown.
     */
    private readonly ReturnExecution $projection;

    /**
     * @param ElementGraph $graph    The graph being queried
     * @param int          $hopLimit How far a repetition goes when no upper bound was written
     */
    public function __construct(
        ElementGraph $graph,
        int $hopLimit = 10,
    ) {
        $evaluation = new ExpressionEvaluation();
        $this->matches = new MatchExecution($graph, $evaluation, $hopLimit);
        $this->rows = new RowExecution($evaluation);
        $this->projection = new ReturnExecution($this->rows);
    }

    /**
     * Runs a straight run of clauses and returns what it answered.
     *
     * @param QueryBlock $block The clauses
     *
     * @example A run that says what to show is shown that
     *     $block = \App\Gql\Parsing\Parser::read('RETURN 1 AS n')->blocks[0];
     *     $execution = new \App\Gql\Execution\BlockExecution(new \App\Gql\Element\ElementGraph([], [], []));
     *     $execution->run($block)->rows[0]->value(0)->toText() // => '1'
     * @example A run that shows everything it bound is shown all of it
     *     $block = \App\Gql\Parsing\Parser::read('LET n = 1 RETURN *')->blocks[0];
     *     $execution = new \App\Gql\Execution\BlockExecution(new \App\Gql\Element\ElementGraph([], [], []));
     *     $execution->run($block)->headings() // => ['n']
     *
     * @return ResultTable What it answered
     *
     * @throws GqlException If a clause cannot be run
     */
    public function run(QueryBlock $block): ResultTable
    {
        $table = BindingTable::unit();
        $headings = null;
        $groupLists = [];
        foreach ($block->clauses as $clause) {
            if ($clause instanceof MatchClause) {
                array_push($groupLists, ...PatternVariables::groupLists($clause->pattern));
            }
            if ($clause instanceof ReturnClause) {
                $headings = $this->projection->headings($clause, $table);
                $table = $this->projection->run($clause, $table, $groupLists);

                continue;
            }
            $table = $this->apply($clause, $table);
        }

        return ResultTable::of($headings ?? $table->names(), $table);
    }

    /**
     * Returns the rows one clause produces from the rows it is given.
     *
     * @param Clause       $clause The clause
     * @param BindingTable $table  The rows it is given
     *
     * @example A clause that keeps a stretch of the rows keeps that stretch
     *     $execution = new \App\Gql\Execution\BlockExecution(new \App\Gql\Element\ElementGraph([], [], []));
     *     $execution->apply(new \App\Gql\Syntax\Clause\PageClause(0, 0), \App\Gql\Binding\BindingTable::unit())->rows // => []
     *
     * @return BindingTable The rows it produces
     *
     * @throws GqlException If the clause is not one peq knows how to run
     */
    public function apply(Clause $clause, BindingTable $table): BindingTable
    {
        if ($clause instanceof MatchClause) {
            return $this->matches->run($clause, $table);
        }
        if ($clause instanceof LetClause) {
            return $this->rows->bind($clause, $table);
        }
        if ($clause instanceof FilterClause) {
            return $this->rows->keep($clause, $table);
        }
        if ($clause instanceof OrderByClause) {
            return $this->rows->order($clause, $table);
        }
        if ($clause instanceof PageClause) {
            return $this->rows->page($clause, $table);
        }

        throw GqlException::because(StatusCode::UnknownFeature, 'this clause is not one peq knows how to run');
    }
}
