<?php

declare(strict_types=1);

namespace App\Gql\Invocation;

use App\Gql\Argument\TextArgument;
use App\Gql\Datum\DateTimeDatum;
use App\Gql\Datum\Datum;
use App\Gql\Datum\DatumJson;
use App\Gql\Datum\DatumKind;
use App\Gql\Datum\DatumOrder;
use App\Gql\Datum\NullDatum;
use App\Gql\Datum\StringDatum;
use App\Gql\GqlException;
use App\Gql\StatusCode;

/**
 * The functions GQL offers that belong to no one kind of value.
 *
 * `coalesce` and `nullif` are the two moves a query makes around absent values: the
 * first supplies one, the second withdraws one. Between them they are how a reader
 * escapes three-valued logic without writing a `CASE`.
 *
 * `to_json_string` is the one that matters most for the reader peq is built for. An
 * agent that has matched a pattern and wants the whole of what it found — labels,
 * properties and all — asks for it as JSON in one column rather than enumerating
 * properties it would have to know the names of first.
 *
 * @visibility App\Gql
 */
final class GeneralFunctions
{
    /**
     * Returns the first value that is there.
     *
     * @param list<Datum> $values The values, in the order they are preferred
     *
     * @example The first value that is there is the answer
     *     $values = [new \App\Gql\Datum\NullDatum(), new \App\Gql\Datum\StringDatum('Unknown')];
     *     \App\Gql\Invocation\GeneralFunctions::coalesce($values)->toText() // => 'Unknown'
     * @example When none of them is there, neither is the answer
     *     \App\Gql\Invocation\GeneralFunctions::coalesce([new \App\Gql\Datum\NullDatum()])->kind() // => \App\Gql\Datum\DatumKind::Null
     *
     * @return Datum The first value that is there, or the absence of one
     */
    public static function coalesce(array $values): Datum
    {
        foreach ($values as $value) {
            if ($value->kind() !== DatumKind::Null) {
                return $value;
            }
        }

        return new NullDatum();
    }

    /**
     * Returns a value, unless it equals another, in which case nothing.
     *
     * This is how a query turns a stand-in value back into an absent one: a
     * visibility recorded as `unknown`, a file path recorded as an empty string.
     *
     * @param Datum $value    The value
     * @param Datum $withdraw What it must not equal
     *
     * @example A value that equals the one withdrawn becomes absent
     *     $value = new \App\Gql\Datum\StringDatum('unknown');
     *     \App\Gql\Invocation\GeneralFunctions::nullif($value, new \App\Gql\Datum\StringDatum('unknown'))->kind() // => \App\Gql\Datum\DatumKind::Null
     * @example Any other value is itself
     *     $value = new \App\Gql\Datum\StringDatum('public');
     *     \App\Gql\Invocation\GeneralFunctions::nullif($value, new \App\Gql\Datum\StringDatum('unknown'))->toText() // => 'public'
     *
     * @return Datum The value, or the absence of one
     */
    public static function nullif(Datum $value, Datum $withdraw): Datum
    {
        return DatumOrder::equals($value, $withdraw) === true ? new NullDatum() : $value;
    }

    /**
     * Returns a value written as JSON.
     *
     * @param Datum $value The value
     *
     * @example A symbol serialises as everything a query could have selected it by
     *     $node = new \App\Gql\Datum\NodeDatum('App\\Invoice', ['Class'], []);
     *     \App\Gql\Invocation\GeneralFunctions::toJsonString($node)->toText() // => '{"id":"App\\\\Invoice","labels":["Class"],"properties":{}}'
     *
     * @return Datum The JSON, as a string
     */
    public static function toJsonString(Datum $value): Datum
    {
        return new StringDatum(DatumJson::of($value));
    }

    /**
     * Returns a moment in time: the one written, or the one it is now.
     *
     * @param list<Datum> $values The moment as written, or nothing to mean now
     *
     * @example A moment written in ISO 8601 is read as that moment
     *     $written = [new \App\Gql\Datum\StringDatum('2024-01-15T10:30:00+00:00')];
     *     \App\Gql\Invocation\GeneralFunctions::zonedDatetime($written)->toText() // => '2024-01-15T10:30:00+00:00'
     * @example Something that is not a moment is reported rather than guessed at
     *     \App\Gql\Invocation\GeneralFunctions::zonedDatetime([new \App\Gql\Datum\StringDatum('yesterday-ish')]) // throws \App\Gql\GqlException: invalid value type
     *
     * @return Datum The moment
     *
     * @throws GqlException If what is written is not a moment
     */
    public static function zonedDatetime(array $values): Datum
    {
        if ($values !== [] && $values[0]->kind() === DatumKind::Null) {
            return new NullDatum();
        }

        $written = $values === [] ? 'now' : TextArgument::of($values[0]);
        $moment = date_create_immutable($written);
        if ($moment === false) {
            throw GqlException::because(StatusCode::InvalidType, sprintf('"%s" is not a moment in time', $written));
        }

        return new DateTimeDatum($moment);
    }
}
