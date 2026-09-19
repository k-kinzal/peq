<?php

declare(strict_types=1);

namespace App\Gql\Matching;

use App\Gql\Binding\BindingRow;
use App\Gql\Datum\NodeDatum;
use App\Gql\Element\ElementGraph;
use App\Gql\Evaluation\ExpressionEvaluation;
use App\Gql\GqlException;
use App\Gql\Syntax\Pattern\EdgePattern;
use App\Gql\Syntax\Pattern\GroupPattern;
use App\Gql\Syntax\Pattern\NodePattern;
use App\Gql\Syntax\Pattern\PathMode;
use App\Gql\Syntax\Pattern\PathPattern;
use App\Gql\Syntax\Pattern\PathTerm;
use App\Gql\Syntax\Pattern\Quantifier;

/**
 * Finding every way one path pattern matches the graph.
 *
 * The search walks the pattern from left to right, carrying every attempt that is
 * still alive. A symbol pattern at the start is the only one that has to look at the
 * whole graph; after that, every step follows relations from where the last one
 * arrived, which is why a pattern that pins down its first symbol is so much cheaper
 * than one that does not.
 *
 * A name already bound is what makes the search cheap in the other direction too:
 * a pattern whose symbol is bound by an earlier clause starts from that symbol rather
 * than from everything, so building an answer up in steps costs no more than writing
 * it as one pattern.
 *
 * @visibility App\Gql
 */
final class PathMatching
{
    /**
     * Crossing relations, once or repeatedly.
     */
    private readonly EdgeMatching $edges;

    /**
     * @param ElementGraph         $graph      The graph being matched against
     * @param ExpressionEvaluation $evaluation How a predicate written in a pattern is worked out
     * @param int                  $hopLimit   How far a repetition goes when no upper bound was written
     */
    public function __construct(
        private readonly ElementGraph $graph,
        private readonly ExpressionEvaluation $evaluation,
        private readonly int $hopLimit = 10,
    ) {
        $this->edges = new EdgeMatching($graph, $evaluation, $hopLimit);
    }

    /**
     * Returns every way a path pattern matches, as rows of what it bound.
     *
     * @param PathPattern $path The pattern
     * @param BindingRow  $row  What is already bound when the pattern is reached
     *
     * @example A pattern that matches nothing binds nothing
     *     $matching = new \App\Gql\Matching\PathMatching(new \App\Gql\Element\ElementGraph([], [], []), new \App\Gql\Evaluation\ExpressionEvaluation());
     *     $pattern = new \App\Gql\Syntax\Pattern\PathPattern([new \App\Gql\Syntax\Pattern\NodePattern('p')]);
     *     $matching->match($pattern, \App\Gql\Binding\BindingRow::unit()) // => []
     *
     * @return list<BindingRow> One row per way the pattern matched
     *
     * @throws GqlException If a requirement written in the pattern cannot be worked out
     */
    public function match(PathPattern $path, BindingRow $row): array
    {
        $rows = [];
        foreach ($this->matchTerms($path->terms, 0, MatchState::before($row), $path->mode) as $state) {
            $rows[] = $path->variable === null || $state->path === null
                ? $state->row
                : $state->row->with($path->variable, $state->path);
        }

        return $rows;
    }

    /**
     * Returns every way the rest of a pattern matches from where an attempt stands.
     *
     * @param list<PathTerm> $terms The pieces of the pattern
     * @param int            $index Which piece to match next
     * @param MatchState     $state How far the attempt has got
     * @param PathMode       $mode  What the path may visit more than once
     *
     * @example A pattern with nothing left to match has matched
     *     $matching = new \App\Gql\Matching\PathMatching(new \App\Gql\Element\ElementGraph([], [], []), new \App\Gql\Evaluation\ExpressionEvaluation());
     *     $state = \App\Gql\Matching\MatchState::before(\App\Gql\Binding\BindingRow::unit());
     *     count($matching->matchTerms([], 0, $state, \App\Gql\Syntax\Pattern\PathMode::Trail)) // => 1
     *
     * @return list<MatchState> The attempts that matched the rest of it
     *
     * @throws GqlException If a requirement written in the pattern cannot be worked out
     */
    public function matchTerms(array $terms, int $index, MatchState $state, PathMode $mode): array
    {
        if ($index >= count($terms)) {
            return [$state];
        }

        $term = $terms[$index];
        $reached = $term instanceof EdgePattern
            ? $this->edges->matches($term, $state, $mode)
            : $this->matchStanding($term, $state, $mode);

        $matched = [];
        foreach ($reached as $next) {
            array_push($matched, ...$this->matchTerms($terms, $index + 1, $next, $mode));
        }

        return $matched;
    }

    /**
     * Returns every way the piece of pattern standing at a symbol matches.
     *
     * @param PathTerm   $term  The piece of pattern
     * @param MatchState $state How far the attempt has got
     * @param PathMode   $mode  What the path may visit more than once
     *
     * @example A piece of pattern that is neither a symbol nor a group matches nothing
     *     $matching = new \App\Gql\Matching\PathMatching(new \App\Gql\Element\ElementGraph([], [], []), new \App\Gql\Evaluation\ExpressionEvaluation());
     *     $state = \App\Gql\Matching\MatchState::before(\App\Gql\Binding\BindingRow::unit());
     *     $matching->matchStanding(new \App\Gql\Syntax\Pattern\NodePattern(), $state, \App\Gql\Syntax\Pattern\PathMode::Trail) // => []
     *
     * @return list<MatchState> The attempts that matched it
     *
     * @throws GqlException If a requirement written in the pattern cannot be worked out
     */
    public function matchStanding(PathTerm $term, MatchState $state, PathMode $mode): array
    {
        if ($term instanceof GroupPattern) {
            return $this->matchGroup($term, $state, $mode);
        }
        if ($term instanceof NodePattern) {
            return $this->matchNode($term, $state, $mode);
        }

        return [];
    }

    /**
     * Returns every way a symbol pattern matches from where an attempt stands.
     *
     * An attempt that has not started yet considers every symbol the pattern could
     * be about; one that has arrived somewhere considers only where it arrived, since
     * that is the symbol the relation before it led to.
     *
     * @param NodePattern $pattern What the pattern requires of the symbol
     * @param MatchState  $state   How far the attempt has got
     * @param PathMode    $mode    What the path may visit more than once
     *
     * @example A pattern over a graph with no symbols matches nothing
     *     $matching = new \App\Gql\Matching\PathMatching(new \App\Gql\Element\ElementGraph([], [], []), new \App\Gql\Evaluation\ExpressionEvaluation());
     *     $state = \App\Gql\Matching\MatchState::before(\App\Gql\Binding\BindingRow::unit());
     *     $matching->matchNode(new \App\Gql\Syntax\Pattern\NodePattern('p'), $state, \App\Gql\Syntax\Pattern\PathMode::Trail) // => []
     *
     * @return list<MatchState> The attempts that matched it
     *
     * @throws GqlException If a requirement written in the pattern cannot be worked out
     */
    public function matchNode(NodePattern $pattern, MatchState $state, PathMode $mode): array
    {
        $matched = [];
        foreach ($this->candidates($pattern, $state) as $node) {
            if (!LabelMatching::satisfies($pattern->labels, $node->labels)
                || !ElementMatching::agrees($pattern->variable, $node, $state->row)
                || !ElementMatching::satisfies($node, $pattern->variable, $pattern->filter, $state->row, $this->evaluation)
            ) {
                continue;
            }
            if ($state->current === null && !PathModeRule::allowsNode($mode, $state, $node)) {
                continue;
            }

            $arrived = $state->current === null ? $state->startingAt($node) : $state;
            $matched[] = $pattern->variable === null ? $arrived : $arrived->bind($pattern->variable, $node);
        }

        return $matched;
    }

    /**
     * Returns the symbols a pattern could be about from where an attempt stands.
     *
     * @param NodePattern $pattern What the pattern requires of the symbol
     * @param MatchState  $state   How far the attempt has got
     *
     * @example An attempt that has arrived somewhere is only about that symbol
     *     $matching = new \App\Gql\Matching\PathMatching(new \App\Gql\Element\ElementGraph([], [], []), new \App\Gql\Evaluation\ExpressionEvaluation());
     *     $state = \App\Gql\Matching\MatchState::before(\App\Gql\Binding\BindingRow::unit())->startingAt(new \App\Gql\Datum\NodeDatum('a'));
     *     count($matching->candidates(new \App\Gql\Syntax\Pattern\NodePattern('p'), $state)) // => 1
     *
     * @return list<NodeDatum> The symbols to consider
     */
    public function candidates(NodePattern $pattern, MatchState $state): array
    {
        if ($state->current !== null) {
            return [$state->current];
        }
        if ($pattern->variable !== null && $state->row->has($pattern->variable)) {
            $bound = $state->row->value($pattern->variable);

            return $bound instanceof NodeDatum ? [$bound] : [];
        }

        return $this->graph->nodes();
    }

    /**
     * Returns every way a parenthesised stretch of pattern matches, repeated.
     *
     * Each repetition carries on from where the last one arrived, which is what makes
     * a repeated group a chain of the shape it describes rather than a set of
     * unrelated matches of it.
     *
     * @param GroupPattern $group The stretch of pattern
     * @param MatchState   $state How far the attempt has got
     * @param PathMode     $mode  What the path may visit more than once
     *
     * @example A group over a graph with no symbols matches only by not repeating
     *     $matching = new \App\Gql\Matching\PathMatching(new \App\Gql\Element\ElementGraph([], [], []), new \App\Gql\Evaluation\ExpressionEvaluation());
     *     $group = new \App\Gql\Syntax\Pattern\GroupPattern([new \App\Gql\Syntax\Pattern\NodePattern()], new \App\Gql\Syntax\Pattern\Quantifier(0, 2));
     *     $state = \App\Gql\Matching\MatchState::before(\App\Gql\Binding\BindingRow::unit());
     *     count($matching->matchGroup($group, $state, \App\Gql\Syntax\Pattern\PathMode::Trail)) // => 1
     *
     * @return list<MatchState> The attempts that matched it
     *
     * @throws GqlException If a requirement written in the pattern cannot be worked out
     */
    public function matchGroup(GroupPattern $group, MatchState $state, PathMode $mode): array
    {
        $quantifier = $group->quantifier ?? Quantifier::exactly(1);
        $reached = $quantifier->allows(0) ? [$state] : [];
        $frontier = [$state];
        $ceiling = $quantifier->ceiling($this->hopLimit);

        for ($times = 1; $times <= $ceiling && $frontier !== []; ++$times) {
            $next = [];
            foreach ($frontier as $standing) {
                array_push($next, ...$this->matchTerms($group->terms, 0, $standing, $mode));
            }
            $frontier = $next;
            if ($quantifier->allows($times)) {
                array_push($reached, ...$frontier);
            }
        }

        return $reached;
    }
}
