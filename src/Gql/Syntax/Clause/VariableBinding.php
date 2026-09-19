<?php

declare(strict_types=1);

namespace App\Gql\Syntax\Clause;

use App\Gql\Syntax\Expression;

/**
 * One name a LET gives to a computed value.
 *
 * Several bindings in one `LET` are worked out from the same row and cannot see each
 * other, which is GQL's rule and worth stating where the binding lives: a name
 * defined here is available from the next clause on, not from the next comma.
 */
final class VariableBinding
{
    /**
     * @param string     $name  The name being given
     * @param Expression $value What it is given to
     */
    public function __construct(
        public readonly string $name,
        public readonly Expression $value,
    ) {
        assert($this->name !== '', 'A binding names something');
    }
}
