<?php

declare(strict_types=1);

namespace App\Gql\Syntax\Pattern;

/**
 * What a pattern requires of an edge, which way it crosses it, and how often.
 *
 * The quantifier is part of the edge rather than of the path around it because that
 * is how GQL writes it: `-[:knows]->{1,3}` repeats the edge, not the nodes at its
 * ends. It changes what the variable means, too. Written without one, an edge
 * variable binds to an edge; written with one, the same variable binds outside the
 * pattern to the list of every edge along the match, which is what makes `size(e)`
 * the distance between two symbols.
 */
final readonly class EdgePattern implements PathTerm
{
    /**
     * @param EdgeDirection     $direction  Which way the pattern crosses the edge
     * @param null|string       $variable   What to bind the matched edge to, or null when the match is not named
     * @param null|LabelPattern $labels     What its labels must satisfy, or null when they need satisfy nothing
     * @param ElementFilter     $filter     What else it must satisfy
     * @param null|Quantifier   $quantifier How often it repeats, or null when it is crossed exactly once
     */
    public function __construct(
        public EdgeDirection $direction,
        public ?string $variable = null,
        public ?LabelPattern $labels = null,
        public ElementFilter $filter = new ElementFilter(),
        public ?Quantifier $quantifier = null,
    ) {}
}
