<?php

declare(strict_types=1);

namespace App\Gql\Syntax\Expression;

use App\Gql\Syntax\Expression;

/**
 * A choice between values, written the way SQL writes one.
 *
 * GQL has both forms, and they are one shape here. The searched form tests a
 * predicate per branch; the simple form tests one subject against a value per branch.
 * Holding the subject as something that may be absent is what makes them the same
 * shape: with no subject, every branch is its own question.
 *
 * A `CASE` that matches nothing and was written without an `ELSE` is worth nothing,
 * which is GQL's rule and the reason the fallback may be absent too.
 */
final class CaseExpression implements Expression
{
    /**
     * @param null|Expression  $subject   What each branch is tested against, or null when branches test themselves
     * @param list<CaseBranch> $branches  The branches, in the order they are tried
     * @param null|Expression  $otherwise What the expression is worth when no branch is taken, or null when nothing is
     */
    public function __construct(
        public readonly ?Expression $subject,
        public readonly array $branches,
        public readonly ?Expression $otherwise = null,
    ) {
        assert($this->branches !== [], 'A choice offers at least one branch');
    }
}
