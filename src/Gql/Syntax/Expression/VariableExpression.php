<?php

declare(strict_types=1);

namespace App\Gql\Syntax\Expression;

use App\Gql\Syntax\Expression;

/**
 * A name standing for whatever an earlier clause bound it to.
 *
 * Variables are how GQL joins. Writing the same name twice in one pattern says the
 * two places must match the same element; writing a name a previous clause bound says
 * this clause continues from what that one found. Both are the same idea, and both
 * come down to reading a column of the current row.
 */
final class VariableExpression implements Expression
{
    /**
     * @param string $name The name, as the query wrote it
     */
    public function __construct(
        public readonly string $name,
    ) {
        assert($this->name !== '', 'A variable is named');
    }
}
