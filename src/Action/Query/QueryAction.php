<?php

declare(strict_types=1);

namespace App\Action\Query;

use App\Action\AnalyzerChoice;
use App\Gql\Element\GraphProjection;
use App\Gql\Execution\QueryExecution;
use App\Gql\GqlException;

/**
 * Runs a query: builds the dependency graph and asks it a question.
 *
 * The graph is built the same way an inspection builds it, from the same settings,
 * which is the point: a query is a second way of asking about the same analysis
 * rather than a second analysis. What differs is only what is done with the graph
 * afterwards — an inspection walks it from a symbol, a query matches patterns against
 * all of it.
 */
final class QueryAction
{
    /**
     * Builds the graph for the configured path and runs the query against it.
     *
     * @param QueryActionInput $input The configuration and the query to run
     *
     * @return QueryActionOutput What the query answered
     *
     * @throws GqlException If the query cannot be read or cannot be run
     */
    public function execute(QueryActionInput $input): QueryActionOutput
    {
        $config = $input->config;
        $elements = GraphProjection::of(AnalyzerChoice::forConfig($config)->analyze($config->basePath));

        return new QueryActionOutput(
            (new QueryExecution($elements, $config->hops))->query($input->query),
            $elements,
        );
    }
}
