<?php

declare(strict_types=1);

namespace App\Gql\Matching;

use App\Gql\Binding\BindingRow;
use App\Gql\Element\ElementGraph;
use App\Gql\Evaluation\ExpressionEvaluation;
use App\Gql\GqlException;
use App\Gql\Syntax\Pattern\GraphPattern;

/**
 * Finding every way a whole MATCH matches the graph.
 *
 * Paths written side by side are matched one after another, each starting from what
 * the ones before it bound. That is all the joining there is: a name written in two
 * paths is bound by the first and constrains the second, so `(p)-[:declaresMethod]->
 * (m), (p)-[:extends]->(q)` finds a class with both a method and a parent without any
 * join condition being written or worked out.
 *
 * Matching each path against the rows the previous one produced also means the order
 * paths are written in decides how much work the search does. A selective path
 * written first narrows everything after it, which is worth knowing when a pattern
 * turns out to be slow.
 *
 * @visibility App\Gql
 */
final class PatternMatching
{
    /**
     * Finding every way one path matches.
     */
    private readonly PathMatching $paths;

    /**
     * @param ElementGraph         $graph      The graph being matched against
     * @param ExpressionEvaluation $evaluation How a predicate written in a pattern is worked out
     * @param int                  $hopLimit   How far a repetition goes when no upper bound was written
     */
    public function __construct(
        ElementGraph $graph,
        ExpressionEvaluation $evaluation,
        int $hopLimit = 10,
    ) {
        $this->paths = new PathMatching($graph, $evaluation, $hopLimit);
    }

    /**
     * Returns every way a pattern matches, as rows of what it bound.
     *
     * @param GraphPattern $pattern The pattern
     * @param BindingRow   $row     What is already bound when the pattern is reached
     *
     * @example A pattern over a graph with no symbols matches nothing
     *     $matching = new \App\Gql\Matching\PatternMatching(new \App\Gql\Element\ElementGraph([], [], []), new \App\Gql\Evaluation\ExpressionEvaluation());
     *     $pattern = new \App\Gql\Syntax\Pattern\GraphPattern([new \App\Gql\Syntax\Pattern\PathPattern([new \App\Gql\Syntax\Pattern\NodePattern('p')])]);
     *     $matching->match($pattern, \App\Gql\Binding\BindingRow::unit()) // => []
     *
     * @return list<BindingRow> One row per way the pattern matched
     *
     * @throws GqlException If a requirement written in the pattern cannot be worked out
     */
    public function match(GraphPattern $pattern, BindingRow $row): array
    {
        $rows = [$row];
        foreach ($pattern->paths as $path) {
            $next = [];
            foreach ($rows as $standing) {
                array_push($next, ...$this->paths->match($path, $standing));
            }
            $rows = $next;
            if ($rows === []) {
                return [];
            }
        }

        return $rows;
    }
}
