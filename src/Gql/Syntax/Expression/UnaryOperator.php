<?php

declare(strict_types=1);

namespace App\Gql\Syntax\Expression;

/**
 * An operator written before the one value it acts on.
 *
 * The null tests and the truth-value tests are here rather than among the comparisons
 * because that is what they are: `IS NULL` and `IS UNKNOWN` ask about one value and
 * cannot be undecided, which is exactly what makes them the way out of three-valued
 * logic. Writing them as comparisons would make them inherit the undecidability they
 * exist to escape.
 */
enum UnaryOperator
{
    /** Turns true into false, false into true, and leaves the undecided undecided */
    case Not;

    /** The number with its sign reversed */
    case Negate;

    /** The number as it stands, written with a sign for symmetry */
    case Identity;

    /** Whether the value is absent, which is never itself undecided */
    case IsNull;

    /** Whether the value is present, which is never itself undecided */
    case IsNotNull;

    /** Whether a truth value is true, which is never undecided */
    case IsTrue;

    /** Whether a truth value is anything but true */
    case IsNotTrue;

    /** Whether a truth value is false */
    case IsFalse;

    /** Whether a truth value is anything but false */
    case IsNotFalse;

    /** Whether a truth value is the undecided one */
    case IsUnknown;

    /** Whether a truth value is decided either way */
    case IsNotUnknown;

    /**
     * Writes the operator the way a query writes it.
     *
     * @example A negation reads as the word GQL writes for it
     *     \App\Gql\Syntax\Expression\UnaryOperator::Not->spelling() // => 'NOT'
     * @example A null test reads as the phrase GQL writes for it
     *     \App\Gql\Syntax\Expression\UnaryOperator::IsNotNull->spelling() // => 'IS NOT NULL'
     *
     * @return string The operator, as a query writes it
     */
    public function spelling(): string
    {
        return match ($this) {
            self::Not => 'NOT',
            self::Negate => '-',
            self::Identity => '+',
            self::IsNull => 'IS NULL',
            self::IsNotNull => 'IS NOT NULL',
            self::IsTrue => 'IS TRUE',
            self::IsNotTrue => 'IS NOT TRUE',
            self::IsFalse => 'IS FALSE',
            self::IsNotFalse => 'IS NOT FALSE',
            self::IsUnknown => 'IS UNKNOWN',
            self::IsNotUnknown => 'IS NOT UNKNOWN',
        };
    }
}
