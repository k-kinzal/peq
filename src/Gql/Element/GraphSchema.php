<?php

declare(strict_types=1);

namespace App\Gql\Element;

use App\Gql\Binding\BindingRow;
use App\Gql\Binding\BindingTable;
use App\Gql\Datum\StringDatum;
use App\Gql\Invocation\AggregateCatalog;
use App\Gql\Invocation\FunctionCatalog;
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
     *
     * @return ResultTable The vocabulary
     */
    public static function table(): ResultTable
    {
        $rows = [
            ...self::named('node label', NodeLabels::all()),
            ...self::named('edge label', EdgeLabels::all()),
            ...self::typed('node property', NodeProperties::all()),
            ...self::typed('edge property', EdgeProperties::all()),
            ...self::named('function', FunctionCatalog::all()),
            ...self::named('aggregate', AggregateCatalog::all()),
        ];

        return ResultTable::of(['category', 'name', 'type'], new BindingTable($rows));
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
     * Returns one row per name of a category that reports a type with each name.
     *
     * @param string                $category What kind of name these are
     * @param array<string, string> $types    The names, and the GQL type of each
     *
     * @example A property is reported with the type it holds
     *     $rows = \App\Gql\Element\GraphSchema::typed('node property', ['line' => 'INT64']);
     *     $rows[0]->value('type')->toText() // => 'INT64'
     *
     * @return list<BindingRow> The rows
     */
    public static function typed(string $category, array $types): array
    {
        $rows = [];
        foreach ($types as $name => $type) {
            $rows[] = BindingRow::unit()->withAll([
                'category' => new StringDatum($category),
                'name' => new StringDatum($name),
                'type' => new StringDatum($type),
            ]);
        }

        return $rows;
    }
}
