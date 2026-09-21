<?php

declare(strict_types=1);

namespace App\Gql\Syntax\Pattern;

/**
 * What a variable-length pattern is allowed to visit more than once.
 *
 * A dependency graph has cycles — mutual recursion, a class whose method returns its
 * own type — and a pattern that may repeat itself over one of them has infinitely
 * many matches. The modes are the four answers GQL gives to that, from the one that
 * restricts nothing to the one that forbids meeting any node twice.
 *
 * A path that names none is a `WALK`, which restricts nothing: GQL's restrictors are
 * things a query adds. That is also why a walk may not repeat without end — GQL
 * requires an unbounded quantifier to stand under a restrictor, and `TRAIL`, which
 * crosses no edge twice, is the weakest one that makes every match finite while still
 * letting a path pass through a hub class more than once.
 */
enum PathMode: string
{
    /** Nothing is restricted; a match may cross the same edge and meet the same node again */
    case Walk = 'WALK';

    /** No edge is crossed twice, which is what keeps a match finite */
    case Trail = 'TRAIL';

    /** No node is met twice, except that a path may come back to where it started */
    case Simple = 'SIMPLE';

    /** No node is met twice at all, so a match can never close a cycle */
    case Acyclic = 'ACYCLIC';
}
