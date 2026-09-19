<?php

declare(strict_types=1);

namespace App\Gql\Invocation;

use App\Gql\Datum\Datum;
use App\Gql\Datum\DatumKind;
use App\Gql\Datum\IntegerDatum;
use App\Gql\Datum\NullDatum;
use App\Gql\Datum\StringDatum;
use App\Gql\Evaluation\TextOperation;
use App\Gql\GqlException;

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

        return new IntegerDatum(mb_strlen(TextOperation::characters($value)));
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

        return new StringDatum(strtoupper(TextOperation::characters($value)));
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

        return new StringDatum(strtolower(TextOperation::characters($value)));
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

        return new StringDatum(trim(TextOperation::characters($value)));
    }

    /**
     * Returns the values of a list written one after another, separated.
     *
     * A value of the list that is not there is left out rather than written as an
     * empty string, so joining a list of names does not produce a run of separators
     * where the names were missing.
     *
     * @param Datum $values    The list
     * @param Datum $separator What to write between its values
     *
     * @example A list of names is written as one string
     *     $names = new \App\Gql\Datum\ListDatum([new \App\Gql\Datum\StringDatum('a'), new \App\Gql\Datum\StringDatum('b')]);
     *     \App\Gql\Invocation\TextFunctions::join($names, new \App\Gql\Datum\StringDatum(', '))->toText() // => 'a, b'
     *
     * @return Datum The joined string, or the absence of one
     *
     * @throws GqlException If the values are not a list and a string
     */
    public static function join(Datum $values, Datum $separator): Datum
    {
        if ($values->kind() === DatumKind::Null || $separator->kind() === DatumKind::Null) {
            return new NullDatum();
        }

        $written = [];
        foreach (ListFunctions::items($values) as $item) {
            if ($item->kind() !== DatumKind::Null) {
                $written[] = $item->toText();
            }
        }

        return new StringDatum(implode(TextOperation::characters($separator), $written));
    }
}
