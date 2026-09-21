<?php

declare(strict_types=1);

namespace Tests\Unit\Gql\Element;

use App\Analyzer\Graph\AuthoredEdge;
use App\Analyzer\Graph\Edge;
use App\Analyzer\Graph\Edge\Declaration\ExtendsEdge;
use App\Analyzer\Graph\Edge\Declaration\MethodEdge;
use App\Analyzer\Graph\Edge\Usage\MethodCallEdge;
use App\Analyzer\Graph\FileMeta;
use App\Gql\Datum\Datum;
use App\Gql\Datum\IntegerDatum;
use App\Gql\Datum\StringDatum;
use App\Gql\Element\EdgeProperties;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Tests\Fixture\Gql\SampleGraph;

/**
 * @internal
 */
#[CoversClass(EdgeProperties::class)]
#[UsesClass(AuthoredEdge::class)]
#[UsesClass(ExtendsEdge::class)]
#[UsesClass(MethodEdge::class)]
#[UsesClass(MethodCallEdge::class)]
#[UsesClass(IntegerDatum::class)]
#[UsesClass(StringDatum::class)]
#[Small]
final class EdgePropertiesTest extends TestCase
{
    /**
     * @param array<string, Datum> $expected
     */
    #[DataProvider('providerRelationsAndWhatAQueryCanAskThem')]
    public function testOfOffersEverythingAQueryCanAskARelation(Edge $edge, array $expected): void
    {
        self::assertEquals($expected, EdgeProperties::of($edge));
    }

    /**
     * @return iterable<string, array{Edge, array<string, Datum>}>
     */
    public static function providerRelationsAndWhatAQueryCanAskThem(): iterable
    {
        yield 'a call' => [
            new MethodCallEdge(SampleGraph::show(), SampleGraph::total(), new FileMeta('/project/src/Http/Controller.php', 22, 5)),
            [
                'kind' => new StringDatum('method-call'),
                'file' => new StringDatum('/project/src/Http/Controller.php'),
                'fileName' => new StringDatum('Controller.php'),
                'line' => new IntegerDatum(22),
                'column' => new IntegerDatum(5),
            ],
        ];

        yield 'a method a class declares' => [
            new MethodEdge(SampleGraph::invoice(), SampleGraph::total(), new FileMeta('/project/src/Domain/Invoice.php', 12, 5)),
            [
                'kind' => new StringDatum('declaration-method'),
                'file' => new StringDatum('/project/src/Domain/Invoice.php'),
                'fileName' => new StringDatum('Invoice.php'),
                'line' => new IntegerDatum(12),
                'column' => new IntegerDatum(5),
            ],
        ];

        yield 'a class extending another' => [
            new ExtendsEdge(SampleGraph::controller(), SampleGraph::kernel(), new FileMeta('/project/src/Http/Controller.php', 10, 9)),
            [
                'kind' => new StringDatum('declaration-extends'),
                'file' => new StringDatum('/project/src/Http/Controller.php'),
                'fileName' => new StringDatum('Controller.php'),
                'line' => new IntegerDatum(10),
                'column' => new IntegerDatum(9),
            ],
        ];
    }

    public function testAllNamesEveryPropertyARelationCarriesAndWhatKindOfValueItHolds(): void
    {
        self::assertSame(
            ['kind' => 'STRING', 'file' => 'STRING', 'fileName' => 'STRING', 'line' => 'INT64', 'column' => 'INT64'],
            EdgeProperties::all(),
        );
    }
}
