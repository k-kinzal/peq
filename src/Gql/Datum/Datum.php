<?php

declare(strict_types=1);

namespace App\Gql\Datum;

/**
 * A value a GQL query holds while it runs.
 *
 * Everything a query binds, computes and returns is one of these: what a pattern
 * bound a variable to, what an expression worked out, what a row carries into the
 * next statement. They are values rather than PHP scalars because GQL's rules about
 * them are not PHP's — null is not false, a whole number and an approximate one
 * compare as numbers but are not the same type, and a node is a value that can be
 * compared and returned but never stored as a property.
 *
 * Every implementation is immutable, because a query never changes a value it was
 * given: it works out a new one.
 */
interface Datum
{
    /**
     * Returns the kind of value this is.
     *
     * @return DatumKind The kind
     */
    public function kind(): DatumKind;

    /**
     * Writes the value out the way a result shows it.
     *
     * @return string The value, as a reader is shown it
     */
    public function toText(): string;
}
