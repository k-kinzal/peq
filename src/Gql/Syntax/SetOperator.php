<?php

declare(strict_types=1);

namespace App\Gql\Syntax;

/**
 * How two result tables are combined into one.
 *
 * Set operations are what let one query ask a question with two shapes. "Everything
 * that reaches this class, whether by calling it or by extending it" is two patterns
 * and one answer; so is "everything the controller layer reaches that the domain
 * layer does not", which is a difference rather than a union.
 *
 * Combining requires the two sides to have the same columns, in the same order,
 * because a row of one has to be a row of the other for any of these to mean
 * anything.
 */
enum SetOperator
{
    /** Every row of both, repeats and all */
    case UnionAll;

    /** Every row of both, each shown once */
    case Union;

    /** The rows of the left that the right does not also have */
    case Except;

    /** The rows both sides have */
    case Intersect;

    /** The rows of the left, or the rows of the right when the left has none */
    case Otherwise;

    /**
     * Writes the operator the way a query writes it.
     *
     * @example Keeping repeats is written out in full
     *     \App\Gql\Syntax\SetOperator::UnionAll->spelling() // => 'UNION ALL'
     * @example Dropping them is the plain form
     *     \App\Gql\Syntax\SetOperator::Union->spelling() // => 'UNION'
     *
     * @return string The operator, as a query writes it
     */
    public function spelling(): string
    {
        return match ($this) {
            self::UnionAll => 'UNION ALL',
            self::Union => 'UNION',
            self::Except => 'EXCEPT',
            self::Intersect => 'INTERSECT',
            self::Otherwise => 'OTHERWISE',
        };
    }
}
