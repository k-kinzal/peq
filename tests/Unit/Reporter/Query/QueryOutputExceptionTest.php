<?php

declare(strict_types=1);

namespace Tests\Unit\Reporter\Query;

use App\Gql\Datum\IntegerDatum;
use App\Gql\Result\ResultColumn;
use App\Gql\Result\ResultRow;
use App\Gql\Result\ResultTable;
use App\Reporter\Diagram\Diagram;
use App\Reporter\Query\DotWriter;
use App\Reporter\Query\QueryOutputException;
use App\Reporter\Query\ResultElements;
use App\Reporter\Query\TableWriter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Output\BufferedOutput;

/**
 * @internal
 */
#[CoversClass(QueryOutputException::class)]
#[UsesClass(DotWriter::class)]
#[UsesClass(TableWriter::class)]
#[UsesClass(ResultElements::class)]
#[UsesClass(Diagram::class)]
#[UsesClass(IntegerDatum::class)]
#[UsesClass(ResultColumn::class)]
#[UsesClass(ResultRow::class)]
#[UsesClass(ResultTable::class)]
#[Small]
final class QueryOutputExceptionTest extends TestCase
{
    public function testAnOutputFailureLeavesTheResultAvailableForAnotherFormat(): void
    {
        $result = new ResultTable([new ResultColumn('n', 'INT64')], [new ResultRow([new IntegerDatum(1)])]);
        $output = new BufferedOutput();

        try {
            (new DotWriter())->report($result, $output);
            self::fail('A scalar result cannot be drawn as a digraph.');
        } catch (QueryOutputException) {
            self::assertSame('', $output->fetch());
            (new TableWriter())->report($result, $output);
        }

        self::assertSame("+-----------+\n| n (INT64) |\n+-----------+\n| 1         |\n+-----------+\n", $output->fetch());
    }
}
