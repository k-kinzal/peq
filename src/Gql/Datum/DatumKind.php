<?php

declare(strict_types=1);

namespace App\Gql\Datum;

/**
 * The kinds of value a GQL query can hold.
 *
 * GQL names its types the way SQL does, and a query that fails because a value was
 * of the wrong kind should say so in those names rather than in PHP's. Keeping the
 * mapping here means one place decides that a whole number is an `INT64` and a path
 * is a `PATH`, and every message, every result column heading and every type error
 * agrees about it.
 */
enum DatumKind
{
    /** The absence of a value, which GQL also reads as the unknown truth value */
    case Null;

    /** A truth value */
    case Boolean;

    /** A whole number */
    case Integer;

    /** An approximate number */
    case Float;

    /** A character string */
    case Text;

    /** A list of values */
    case ListOf;

    /** A reference to a node of the graph */
    case Node;

    /** A reference to an edge of the graph */
    case Edge;

    /** A path through the graph */
    case Path;

    /** A moment in time, with the offset it is written in */
    case DateTime;

    /**
     * Names the kind the way GQL writes the type.
     *
     * @example A whole number is what GQL calls an INT64
     *     \App\Gql\Datum\DatumKind::Integer->typeName() // => 'INT64'
     * @example A moment carries its offset, and GQL says so in the name
     *     \App\Gql\Datum\DatumKind::DateTime->typeName() // => 'ZONED DATETIME'
     *
     * @return string The GQL name of the type
     */
    public function typeName(): string
    {
        return match ($this) {
            self::Null => 'NULL',
            self::Boolean => 'BOOL',
            self::Integer => 'INT64',
            self::Float => 'FLOAT64',
            self::Text => 'STRING',
            self::ListOf => 'LIST',
            self::Node => 'NODE',
            self::Edge => 'EDGE',
            self::Path => 'PATH',
            self::DateTime => 'ZONED DATETIME',
        };
    }

    /**
     * Reports whether values of this kind are numbers.
     *
     * Arithmetic and numeric comparison both need the same answer, and GQL's rule is
     * that the two numeric kinds mix freely and nothing else joins them.
     *
     * @example Both numeric kinds are numbers
     *     \App\Gql\Datum\DatumKind::Float->numeric() // => true
     * @example A string that happens to spell one is not
     *     \App\Gql\Datum\DatumKind::Text->numeric() // => false
     *
     * @return bool True when the kind is one of the numeric kinds
     */
    public function numeric(): bool
    {
        return match ($this) {
            self::Integer,
            self::Float => true,

            self::Null,
            self::Boolean,
            self::Text,
            self::ListOf,
            self::Node,
            self::Edge,
            self::Path,
            self::DateTime => false,
        };
    }

    /**
     * Returns the order kinds are sorted in when values of different kinds meet.
     *
     * Sorting a column that holds more than one kind still has to terminate, and GQL
     * settles it by ordering the kinds themselves. Null sorts first because the
     * standard says null is the smallest value there is; the rest follow in the order
     * a reader would expect to see them grouped in.
     *
     * @example Null sorts before everything
     *     \App\Gql\Datum\DatumKind::Null->rank() // => 0
     * @example Numbers sort together, before text
     *     \App\Gql\Datum\DatumKind::Integer->rank() < \App\Gql\Datum\DatumKind::Text->rank() // => true
     *
     * @return int The place of this kind in the order of kinds
     */
    public function rank(): int
    {
        return match ($this) {
            self::Null => 0,
            self::Boolean => 1,
            self::Integer, self::Float => 2,
            self::DateTime => 3,
            self::Text => 4,
            self::ListOf => 5,
            self::Node => 6,
            self::Edge => 7,
            self::Path => 8,
        };
    }
}
