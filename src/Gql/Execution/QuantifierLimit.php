<?php

declare(strict_types=1);

namespace App\Gql\Execution;

use App\Gql\GqlException;
use App\Gql\StatusCode;
use App\Gql\Syntax\Clause\MatchClause;
use App\Gql\Syntax\Pattern\EdgePattern;
use App\Gql\Syntax\Pattern\GroupPattern;
use App\Gql\Syntax\Pattern\PathTerm;
use App\Gql\Syntax\Query;

/**
 * The largest upper bound a quantifier may be written with.
 *
 * ISO/IEC 39075 leaves this to the implementation — it is IL018, "the maximum value of
 * the upper bound of a general quantifier" — and peq makes it `--hops`. What it limits
 * is what a query writes: `{1,20}` asks for up to twenty repetitions, and a walk of
 * twenty steps through a graph with cycles can be more work than anyone meant to ask
 * for. A quantifier with no upper bound is not affected, because GQL allows one only
 * under a restrictor, which is what keeps its matches finite.
 *
 * The limit is checked before anything runs, so a query that exceeds it is refused
 * whatever the graph holds, rather than only when a match happens to reach that far.
 *
 * @visibility App\Gql
 */
final class QuantifierLimit
{
    /**
     * Refuses a query that writes an upper bound above the limit.
     *
     * @param Query $query The query
     * @param int   $limit The largest upper bound a quantifier may be written with
     *
     * @example A query within the limit is left alone
     *     \App\Gql\Execution\QuantifierLimit::check(\App\Gql\Parsing\Parser::read('MATCH (a)-[e]->{1,3}(b) RETURN a'), 3) // => null
     * @example One beyond it is refused, and told how to raise it
     *     \App\Gql\Execution\QuantifierLimit::check(\App\Gql\Parsing\Parser::read('MATCH (a)-[e]->{1,4}(b) RETURN a'), 3) // throws \App\Gql\GqlException: --hops
     *
     * @throws GqlException If a quantifier is written with an upper bound above the limit
     */
    public static function check(Query $query, int $limit): void
    {
        foreach ($query->blocks as $block) {
            foreach ($block->clauses as $clause) {
                if (!$clause instanceof MatchClause) {
                    continue;
                }
                foreach ($clause->pattern->paths as $path) {
                    self::checkTerms($path->terms, $limit);
                }
            }
        }
    }

    /**
     * Refuses the pieces of a path when one of them writes an upper bound above the limit.
     *
     * @param list<PathTerm> $terms The pieces
     * @param int            $limit The largest upper bound a quantifier may be written with
     *
     * @example A path that repeats nothing is within any limit
     *     \App\Gql\Execution\QuantifierLimit::checkTerms([new \App\Gql\Syntax\Pattern\NodePattern()], 0) // => null
     *
     * @throws GqlException If one of them writes an upper bound above the limit
     */
    public static function checkTerms(array $terms, int $limit): void
    {
        foreach ($terms as $term) {
            $most = match (true) {
                $term instanceof EdgePattern => $term->quantifier?->most,
                $term instanceof GroupPattern => $term->quantifier?->most,
                default => null,
            };
            if ($most !== null && $most > $limit) {
                throw GqlException::because(
                    StatusCode::SyntaxError,
                    sprintf('a quantifier may be written with an upper bound of at most %d here, and one is written with %d: raise --hops to allow more', $limit, $most),
                );
            }
            if ($term instanceof GroupPattern) {
                self::checkTerms($term->terms, $limit);
            }
        }
    }
}
