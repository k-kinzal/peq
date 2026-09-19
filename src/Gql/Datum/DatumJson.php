<?php

declare(strict_types=1);

namespace App\Gql\Datum;

/**
 * A value written as JSON.
 *
 * Two things need this and want it to agree: the `to_json_string` function GQL
 * defines, and the reporter that writes a whole result for a program to read. A
 * symbol serialised inside a query should look the same as the same symbol serialised
 * by the reporter around it.
 *
 * The text is built rather than handed to a serialiser, because the values are not
 * PHP values: a node is an object with labels and properties, a path is the chain it
 * represents, and the absence of a value is JSON's null rather than an absent key.
 * Only the leaves — strings and numbers — go through PHP's encoder, which is what
 * they are good at.
 *
 * The reporters name this too, which is why its scope reaches past the language. A
 * symbol serialised inside a query and the same symbol serialised by the reporter
 * around it should be the same text, and the only way to promise that is for both to
 * go through here.
 *
 * @visibility App
 */
final class DatumJson
{
    /**
     * Writes a value as JSON.
     *
     * @param Datum $value The value
     *
     * @example A string is quoted and escaped the way JSON quotes one
     *     \App\Gql\Datum\DatumJson::of(new \App\Gql\Datum\StringDatum('App\\Invoice')) // => '"App\\\\Invoice"'
     * @example The absence of a value is JSON's own absence
     *     \App\Gql\Datum\DatumJson::of(new \App\Gql\Datum\NullDatum()) // => 'null'
     * @example A list is a JSON array of its values
     *     \App\Gql\Datum\DatumJson::of(new \App\Gql\Datum\ListDatum([new \App\Gql\Datum\IntegerDatum(1)])) // => '[1]'
     *
     * @return string The value, written as JSON
     */
    public static function of(Datum $value): string
    {
        if ($value instanceof NullDatum) {
            return 'null';
        }
        if ($value instanceof BooleanDatum) {
            return $value->value ? 'true' : 'false';
        }
        if ($value instanceof IntegerDatum) {
            return (string) $value->value;
        }
        if ($value instanceof FloatDatum) {
            return self::number($value->value);
        }
        if ($value instanceof ListDatum) {
            return '['.implode(',', array_map(self::of(...), $value->items)).']';
        }

        return self::element($value);
    }

    /**
     * Writes an element of the graph, or anything else, as JSON.
     *
     * A node and an edge are objects rather than names, because a program reading a
     * result wants the labels and properties it asked a pattern to select by. A path
     * is the chain of both, in the order it was walked.
     *
     * @param Datum $value The value
     *
     * @example A node carries what a query selected it by
     *     $node = new \App\Gql\Datum\NodeDatum('App\\Invoice', ['Class'], []);
     *     \App\Gql\Datum\DatumJson::element($node) // => '{"id":"App\\\\Invoice","labels":["Class"],"properties":{}}'
     * @example Anything with no shape of its own is written as its text
     *     \App\Gql\Datum\DatumJson::element(new \App\Gql\Datum\StringDatum('x')) // => '"x"'
     *
     * @return string The value, written as JSON
     */
    public static function element(Datum $value): string
    {
        if ($value instanceof NodeDatum) {
            return '{"id":'.self::text($value->id)
                .',"labels":['.implode(',', array_map(self::text(...), $value->labels))
                .'],"properties":'.self::properties($value->properties).'}';
        }
        if ($value instanceof EdgeDatum) {
            return '{"id":'.self::text($value->id)
                .',"labels":['.implode(',', array_map(self::text(...), $value->labels))
                .'],"origin":'.self::text($value->origin)
                .',"target":'.self::text($value->target)
                .',"properties":'.self::properties($value->properties).'}';
        }
        if ($value instanceof PathDatum) {
            return '['.implode(',', array_map(self::of(...), $value->elements)).']';
        }

        return self::text($value->toText());
    }

    /**
     * Writes a map of properties as a JSON object.
     *
     * @param array<string, Datum> $properties The properties, by name
     *
     * @example An element that carries nothing is still an object
     *     \App\Gql\Datum\DatumJson::properties([]) // => '{}'
     *
     * @return string The properties, written as JSON
     */
    public static function properties(array $properties): string
    {
        $written = [];
        foreach ($properties as $name => $value) {
            $written[] = self::text($name).':'.self::of($value);
        }

        return '{'.implode(',', $written).'}';
    }

    /**
     * Writes a string as a JSON string.
     *
     * Slashes are left alone, because the strings here are namespaces and file paths
     * and escaping their separators makes a result harder to read for no gain.
     *
     * @param string $value The characters
     *
     * @example A namespace separator is escaped, because JSON requires it
     *     \App\Gql\Datum\DatumJson::text('App\\Invoice') // => '"App\\\\Invoice"'
     * @example A path separator is left alone, because JSON does not
     *     \App\Gql\Datum\DatumJson::text('src/Invoice.php') // => '"src/Invoice.php"'
     *
     * @return string The string, written as JSON
     */
    public static function text(string $value): string
    {
        $written = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);

        return $written === false ? '""' : $written;
    }

    /**
     * Writes an approximate number as JSON.
     *
     * JSON has no way of writing a number that is not one, so a result that came out
     * infinite or undefined is written as JSON's absence of a value rather than as
     * text a reader would have to parse.
     *
     * @param float $value The number
     *
     * @example A number is written as itself
     *     \App\Gql\Datum\DatumJson::number(1.5) // => '1.5'
     * @example One JSON cannot write is written as an absence
     *     \App\Gql\Datum\DatumJson::number(INF) // => 'null'
     *
     * @return string The number, written as JSON
     */
    public static function number(float $value): string
    {
        if (!is_finite($value)) {
            return 'null';
        }
        $written = (string) $value;

        return str_contains($written, '.') || str_contains($written, 'E') ? $written : $written.'.0';
    }
}
