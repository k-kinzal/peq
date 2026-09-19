<?php

declare(strict_types=1);

namespace App\Gql\Result;

use App\Gql\Binding\BindingTable;
use App\Gql\Datum\Datum;
use App\Gql\Datum\DatumKind;
use App\Gql\StatusCode;

/**
 * What a query answered: named columns, rows, and how it went.
 *
 * The status is part of the result rather than something the caller works out,
 * because GQL says it is: a query that ran and found nothing succeeded, and a reader
 * that cannot tell that apart from a query that failed will eventually treat one as
 * the other. Finding nothing is an answer — often the answer a question about
 * impact was hoping for.
 */
final class ResultTable
{
    /**
     * @param list<ResultColumn> $columns The columns, in the order they are shown
     * @param list<ResultRow>    $rows    The rows, in the order they are shown
     */
    public function __construct(
        public readonly array $columns,
        public readonly array $rows,
    ) {}

    /**
     * Returns the result of a query, given its columns and the rows it bound.
     *
     * @param list<string> $headings What the columns are called, in order
     * @param BindingTable $table    The rows the query produced
     *
     * @example A result takes its columns from what the query asked for
     *     $row = \App\Gql\Binding\BindingRow::unit()->with('n', new \App\Gql\Datum\IntegerDatum(1));
     *     $result = \App\Gql\Result\ResultTable::of(['n'], new \App\Gql\Binding\BindingTable([$row]));
     *     $result->columns[0]->type // => 'INT64'
     *
     * @return self The result
     */
    public static function of(array $headings, BindingTable $table): self
    {
        $rows = [];
        foreach ($table->rows as $row) {
            $values = [];
            foreach ($headings as $heading) {
                $values[] = $row->value($heading);
            }
            $rows[] = new ResultRow($values);
        }

        $columns = [];
        foreach ($headings as $index => $heading) {
            $columns[] = new ResultColumn($heading, self::typeOf($rows, $index));
        }

        return new self($columns, $rows);
    }

    /**
     * Returns the result of a query that answered nothing at all.
     *
     * @example A query that answered nothing has no columns and no rows
     *     \App\Gql\Result\ResultTable::nothing()->rows // => []
     *
     * @return self The result
     */
    public static function nothing(): self
    {
        return new self([], []);
    }

    /**
     * Returns the GQLSTATUS the result is reported under.
     *
     * @example A query that found something says so
     *     $row = \App\Gql\Binding\BindingRow::unit()->with('n', new \App\Gql\Datum\IntegerDatum(1));
     *     \App\Gql\Result\ResultTable::of(['n'], new \App\Gql\Binding\BindingTable([$row]))->status() // => \App\Gql\StatusCode::Success
     * @example A query that found nothing says that, which is not the same as failing
     *     \App\Gql\Result\ResultTable::nothing()->status() // => \App\Gql\StatusCode::NoData
     *
     * @return StatusCode The status
     */
    public function status(): StatusCode
    {
        return $this->rows === [] ? StatusCode::NoData : StatusCode::Success;
    }

    /**
     * Returns the names of the columns, in the order they are shown.
     *
     * @example The headings are what the query asked its columns to be called
     *     $row = \App\Gql\Binding\BindingRow::unit()->with('n', new \App\Gql\Datum\IntegerDatum(1));
     *     \App\Gql\Result\ResultTable::of(['n'], new \App\Gql\Binding\BindingTable([$row]))->headings() // => ['n']
     *
     * @return list<string> The headings
     */
    public function headings(): array
    {
        return array_map(static fn (ResultColumn $column): string => $column->heading, $this->columns);
    }

    /**
     * Returns the GQL name of what a column holds.
     *
     * A column holding values of more than one kind is reported as holding any kind
     * rather than as holding the kind of whichever value happened to come first.
     *
     * @param list<ResultRow> $rows   The rows
     * @param int             $column Which column, counting from zero
     *
     * @example A column of whole numbers holds whole numbers
     *     $rows = [new \App\Gql\Result\ResultRow([new \App\Gql\Datum\IntegerDatum(1)])];
     *     \App\Gql\Result\ResultTable::typeOf($rows, 0) // => 'INT64'
     * @example A column of more than one kind holds any of them
     *     $rows = [new \App\Gql\Result\ResultRow([new \App\Gql\Datum\IntegerDatum(1)]), new \App\Gql\Result\ResultRow([new \App\Gql\Datum\StringDatum('a')])];
     *     \App\Gql\Result\ResultTable::typeOf($rows, 0) // => 'ANY'
     * @example A column with nothing in it holds nothing
     *     \App\Gql\Result\ResultTable::typeOf([], 0) // => 'NULL'
     *
     * @return string The GQL name of the type
     */
    public static function typeOf(array $rows, int $column): string
    {
        $found = null;
        foreach ($rows as $row) {
            $kind = $row->value($column)->kind();
            if ($kind === DatumKind::Null) {
                continue;
            }
            if ($found !== null && $found !== $kind) {
                return 'ANY';
            }
            $found = $kind;
        }

        return ($found ?? DatumKind::Null)->typeName();
    }

    /**
     * Returns every value of one column, for a reader that wants the column itself.
     *
     * @param int $column Which column, counting from zero
     *
     * @example A column with no rows holds no values
     *     \App\Gql\Result\ResultTable::nothing()->column(0) // => []
     *
     * @return list<Datum> The values, in row order
     */
    public function column(int $column): array
    {
        return array_map(static fn (ResultRow $row): Datum => $row->value($column), $this->rows);
    }
}
