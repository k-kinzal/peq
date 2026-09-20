<?php

declare(strict_types=1);

namespace App\Gql\Element;

use App\Gql\Binding\BindingRow;
use App\Gql\Binding\BindingTable;
use App\Gql\Datum\StringDatum;
use App\Gql\Invocation\AggregateCatalog;
use App\Gql\Invocation\FunctionCatalog;
use App\Gql\ReservedWords;
use App\Gql\Result\ResultTable;

/**
 * What a query can be written against, written down.
 *
 * A graph query language is only usable by someone who knows the vocabulary: which
 * labels exist, which properties a symbol carries, what the functions are called. A
 * person finds that out by reading the documentation. An agent asking peq a question
 * about a codebase it has just met has no documentation to read, so it has to be able
 * to ask.
 *
 * The answer is a result table like any other, which means it can be asked for in any
 * format the reader already knows how to read — as a table to look at, or as JSON to
 * write the next query from.
 */
final class GraphSchema
{
    /**
     * Returns the whole vocabulary a query can be written against.
     *
     * @example The vocabulary says what each name is and, where it has one, its type
     *     \App\Gql\Element\GraphSchema::table()->headings() // => ['category', 'name', 'type']
     * @example It covers the labels a pattern selects by
     *     $rows = \App\Gql\Element\GraphSchema::table()->column(1);
     *     in_array('Callable', array_map(static fn ($value) => $value->toText(), $rows), true) // => true
     * @example A name GQL reserves comes back the way a query has to write it
     *     $rows = \App\Gql\Element\GraphSchema::table()->column(1);
     *     in_array('`Function`', array_map(static fn ($value) => $value->toText(), $rows), true) // => true
     *
     * @return ResultTable The vocabulary
     */
    public static function table(): ResultTable
    {
        $rows = [
            ...self::labelled('node label', NodeLabels::all()),
            ...self::labelled('edge label', EdgeLabels::all()),
            ...self::typed('node property', NodeProperties::all()),
            ...self::typed('edge property', EdgeProperties::all()),
            ...self::named('function', FunctionCatalog::all()),
            ...self::named('aggregate', AggregateCatalog::all()),
        ];

        return ResultTable::of(['category', 'name', 'type'], new BindingTable($rows));
    }

    /**
     * Returns one row per label, each written the way a pattern has to write it.
     *
     * A label is an identifier, so one that spells a word GQL reserves is written in
     * back quotes. Reporting `Function` instead would be reporting a pattern that
     * does not parse to the one reader who cannot check it by reading a document.
     *
     * @param string       $category What kind of label these are
     * @param list<string> $names    The labels, as the graph carries them
     *
     * @example A label GQL leaves free is reported as it is
     *     $rows = \App\Gql\Element\GraphSchema::labelled('node label', ['Method']);
     *     $rows[0]->value('name')->toText() // => 'Method'
     * @example One GQL reserves is reported in back quotes
     *     $rows = \App\Gql\Element\GraphSchema::labelled('node label', ['Function']);
     *     $rows[0]->value('name')->toText() // => '`Function`'
     *
     * @return list<BindingRow> The rows
     */
    public static function labelled(string $category, array $names): array
    {
        return self::named($category, array_map(
            static fn (string $name): string => ReservedWords::asWritten($name),
            $names,
        ));
    }

    /**
     * Returns one row per name of a category that has no types to report.
     *
     * @param string       $category What kind of name these are
     * @param list<string> $names    The names
     *
     * @example A category with no names has no rows
     *     \App\Gql\Element\GraphSchema::named('node label', []) // => []
     *
     * @return list<BindingRow> The rows
     */
    public static function named(string $category, array $names): array
    {
        $rows = [];
        foreach ($names as $name) {
            $rows[] = BindingRow::unit()->withAll([
                'category' => new StringDatum($category),
                'name' => new StringDatum($name),
                'type' => new StringDatum(''),
            ]);
        }

        return $rows;
    }

    /**
     * Returns one row per property, each written the way a query has to write it.
     *
     * @param string                $category What kind of property these are
     * @param array<string, string> $types    The properties, and the GQL type of each
     *
     * @example A property is reported with the type it holds
     *     $rows = \App\Gql\Element\GraphSchema::typed('node property', ['line' => 'INT64']);
     *     $rows[0]->value('type')->toText() // => 'INT64'
     * @example One named after a word GQL reserves is reported in back quotes
     *     $rows = \App\Gql\Element\GraphSchema::typed('node property', ['value' => 'STRING']);
     *     $rows[0]->value('name')->toText() // => '`value`'
     *
     * @return list<BindingRow> The rows
     */
    public static function typed(string $category, array $types): array
    {
        $rows = [];
        foreach ($types as $name => $type) {
            $rows[] = BindingRow::unit()->withAll([
                'category' => new StringDatum($category),
                'name' => new StringDatum(ReservedWords::asWritten($name)),
                'type' => new StringDatum($type),
            ]);
        }

        return $rows;
    }
}
