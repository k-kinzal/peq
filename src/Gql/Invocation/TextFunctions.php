<?php

declare(strict_types=1);

namespace App\Gql\Invocation;

use App\Gql\Argument\NumberArgument;
use App\Gql\Argument\TextArgument;
use App\Gql\Datum\Datum;
use App\Gql\Datum\DatumKind;
use App\Gql\Datum\IntegerDatum;
use App\Gql\Datum\NullDatum;
use App\Gql\Datum\StringDatum;
use App\Gql\GqlException;
use App\Gql\StatusCode;

/**
 * GQL's functions over character strings.
 *
 * Every one of them answers the absence of a value with the absence of a value, which
 * is SQL's rule and GQL's: asking for the length of something that is not there has
 * no answer, and inventing zero would make a filter keep rows it should not.
 *
 * Case mapping is US ASCII only, as the standard specifies. That is a real limitation
 * and worth knowing about, but for a graph of PHP symbols it is not a practical one:
 * class and method names are ASCII in every codebase peq is likely to read.
 *
 * `left` and `right` are how GQL asks whether a string begins or ends with something,
 * since the standard defines no `STARTS WITH` and no `ENDS WITH`.
 *
 * @visibility App\Gql
 */
final class TextFunctions
{
    /**
     * Returns how many characters a string holds.
     *
     * @param Datum $value The string
     *
     * @example A string is as long as its characters
     *     \App\Gql\Invocation\TextFunctions::charLength(new \App\Gql\Datum\StringDatum('Invoice'))->toText() // => '7'
     * @example Something that is not there has no length
     *     \App\Gql\Invocation\TextFunctions::charLength(new \App\Gql\Datum\NullDatum())->kind() // => \App\Gql\Datum\DatumKind::Null
     *
     * @return Datum The length, or the absence of one
     *
     * @throws GqlException If the value is not a string
     */
    public static function charLength(Datum $value): Datum
    {
        if ($value->kind() === DatumKind::Null) {
            return new NullDatum();
        }

        return new IntegerDatum(mb_strlen(TextArgument::of($value)));
    }

    /**
     * Returns a string with its ASCII letters in upper case.
     *
     * @param Datum $value The string
     *
     * @example Case mapping makes a comparison insensitive to case
     *     \App\Gql\Invocation\TextFunctions::upper(new \App\Gql\Datum\StringDatum('Invoice'))->toText() // => 'INVOICE'
     *
     * @return Datum The string, or the absence of one
     *
     * @throws GqlException If the value is not a string
     */
    public static function upper(Datum $value): Datum
    {
        if ($value->kind() === DatumKind::Null) {
            return new NullDatum();
        }

        return new StringDatum(strtoupper(TextArgument::of($value)));
    }

    /**
     * Returns a string with its ASCII letters in lower case.
     *
     * @param Datum $value The string
     *
     * @example Case mapping makes a comparison insensitive to case
     *     \App\Gql\Invocation\TextFunctions::lower(new \App\Gql\Datum\StringDatum('Invoice'))->toText() // => 'invoice'
     *
     * @return Datum The string, or the absence of one
     *
     * @throws GqlException If the value is not a string
     */
    public static function lower(Datum $value): Datum
    {
        if ($value->kind() === DatumKind::Null) {
            return new NullDatum();
        }

        return new StringDatum(strtolower(TextArgument::of($value)));
    }

    /**
     * Returns a string without the whitespace at either end of it.
     *
     * @param Datum $value The string
     *
     * @example Trimming takes the whitespace off both ends
     *     \App\Gql\Invocation\TextFunctions::trim(new \App\Gql\Datum\StringDatum('  Invoice '))->toText() // => 'Invoice'
     *
     * @return Datum The string, or the absence of one
     *
     * @throws GqlException If the value is not a string
     */
    public static function trim(Datum $value): Datum
    {
        if ($value->kind() === DatumKind::Null) {
            return new NullDatum();
        }

        return new StringDatum(trim(TextArgument::of($value)));
    }

    /**
     * Returns the first characters of a string.
     *
     * This is GQL's `<substring function>` read from the left, and it is how a query
     * asks whether something begins with something: `left(m.id, 11) = 'App\\Domain'`.
     * Asking for more characters than there are gives the whole string, as the
     * standard says, rather than reporting the question wrong.
     *
     * @param Datum $value  The string
     * @param Datum $length How many characters to take
     *
     * @example A namespace is asked for as a prefix
     *     $name = new \App\Gql\Datum\StringDatum('App\\Domain\\Invoice');
     *     \App\Gql\Invocation\TextFunctions::left($name, new \App\Gql\Datum\IntegerDatum(10))->toText() // => 'App\Domain'
     * @example Asking for more than there is gives what there is
     *     \App\Gql\Invocation\TextFunctions::left(new \App\Gql\Datum\StringDatum('ab'), new \App\Gql\Datum\IntegerDatum(9))->toText() // => 'ab'
     * @example Something that is not there has no first characters
     *     \App\Gql\Invocation\TextFunctions::left(new \App\Gql\Datum\NullDatum(), new \App\Gql\Datum\IntegerDatum(1))->kind() // => \App\Gql\Datum\DatumKind::Null
     *
     * @return Datum The characters, or the absence of them
     *
     * @throws GqlException If the values are not a string and a whole number
     */
    public static function left(Datum $value, Datum $length): Datum
    {
        if ($value->kind() === DatumKind::Null || $length->kind() === DatumKind::Null) {
            return new NullDatum();
        }

        return new StringDatum(mb_substr(TextArgument::of($value), 0, self::length($length)));
    }

    /**
     * Returns the last characters of a string.
     *
     * The other half of `<substring function>`, and how a query asks whether something
     * ends with something: `right(c.name, 10) = 'Controller'`.
     *
     * @param Datum $value  The string
     * @param Datum $length How many characters to take
     *
     * @example A convention is asked for as a suffix
     *     $name = new \App\Gql\Datum\StringDatum('UserController');
     *     \App\Gql\Invocation\TextFunctions::right($name, new \App\Gql\Datum\IntegerDatum(10))->toText() // => 'Controller'
     * @example Taking none of a string gives none of it
     *     \App\Gql\Invocation\TextFunctions::right(new \App\Gql\Datum\StringDatum('ab'), new \App\Gql\Datum\IntegerDatum(0))->toText() // => ''
     *
     * @return Datum The characters, or the absence of them
     *
     * @throws GqlException If the values are not a string and a whole number
     */
    public static function right(Datum $value, Datum $length): Datum
    {
        if ($value->kind() === DatumKind::Null || $length->kind() === DatumKind::Null) {
            return new NullDatum();
        }
        $taken = self::length($length);
        $subject = TextArgument::of($value);

        return new StringDatum($taken === 0 ? '' : mb_substr($subject, -$taken));
    }

    /**
     * Reads a value as the number of characters a substring is asked for.
     *
     * @param Datum $length The value
     *
     * @example A whole number is a length
     *     \App\Gql\Invocation\TextFunctions::length(new \App\Gql\Datum\IntegerDatum(3)) // => 3
     *
     * @return int The length
     *
     * @throws GqlException If the value is not a whole number, or is a negative one
     */
    public static function length(Datum $length): int
    {
        $taken = NumberArgument::of($length);
        if (!is_int($taken) || $taken < 0) {
            throw GqlException::because(
                StatusCode::SubstringError,
                'a substring is as many characters long as a whole number that is not negative, not '.$length->toText(),
            );
        }

        return $taken;
    }
}
