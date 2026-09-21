<?php

declare(strict_types=1);

namespace App\Gql\Matching;

use App\Gql\Datum\EdgeDatum;
use App\Gql\Datum\NodeDatum;
use App\Gql\Syntax\Pattern\PathMode;

/**
 * What a path mode forbids a match from doing twice.
 *
 * A dependency graph is full of cycles — mutual recursion, a class whose method
 * returns its own type, a pair of services that call each other — and without a rule
 * about repetition a pattern over one of them has infinitely many matches. These
 * rules are what make a search end.
 *
 * A path that names no mode is a walk, and a walk forbids nothing — which is why GQL
 * lets a walk repeat only a bounded number of times. `TRAIL`, which forbids crossing
 * the same relation twice, is the weakest rule that makes an unbounded repetition
 * finite, so it rules out as little as possible of what a reader might have meant.
 *
 * @visibility App\Gql
 */
final class PathModeRule
{
    /**
     * Reports whether a match may cross a relation.
     *
     * @param PathMode   $mode  What the path may visit more than once
     * @param MatchState $state How far the attempt has got
     * @param EdgeDatum  $edge  The relation about to be crossed
     *
     * @example Crossing anything twice is allowed where nothing is forbidden
     *     $state = \App\Gql\Matching\MatchState::before(\App\Gql\Binding\BindingRow::unit());
     *     \App\Gql\Matching\PathModeRule::allowsEdge(\App\Gql\Syntax\Pattern\PathMode::Walk, $state, new \App\Gql\Datum\EdgeDatum('e', [], [], 'a', 'b')) // => true
     * @example A relation already crossed is refused by the mode that forbids it
     *     $state = \App\Gql\Matching\MatchState::before(\App\Gql\Binding\BindingRow::unit())->startingAt(new \App\Gql\Datum\NodeDatum('a'))->across(new \App\Gql\Datum\EdgeDatum('e', [], [], 'a', 'b'), new \App\Gql\Datum\NodeDatum('b'));
     *     \App\Gql\Matching\PathModeRule::allowsEdge(\App\Gql\Syntax\Pattern\PathMode::Trail, $state, new \App\Gql\Datum\EdgeDatum('e', [], [], 'a', 'b')) // => false
     *
     * @return bool True when the mode allows it
     */
    public static function allowsEdge(PathMode $mode, MatchState $state, EdgeDatum $edge): bool
    {
        return match ($mode) {
            PathMode::Walk => true,
            PathMode::Trail,
            PathMode::Simple,
            PathMode::Acyclic => !$state->crossed($edge->id),
        };
    }

    /**
     * Reports whether a match may arrive at a symbol.
     *
     * The difference between the two strictest modes is one case: a path that comes
     * back to where it started. One allows it, because a cycle through a symbol is
     * often exactly what a question about recursion is looking for; the other does
     * not, because a question about layering is not.
     *
     * @param PathMode   $mode  What the path may visit more than once
     * @param MatchState $state How far the attempt has got
     * @param NodeDatum  $node  The symbol about to be arrived at
     *
     * @example Coming back to where the path started is allowed by one mode
     *     $state = \App\Gql\Matching\MatchState::before(\App\Gql\Binding\BindingRow::unit())->startingAt(new \App\Gql\Datum\NodeDatum('a'))->across(new \App\Gql\Datum\EdgeDatum('e', [], [], 'a', 'b'), new \App\Gql\Datum\NodeDatum('b'));
     *     \App\Gql\Matching\PathModeRule::allowsNode(\App\Gql\Syntax\Pattern\PathMode::Simple, $state, new \App\Gql\Datum\NodeDatum('a')) // => true
     * @example And forbidden by the other
     *     $state = \App\Gql\Matching\MatchState::before(\App\Gql\Binding\BindingRow::unit())->startingAt(new \App\Gql\Datum\NodeDatum('a'))->across(new \App\Gql\Datum\EdgeDatum('e', [], [], 'a', 'b'), new \App\Gql\Datum\NodeDatum('b'));
     *     \App\Gql\Matching\PathModeRule::allowsNode(\App\Gql\Syntax\Pattern\PathMode::Acyclic, $state, new \App\Gql\Datum\NodeDatum('a')) // => false
     *
     * @return bool True when the mode allows it
     */
    public static function allowsNode(PathMode $mode, MatchState $state, NodeDatum $node): bool
    {
        return match ($mode) {
            PathMode::Walk,
            PathMode::Trail => true,
            PathMode::Simple => !$state->met($node->id) || $state->startedAt($node->id),
            PathMode::Acyclic => !$state->met($node->id),
        };
    }
}
