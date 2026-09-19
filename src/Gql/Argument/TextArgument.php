<?php

declare(strict_types=1);

namespace App\Gql\Argument;

use App\Gql\Datum\Datum;
use App\Gql\Datum\StringDatum;
use App\Gql\GqlException;
use App\Gql\StatusCode;

/**
 * Reading a value as the characters an operation was given.
 *
 * The string predicates want it and so do the string functions, and neither belongs
 * to the other. A value that is not a string is reported rather than converted,
 * because an operation asking whether a number starts with another number is a query
 * its author did not mean to write.
 *
 * @visibility App\Gql
 */
final class TextArgument
{
    /**
     * Reads a value as characters.
     *
     * @param Datum $value The value
     *
     * @example A string reads as its characters
     *     \App\Gql\Argument\TextArgument::of(new \App\Gql\Datum\StringDatum('App')) // => 'App'
     * @example Anything else is reported under the status GQL gives it
     *     \App\Gql\Argument\TextArgument::of(new \App\Gql\Datum\IntegerDatum(1)) // throws \App\Gql\GqlException: invalid value type
     *
     * @return string The characters
     *
     * @throws GqlException If the value is not a string
     */
    public static function of(Datum $value): string
    {
        if ($value instanceof StringDatum) {
            return $value->value;
        }

        throw GqlException::because(
            StatusCode::InvalidType,
            sprintf('a string was expected, and a %s was given', $value->kind()->typeName()),
        );
    }
}
