<?php

declare(strict_types=1);

namespace App\Gql\Syntax\Pattern;

/**
 * What a pattern requires of a node, and what it binds the node to.
 *
 * Writing the same variable twice in one pattern is how GQL says "the same node
 * here as there", which is the mechanism behind every question of the form "two
 * things that share a third": two methods of the same class, two controllers that
 * reach the same repository.
 *
 * A node pattern that requires nothing and binds nothing — a bare `()` — is still a
 * node pattern: it says a node stands there, which is what an edge needs at each end.
 */
final class NodePattern implements PathTerm
{
    /**
     * @param null|string       $variable What to bind the matched node to, or null when the match is not named
     * @param null|LabelPattern $labels   What its labels must satisfy, or null when they need satisfy nothing
     * @param ElementFilter     $filter   What else it must satisfy
     */
    public function __construct(
        public readonly ?string $variable = null,
        public readonly ?LabelPattern $labels = null,
        public readonly ElementFilter $filter = new ElementFilter(),
    ) {}
}
