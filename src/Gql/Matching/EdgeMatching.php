<?php

declare(strict_types=1);

namespace App\Gql\Matching;

use App\Gql\Datum\Datum;
use App\Gql\Datum\EdgeDatum;
use App\Gql\Datum\ListDatum;
use App\Gql\Element\ElementGraph;
use App\Gql\Evaluation\ExpressionEvaluation;
use App\Gql\GqlException;
use App\Gql\Syntax\Pattern\EdgePattern;
use App\Gql\Syntax\Pattern\PathMode;

/**
 * Crossing a relation, once or as many times as a pattern asks.
 *
 * A pattern written without a repetition crosses one relation and binds its name to
 * it. Written with one, it crosses a chain and binds the same name to the list of
 * every relation in the chain — which is what makes `size(e)` the distance between
 * two symbols, and what a question like "how far does this controller reach" comes
 * down to.
 *
 * Inside the pattern the name still means one relation at a time, so a predicate
 * written on a repeating edge narrows the chain link by link rather than being handed
 * a list it would have to unpick. That is GQL's rule, and it is the difference
 * between a cheap pattern and an expensive filter after the fact.
 *
 * @visibility App\Gql\Matching
 */
final class EdgeMatching
{
    /**
     * @param ElementGraph         $graph      The graph being matched against
     * @param ExpressionEvaluation $evaluation How a predicate written in a pattern is worked out
     * @param int                  $hopLimit   How far a repetition goes when no upper bound was written
     */
    public function __construct(
        private readonly ElementGraph $graph,
        private readonly ExpressionEvaluation $evaluation,
        private readonly int $hopLimit,
    ) {}

    /**
     * Returns every way a pattern can cross a relation from where it stands.
     *
     * A repetition allowed to happen no times at all matches without moving, which is
     * GQL's rule for a lower bound of zero: the two ends of the pattern turn out to be
     * the same symbol.
     *
     * @param EdgePattern $pattern What the pattern requires of the relation
     * @param MatchState  $state   How far the attempt has got
     * @param PathMode    $mode    What the path may visit more than once
     *
     * @example A pattern standing nowhere can cross nothing
     *     $matching = new \App\Gql\Matching\EdgeMatching(new \App\Gql\Element\ElementGraph([], [], []), new \App\Gql\Evaluation\ExpressionEvaluation(), 10);
     *     $pattern = new \App\Gql\Syntax\Pattern\EdgePattern(\App\Gql\Syntax\Pattern\EdgeDirection::Along);
     *     $matching->matches($pattern, \App\Gql\Matching\MatchState::before(\App\Gql\Binding\BindingRow::unit()), \App\Gql\Syntax\Pattern\PathMode::Trail) // => []
     *
     * @return list<MatchState> The attempts that crossed it
     *
     * @throws GqlException If a requirement written in the pattern cannot be worked out
     */
    public function matches(EdgePattern $pattern, MatchState $state, PathMode $mode): array
    {
        if ($state->current === null) {
            return [];
        }
        $quantifier = $pattern->quantifier;
        if ($quantifier === null) {
            return $this->crossOnce($pattern, $state, $mode, false);
        }

        $start = $pattern->variable === null ? $state : $state->bind($pattern->variable, new ListDatum([]));
        $reached = $quantifier->allows(0) ? [$start] : [];
        $frontier = [$start];
        $ceiling = $quantifier->ceiling($this->hopLimit);

        for ($times = 1; $times <= $ceiling && $frontier !== []; ++$times) {
            $next = [];
            foreach ($frontier as $standing) {
                array_push($next, ...$this->crossOnce($pattern, $standing, $mode, true));
            }
            $frontier = $next;
            if ($quantifier->allows($times)) {
                array_push($reached, ...$frontier);
            }
        }

        return $reached;
    }

    /**
     * Returns every way a pattern can cross one relation from where it stands.
     *
     * @param EdgePattern $pattern   What the pattern requires of the relation
     * @param MatchState  $state     How far the attempt has got
     * @param PathMode    $mode      What the path may visit more than once
     * @param bool        $repeating Whether the name binds to the chain rather than to this relation
     *
     * @example A pattern standing nowhere can cross nothing
     *     $matching = new \App\Gql\Matching\EdgeMatching(new \App\Gql\Element\ElementGraph([], [], []), new \App\Gql\Evaluation\ExpressionEvaluation(), 10);
     *     $pattern = new \App\Gql\Syntax\Pattern\EdgePattern(\App\Gql\Syntax\Pattern\EdgeDirection::Along);
     *     $matching->crossOnce($pattern, \App\Gql\Matching\MatchState::before(\App\Gql\Binding\BindingRow::unit()), \App\Gql\Syntax\Pattern\PathMode::Trail, false) // => []
     *
     * @return list<MatchState> The attempts that crossed it
     *
     * @throws GqlException If a requirement written in the pattern cannot be worked out
     */
    public function crossOnce(EdgePattern $pattern, MatchState $state, PathMode $mode, bool $repeating): array
    {
        $standing = $state->current;
        if ($standing === null) {
            return [];
        }

        $reached = [];
        foreach (EdgeTraversal::from($this->graph, $standing->id, $pattern->direction) as $crossing) {
            $arrived = $this->arrival($pattern, $state, $mode, $crossing, $repeating);
            if ($arrived !== null) {
                $reached[] = $arrived;
            }
        }

        return $reached;
    }

    /**
     * Returns the attempt having crossed one relation, or nothing when it may not.
     *
     * @param EdgePattern   $pattern   What the pattern requires of the relation
     * @param MatchState    $state     How far the attempt has got
     * @param PathMode      $mode      What the path may visit more than once
     * @param EdgeTraversal $crossing  The relation and where it leads
     * @param bool          $repeating Whether the name binds to the chain rather than to this relation
     *
     * @example A relation leading nowhere the graph knows about is not crossed
     *     $edge = new \App\Gql\Datum\EdgeDatum('e', [], [], 'a', 'b');
     *     $matching = new \App\Gql\Matching\EdgeMatching(new \App\Gql\Element\ElementGraph([], [], []), new \App\Gql\Evaluation\ExpressionEvaluation(), 10);
     *     $state = \App\Gql\Matching\MatchState::before(\App\Gql\Binding\BindingRow::unit())->startingAt(new \App\Gql\Datum\NodeDatum('a'));
     *     $pattern = new \App\Gql\Syntax\Pattern\EdgePattern(\App\Gql\Syntax\Pattern\EdgeDirection::Along);
     *     $matching->arrival($pattern, $state, \App\Gql\Syntax\Pattern\PathMode::Trail, new \App\Gql\Matching\EdgeTraversal($edge, 'b'), false) // => null
     *
     * @return null|MatchState The attempt, or null when this relation is not one the pattern crosses
     *
     * @throws GqlException If a requirement written in the pattern cannot be worked out
     */
    public function arrival(EdgePattern $pattern, MatchState $state, PathMode $mode, EdgeTraversal $crossing, bool $repeating): ?MatchState
    {
        $edge = $crossing->edge;
        $other = $this->graph->node($crossing->other);
        if ($other === null
            || !PathModeRule::allowsEdge($mode, $state, $edge)
            || !PathModeRule::allowsNode($mode, $state, $other)
            || !LabelMatching::satisfies($pattern->labels, $edge->labels)
            || (!$repeating && !ElementMatching::agrees($pattern->variable, $edge, $state->row))
            || !ElementMatching::satisfies($edge, $pattern->variable, $pattern->filter, $state->row, $this->evaluation)
        ) {
            return null;
        }

        $arrived = $state->across($edge, $other);
        if ($pattern->variable === null) {
            return $arrived;
        }

        return $arrived->bind(
            $pattern->variable,
            $repeating ? self::extended($state->row->value($pattern->variable), $edge) : $edge,
        );
    }

    /**
     * Returns a chain of relations with one more relation on the end of it.
     *
     * @param Datum     $crossed The relations crossed so far
     * @param EdgeDatum $edge    The one just crossed
     *
     * @example A chain grows by one relation at a time
     *     $chain = new \App\Gql\Datum\ListDatum([new \App\Gql\Datum\EdgeDatum('e', [], [], 'a', 'b')]);
     *     count(\App\Gql\Matching\EdgeMatching::extended($chain, new \App\Gql\Datum\EdgeDatum('f', [], [], 'b', 'c'))->items) // => 2
     *
     * @return ListDatum The longer chain
     */
    public static function extended(Datum $crossed, EdgeDatum $edge): ListDatum
    {
        $items = $crossed instanceof ListDatum ? $crossed->items : [];

        return new ListDatum([...$items, $edge]);
    }
}
