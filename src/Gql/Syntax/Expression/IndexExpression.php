<?php

declare(strict_types=1);

namespace App\Gql\Syntax\Expression;

use App\Gql\Syntax\Expression;

/**
 * One value taken out of a list by its place in it.
 *
 * Lists are counted from zero, the way GQL counts them. The main thing indexed in a
 * query about code is the list of edges a variable-length pattern bound: `e[0]` is
 * the first call in a chain, which is the one a reader usually wants to look at.
 */
final class IndexExpression implements Expression
{
    /**
     * @param Expression $subject The list the value is taken out of
     * @param Expression $index   Which place in it to take, counting from zero
     */
    public function __construct(
        public readonly Expression $subject,
        public readonly Expression $index,
    ) {}
}
