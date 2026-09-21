<?php

declare(strict_types=1);

namespace App\Gql\Syntax\Clause;

use App\Gql\Syntax\Expression;

/**
 * One column of what a query returns.
 *
 * A column written without a name is headed by the text that produced it, which is
 * how `RETURN p.firstName` comes back under `p.firstName`. Naming it with `AS` is
 * worth doing for anything a reader will refer to again — a sort key written after
 * the projection can use the name, and a program reading the result gets a heading
 * that does not change when the expression is rewritten.
 */
final readonly class Projection
{
    /**
     * @param Expression  $value   What the column holds
     * @param null|string $alias   What the column is called, or null when it is called after what produced it
     * @param string      $written The text that produced it, used as its heading when it has no name
     */
    public function __construct(
        public Expression $value,
        public ?string $alias = null,
        public string $written = '',
    ) {}

    /**
     * Returns the heading the column is shown under.
     *
     * @example A named column is headed by its name
     *     $value = new \App\Gql\Syntax\Expression\VariableExpression('p');
     *     (new \App\Gql\Syntax\Clause\Projection($value, 'person', 'p'))->heading() // => 'person'
     * @example An unnamed one is headed by what produced it
     *     $value = new \App\Gql\Syntax\Expression\VariableExpression('p');
     *     (new \App\Gql\Syntax\Clause\Projection($value, null, 'p.firstName'))->heading() // => 'p.firstName'
     *
     * @return string The heading
     */
    public function heading(): string
    {
        return $this->alias ?? $this->written;
    }
}
