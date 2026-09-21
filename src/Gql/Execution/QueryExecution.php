<?php

declare(strict_types=1);

namespace App\Gql\Execution;

use App\Analyzer\Graph\Graph;
use App\Gql\Element\ElementGraph;
use App\Gql\Element\GraphProjection;
use App\Gql\GqlException;
use App\Gql\Parsing\Parser;
use App\Gql\Result\ResultTable;
use App\Gql\Syntax\Query;

/**
 * Running a whole query against an analysed dependency graph.
 *
 * This is the boundary between peq and its query language: everything on the far side
 * of it is GQL, and everything on this side is a graph of PHP symbols. A caller hands
 * over a graph and a query and is handed a result and a status; nothing about parsing,
 * matching or three-valued logic reaches past here.
 *
 * Each run of clauses is executed independently and the results are combined
 * afterwards, because nothing flows across a set operator — a name bound on the left
 * of a `UNION` means nothing on its right.
 */
final class QueryExecution
{
    /**
     * @param ElementGraph $graph    The graph being queried
     * @param int          $hopLimit The largest upper bound a quantifier may be written with, which is IL018
     */
    public function __construct(
        private readonly ElementGraph $graph,
        private readonly int $hopLimit = 10,
    ) {}

    /**
     * Prepares to query an analysed dependency graph.
     *
     * @param Graph $graph    The graph analysis produced
     * @param int   $hopLimit The largest upper bound a quantifier may be written with, which is IL018
     *
     * @example A graph analysis produced can be queried as it stands
     *     \App\Gql\Execution\QueryExecution::against(new \App\Analyzer\Graph\Graph())->query('RETURN 1 AS n')->rows[0]->value(0)->toText() // => '1'
     *
     * @return self The execution
     */
    public static function against(Graph $graph, int $hopLimit = 10): self
    {
        return new self(GraphProjection::of($graph), $hopLimit);
    }

    /**
     * Reads a query and runs it.
     *
     * @param string $source The query, as it was written
     *
     * @example A query that finds nothing says so rather than failing
     *     $found = \App\Gql\Execution\QueryExecution::against(new \App\Analyzer\Graph\Graph())->query('MATCH (p:Method) RETURN p');
     *     $found->status() // => \App\Gql\StatusCode::NoData
     *
     * @return ResultTable What it answered
     *
     * @throws GqlException If the query cannot be read or cannot be run
     */
    public function query(string $source): ResultTable
    {
        return $this->run(Parser::read($source));
    }

    /**
     * Runs a query that has already been read.
     *
     * @param Query $query The query
     *
     * @example A query that combines two runs answers with both
     *     $parsed = \App\Gql\Parsing\Parser::read('RETURN 1 AS n UNION ALL RETURN 2 AS n');
     *     count(\App\Gql\Execution\QueryExecution::against(new \App\Analyzer\Graph\Graph())->run($parsed)->rows) // => 2
     *
     * @return ResultTable What it answered
     *
     * @throws GqlException If the query cannot be run
     */
    public function run(Query $query): ResultTable
    {
        QuantifierLimit::check($query, $this->hopLimit);
        $blocks = new BlockExecution($this->graph);
        $answered = $blocks->run($query->blocks[0]);

        foreach ($query->operators as $place => $operator) {
            $answered = SetOperation::combine($operator, $answered, $blocks->run($query->blocks[$place + 1]));
        }

        return $answered;
    }
}
