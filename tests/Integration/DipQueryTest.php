<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Analyzer\Analyzer;
use App\Analyzer\NativeAnalyzer\NativeAnalyzer;
use App\Analyzer\PhpStanAnalyzer\PhpStanAnalyzer;
use App\Gql\Element\GraphProjection;
use App\Gql\Execution\QueryExecution;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(QueryExecution::class)]
#[Large]
final class DipQueryTest extends TestCase
{
    /**
     * @throws \App\Gql\GqlException
     */
    #[DataProvider('providerAnalyzers')]
    public function testQueryFindsPdoUsersAndTheirRepeatedAttributesThroughInterfaceCalls(Analyzer $analyzer): void
    {
        $graph = $analyzer->analyze(dirname(__DIR__).'/Fixture/Source/Dip.php');
        $query = new QueryExecution(GraphProjection::of($graph));

        $answer = $query->query('MATCH TRAIL (c:Method WHERE c.name = "action")-[:`call`|possibleCall]->{0,}(m:Method)-[:methodCall]->(p:Method WHERE p.owner = "PDO") MATCH (m)-[a:attribute]->(t) WHERE a.`parameter` IS NULL RETURN DISTINCT m.id, t.id, a.arguments');

        self::assertSame([
            ['Tests\Fixture\Source\Dip\BaseService::execute', 'Tests\Fixture\Source\Dip\Trace', "['query']"],
            ['Tests\Fixture\Source\Dip\BaseService::execute', 'Tests\Fixture\Source\Dip\Trace', "[operation: 'audit']"],
        ], array_map(static fn ($row): array => array_map(static fn ($value): string => $value->toText(), $row->values), $answer->rows));
    }

    /**
     * @throws \App\Gql\GqlException
     */
    #[DataProvider('providerAnalyzers')]
    public function testQueryRetainsTheCallSiteReceiverAndTheImplementingClass(Analyzer $analyzer): void
    {
        $query = new QueryExecution(GraphProjection::of($analyzer->analyze(dirname(__DIR__).'/Fixture/Source/Dip.php')));

        $answer = $query->query('MATCH (c:Method WHERE c.name = "action")-[e:possibleCall]->(body) RETURN DISTINCT e.resolution, e.receiverType, e.declaredTarget, e.implementationType, body.id, e.basis');

        self::assertSame([[
            'possible', 'Tests\Fixture\Source\Dip\Specialized', 'Tests\Fixture\Source\Dip\Port::execute',
            'Tests\Fixture\Source\Dip\Service', 'Tests\Fixture\Source\Dip\BaseService::execute', 'class-hierarchy',
        ]], array_map(static fn ($row): array => array_map(static fn ($value): string => $value->toText(), $row->values), $answer->rows));
    }

    /**
     * @throws \App\Gql\GqlException
     */
    #[DataProvider('providerAnalyzers')]
    public function testQueryDistinguishesMethodAndParameterAttributes(Analyzer $analyzer): void
    {
        $query = new QueryExecution(GraphProjection::of($analyzer->analyze(dirname(__DIR__).'/Fixture/Source/Dip.php')));

        $answer = $query->query('MATCH (f:Callable)-[a:attribute]->(t) WHERE a.`parameter` IS NOT NULL RETURN f.name, a.`parameter`, a.arguments');

        self::assertSame([['invoke', 'port', "['parameter']"]], array_map(static fn ($row): array => array_map(static fn ($value): string => $value->toText(), $row->values), $answer->rows));
    }

    /**
     * @return array<string, array{Analyzer}>
     */
    public static function providerAnalyzers(): array
    {
        return ['native' => [new NativeAnalyzer()], 'phpstan' => [new PhpStanAnalyzer()]];
    }
}
