<?php

declare(strict_types=1);

namespace App\Reporter\Query;

use App\Gql\Datum\Datum;
use App\Gql\Result\ResultColumn;
use App\Gql\Result\ResultRow;
use App\Gql\Result\ResultTable;
use Override;
use Symfony\Component\Console\Formatter\OutputFormatter;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Writes a query's answer as a table.
 *
 * This is the default for a reason: a GQL result is a table, and writing it as one
 * adds nothing and loses nothing. The column headings carry the type beside the name,
 * which is the one thing a bare table would otherwise leave a reader to guess — and
 * which a reader who is about to write a second query needs in order to know what
 * they can compare the first one's answer against.
 *
 * A query that found nothing has no table to draw. The command reports its status,
 * 02000 no data, on stderr; the JSON format carries that status in its document.
 *
 * @visibility App\Reporter
 */
final class TableWriter implements QueryReporter
{
    /**
     * Writes the answer as a table.
     *
     * @param ResultTable     $result What the query answered
     * @param OutputInterface $output Where it is written
     */
    #[Override]
    public function report(ResultTable $result, OutputInterface $output): void
    {
        if ($result->rows === []) {
            return;
        }

        (new Table($output))
            ->setHeaders(array_map(self::heading(...), $result->columns))
            ->setRows(array_map(self::cells(...), $result->rows))
            ->render()
        ;
    }

    /**
     * Writes the heading of one column.
     *
     * @param ResultColumn $column The column
     *
     * @example A heading says what the column is called and what it holds
     *     \App\Reporter\Query\TableWriter::heading(new \App\Gql\Result\ResultColumn('n', 'INT64')) // => 'n (INT64)'
     *
     * @return string The heading
     */
    public static function heading(ResultColumn $column): string
    {
        return OutputFormatter::escape(sprintf('%s (%s)', $column->heading, $column->type));
    }

    /**
     * Writes the cells of one row.
     *
     * @param ResultRow $row The row
     *
     * @example A cell holds a value written the way a result shows it
     *     \App\Reporter\Query\TableWriter::cells(new \App\Gql\Result\ResultRow([new \App\Gql\Datum\IntegerDatum(1)])) // => ['1']
     *
     * @return list<string> The cells, in the order the columns are shown
     */
    public static function cells(ResultRow $row): array
    {
        return array_map(static fn (Datum $value): string => OutputFormatter::escape($value->toText()), $row->values);
    }
}
