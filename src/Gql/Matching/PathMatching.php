<?php

declare(strict_types=1);

namespace App\Gql\Matching;

use App\Gql\Binding\BindingRow;
use App\Gql\Datum\Datum;
use App\Gql\Datum\ListDatum;
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
final readonly class PathMatching
{
    /**
     * Crossing relations, once or repeatedly.
     */
    private EdgeMatching $edges;

    /**
     * @param ElementGraph         $graph      The graph being matched against
     * @param ExpressionEvaluation $evaluation How a predicate written in a pattern is worked out
     */
    public function __construct(
        private ElementGraph $graph,
        private ExpressionEvaluation $evaluation,
    ) {
        $this->edges = new EdgeMatching($graph, $evaluation);
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
     * A name a repeated group binds is a group variable: each repetition binds it
     * afresh, and once the group is matched it is bound to the list of what every
     * repetition bound, in the order they were matched. `((a)-[]->(b)){2}` binds `a`
     * and `b` to two symbols each, not to one symbol that has to recur. A group that
     * is only parenthesised, with no quantifier, repeats nothing and binds as the
     * pattern inside it does.
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
        $quantifier = $group->quantifier;
        if ($quantifier === null) {
            return $this->matchTerms($group->terms, 0, $state, $mode);
        }

        $variables = array_values(array_unique(PatternVariables::inTerms($group->terms)));
        $start = $state;
        foreach ($variables as $name) {
            $start = $start->bind($name, new ListDatum([]));
        }
        $reached = $quantifier->allows(0) ? [$start] : [];
        $frontier = [$start];
        $ceiling = $quantifier->most ?? PHP_INT_MAX;

        for ($times = 1; $times <= $ceiling && $frontier !== []; ++$times) {
            $next = [];
            foreach ($frontier as $standing) {
                array_push($next, ...$this->repeat($group, $variables, $standing, $mode));
            }
            $frontier = $next;
            if ($quantifier->allows($times)) {
                array_push($reached, ...$frontier);
            }
        }

        return $reached;
    }

    /**
     * Returns every way one more repetition of a group matches, with its names added to their lists.
     *
     * @param GroupPattern $group     The group
     * @param list<string> $variables The names it binds, each bound to the list of what earlier repetitions bound
     * @param MatchState   $standing  How far the attempt has got
     * @param PathMode     $mode      What the path may visit more than once
     *
     * @example A repetition adds what it bound to the list the name is bound to
     *     $node = new \App\Gql\Datum\NodeDatum('a');
     *     $matching = new \App\Gql\Matching\PathMatching(new \App\Gql\Element\ElementGraph(['a' => $node], [], []), new \App\Gql\Evaluation\ExpressionEvaluation());
     *     $group = new \App\Gql\Syntax\Pattern\GroupPattern([new \App\Gql\Syntax\Pattern\NodePattern('n')], \App\Gql\Syntax\Pattern\Quantifier::exactly(1));
     *     $standing = \App\Gql\Matching\MatchState::before(\App\Gql\Binding\BindingRow::unit()->with('n', new \App\Gql\Datum\ListDatum([])));
     *     $matching->repeat($group, ['n'], $standing, \App\Gql\Syntax\Pattern\PathMode::Walk)[0]->row->value('n')->toText() // => '[a]'
     *
     * @return list<MatchState> The attempts that matched it once more
     *
     * @throws GqlException If a requirement written in the pattern cannot be worked out
     */
    public function repeat(GroupPattern $group, array $variables, MatchState $standing, PathMode $mode): array
    {
        $fresh = $standing->withRow(new BindingRow(array_diff_key($standing->row->values(), array_flip($variables))));
        $repeated = [];
        foreach ($this->matchTerms($group->terms, 0, $fresh, $mode) as $after) {
            $row = $after->row;
            foreach ($variables as $name) {
                $row = $row->with($name, self::collected($standing->row->value($name), $after->row->value($name)));
            }
            $repeated[] = $after->withRow($row);
        }

        return $repeated;
    }

    /**
     * Returns a group variable's list with what one more repetition bound it to added.
     *
     * A repeated relation inside a repeated group binds a list of its own on every
     * repetition, and that list is added element by element, so that a group variable
     * is always one list of elements in the order the path crossed them.
     *
     * @param Datum $collected What earlier repetitions bound, as a list
     * @param Datum $bound     What this repetition bound
     *
     * @example A repetition adds one element to the list
     *     \App\Gql\Matching\PathMatching::collected(new \App\Gql\Datum\ListDatum([new \App\Gql\Datum\NodeDatum('a')]), new \App\Gql\Datum\NodeDatum('b'))->toText() // => '[a, b]'
     * @example A list a repetition bound is added element by element
     *     \App\Gql\Matching\PathMatching::collected(new \App\Gql\Datum\ListDatum([]), new \App\Gql\Datum\ListDatum([new \App\Gql\Datum\NodeDatum('a')]))->toText() // => '[a]'
     *
     * @return ListDatum The list
     */
    public static function collected(Datum $collected, Datum $bound): ListDatum
    {
        $items = $collected instanceof ListDatum ? $collected->items : [];

        return new ListDatum([...$items, ...($bound instanceof ListDatum ? $bound->items : [$bound])]);
    }
}
