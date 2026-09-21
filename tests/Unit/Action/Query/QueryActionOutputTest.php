<?php

declare(strict_types=1);

namespace Tests\Unit\Action\Query;

use App\Action\Query\QueryActionOutput;
use App\Gql\Binding\BindingRow;
use App\Gql\Binding\BindingTable;
use App\Gql\Datum\DatumKind;
use App\Gql\Datum\IntegerDatum;
use App\Gql\Element\ElementGraph;
use App\Gql\Result\ResultColumn;
use App\Gql\Result\ResultRow;
use App\Gql\Result\ResultTable;
use App\Gql\StatusCode;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(QueryActionOutput::class)]
#[UsesClass(BindingRow::class)]
#[UsesClass(BindingTable::class)]
#[UsesClass(DatumKind::class)]
#[UsesClass(IntegerDatum::class)]
#[UsesClass(ElementGraph::class)]
#[UsesClass(ResultColumn::class)]
#[UsesClass(ResultRow::class)]
#[UsesClass(ResultTable::class)]
#[UsesClass(StatusCode::class)]
#[Small]
final class QueryActionOutputTest extends TestCase
{
    public function testAnOutputCarriesWhatTheQueryAnswered(): void
    {
        $row = BindingRow::unit()->with('n', new IntegerDatum(1));
        $answered = ResultTable::of(['n'], new BindingTable([$row]));

        self::assertSame($answered, (new QueryActionOutput($answered, new ElementGraph([], [], [])))->result);
    }

    public function testAnOutputCarriesTheGraphTheAnswerCameFrom(): void
    {
        $graph = new ElementGraph([], [], []);

        self::assertSame($graph, (new QueryActionOutput(ResultTable::nothing(), $graph))->graph);
    }
}
