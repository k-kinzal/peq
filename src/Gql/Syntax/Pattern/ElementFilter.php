<?php

declare(strict_types=1);

namespace App\Gql\Syntax\Pattern;

use App\Gql\Syntax\Expression;

/**
 * What a pattern requires of an element beyond its labels.
 *
 * GQL offers two ways of writing the requirement and this holds both, because they
 * are asked at the same moment. `{ name: 'Invoice' }` requires properties to equal
 * values; `WHERE p.line > 100` requires a predicate to hold. The first is shorthand
 * the second could express, and it exists because most requirements are equalities
 * and writing them out would bury the shape of the pattern in punctuation.
 *
 * Both are evaluated while matching rather than after it, which is what makes a
 * selective pattern cheap: an element that fails here is never followed.
 */
final class ElementFilter
{
    /**
     * @param array<string, Expression> $properties The properties that must equal given values
     * @param null|Expression           $predicate  What must hold of the element, or null when nothing beyond the properties must
     */
    public function __construct(
        public readonly array $properties = [],
        public readonly ?Expression $predicate = null,
    ) {}

    /**
     * Reports whether the filter requires anything at all.
     *
     * @example A pattern that writes no requirement requires nothing
     *     (new \App\Gql\Syntax\Pattern\ElementFilter())->empty() // => true
     *
     * @return bool True when neither properties nor a predicate were written
     */
    public function empty(): bool
    {
        return $this->properties === [] && $this->predicate === null;
    }
}
