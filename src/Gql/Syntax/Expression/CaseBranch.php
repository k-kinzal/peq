<?php

declare(strict_types=1);

namespace App\Gql\Syntax\Expression;

use App\Gql\Syntax\Expression;

/**
 * One `WHEN ... THEN ...` of a conditional expression.
 *
 * It is a pair rather than an expression of its own: a branch is not something a
 * query can compute on its own, only something a `CASE` chooses between.
 */
final class CaseBranch
{
    /**
     * @param Expression $when What has to hold for this branch to be taken
     * @param Expression $then What the expression is worth when it does
     */
    public function __construct(
        public readonly Expression $when,
        public readonly Expression $then,
    ) {}
}
