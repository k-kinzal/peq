<?php

declare(strict_types=1);

namespace App\Reporter\Query;

use App\Gql\Datum\Datum;
use App\Gql\Datum\DatumJson;
use App\Gql\Result\ResultColumn;
use App\Gql\Result\ResultRow;
use App\Gql\Result\ResultTable;
use Override;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Writes a query's answer as a JSON document.
 *
 * This is the form written for a program, and for the reader peq's query language
 * exists for: an agent that asked a question and has to decide what to do next. It
 * carries the status first, because the standard says a status is the part a program
 * is allowed to test for, and because "this found nothing" and "this could not be
 * read" need different next moves.
 *
 * Symbols and relations serialise as objects rather than as names, so an agent that
 * matched a pattern gets the labels and properties it selected by without having to
 * ask a second query for them.
 *
 * @visibility App\Reporter
 */
final class JsonWriter implements QueryReporter
{
    /**
     * Writes the answer as JSON.
     *
     * @param ResultTable     $result What the query answered
     * @param OutputInterface $output Where it is written
     */
    #[Override]
    public function report(ResultTable $result, OutputInterface $output): void
    {
        $output->writeln(self::document($result), OutputInterface::OUTPUT_RAW);
    }

    /**
     * Writes the whole answer as one JSON document.
     *
     * @param ResultTable $result What the query answered
     *
     * @example An answer carries its status, its columns and its rows
     *     $row = \App\Gql\Binding\BindingRow::unit()->with('n', new \App\Gql\Datum\IntegerDatum(1));
     *     $result = \App\Gql\Result\ResultTable::of(['n'], new \App\Gql\Binding\BindingTable([$row]));
     *     \App\Reporter\Query\JsonWriter::document($result) // => '{"status":"00000","condition":"note: successful completion","columns":[{"name":"n","type":"INT64"}],"rows":[[1]]}'
     * @example An answer that found nothing says so rather than failing
     *     \App\Reporter\Query\JsonWriter::document(\App\Gql\Result\ResultTable::nothing()) // => '{"status":"02000","condition":"note: no data","columns":[],"rows":[]}'
     *
     * @return string The document
     */
    public static function document(ResultTable $result): string
    {
        $status = $result->status();

        return '{"status":'.DatumJson::text($status->value)
            .',"condition":'.DatumJson::text($status->condition())
            .',"columns":['.implode(',', array_map(self::column(...), $result->columns))
            .'],"rows":['.implode(',', array_map(self::row(...), $result->rows)).']}';
    }

    /**
     * Writes one column heading as JSON.
     *
     * @param ResultColumn $column The column
     *
     * @example A column says what it is called and what it holds
     *     \App\Reporter\Query\JsonWriter::column(new \App\Gql\Result\ResultColumn('n', 'INT64')) // => '{"name":"n","type":"INT64"}'
     *
     * @return string The column, written as JSON
     */
    public static function column(ResultColumn $column): string
    {
        return '{"name":'.DatumJson::text($column->heading).',"type":'.DatumJson::text($column->type).'}';
    }

    /**
     * Writes one row as JSON.
     *
     * A row is an array rather than an object, because the columns are already named
     * once for the whole table and naming them again on every row would multiply the
     * size of an answer by the length of its headings.
     *
     * @param ResultRow $row The row
     *
     * @example A row is the values of its columns, in order
     *     \App\Reporter\Query\JsonWriter::row(new \App\Gql\Result\ResultRow([new \App\Gql\Datum\IntegerDatum(1)])) // => '[1]'
     *
     * @return string The row, written as JSON
     */
    public static function row(ResultRow $row): string
    {
        return '['.implode(',', array_map(static fn (Datum $value): string => DatumJson::of($value), $row->values)).']';
    }
}
