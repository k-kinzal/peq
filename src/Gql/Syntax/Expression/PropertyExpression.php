<?php

declare(strict_types=1);

namespace App\Gql\Syntax\Expression;

use App\Gql\Syntax\Expression;

/**
 * A property read off a node or an edge.
 *
 * Property access binds tighter than every other operator in GQL, which is why
 * `p.birthday < 19990101` needs no parentheses. What it reads off is an expression
 * rather than a variable, so `e[0].line` — the line of the first edge of a path — is
 * one expression rather than a special case.
 */
final class PropertyExpression implements Expression
{
    /**
     * @param Expression $subject  What the property is read off
     * @param string     $property The property name, as the query wrote it
     */
    public function __construct(
        public readonly Expression $subject,
        public readonly string $property,
    ) {
        assert($this->property !== '', 'A property is named');
    }
}
