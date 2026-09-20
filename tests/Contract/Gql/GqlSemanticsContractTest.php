<?php

declare(strict_types=1);

namespace Tests\Contract\Gql;

use App\Gql\Execution\QueryExecution;
use App\Gql\GqlException;
use App\Gql\StatusCode;
use Generator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Tests\Fixture\Gql\AnsweredQuery;
use Tests\Fixture\Gql\ExpressionSpelling;
use Tests\Fixture\Gql\MatchedPattern;
use Tests\Fixture\Gql\SampleGraph;

/**
 * @internal
 */
#[CoversClass(QueryExecution::class)]
#[Medium]
final class GqlSemanticsContractTest extends TestCase
{
    /**
     * @throws GqlException
     */
    #[DataProvider('providerTheThreeValuedComparisonTable')]
    #[Test]
    public function testComparisonFollowsTheThreeValuedTableTheDocumentationPrints(string $written, string $expected): void
    {
        self::assertSame($expected, AnsweredQuery::of('RETURN '.$written.' AS answer'));
    }

    /**
     * @return Generator<string, array{string, string}>
     */
    public static function providerTheThreeValuedComparisonTable(): Generator
    {
        yield '5 = 5 is TRUE' => ['5 = 5', '[answer:BOOL] TRUE'];

        yield '5 = 3 is FALSE' => ['5 = 3', '[answer:BOOL] FALSE'];

        yield '5 = NULL is UNKNOWN' => ['5 = NULL', '[answer:NULL] NULL'];

        yield 'NULL = NULL is UNKNOWN' => ['NULL = NULL', '[answer:NULL] NULL'];

        yield 'NOT UNKNOWN is UNKNOWN' => ['NOT (5 = NULL)', '[answer:NULL] NULL'];
    }

    /**
     * @throws GqlException
     */
    #[DataProvider('providerTheOperatorPrecedenceTheDocumentationPrints')]
    #[Test]
    public function testOperatorsBindInTheOrderTheDocumentationPrints(string $written, string $grouped): void
    {
        self::assertSame($grouped, ExpressionSpelling::of($written));
    }

    /**
     * @return Generator<string, array{string, string}>
     */
    public static function providerTheOperatorPrecedenceTheDocumentationPrints(): Generator
    {
        yield 'property access binds tighter than a sign' => ['-a.b', '(- a.b)'];

        yield 'multiplication binds tighter than addition' => ['a + b * c', '(a + (b * c))'];

        yield 'addition binds tighter than comparison' => ['a = b + c', '(a = (b + c))'];

        yield 'comparison binds tighter than negation' => ['NOT a = b', '(NOT (a = b))'];

        yield 'negation binds tighter than conjunction' => ['NOT a AND b', '((NOT a) AND b)'];

        yield 'conjunction binds tighter than exclusive disjunction' => ['a AND b XOR c', '((a AND b) XOR c)'];

        yield 'exclusive disjunction binds tighter than disjunction' => ['a XOR b OR c', '((a XOR b) OR c)'];

        yield 'the documentation writes this one out' => ['NOT a OR b', '((NOT a) OR b)'];
    }

    /**
     * @throws GqlException
     */
    #[DataProvider('providerTheAggregationRulesTheDocumentationStates')]
    #[Test]
    public function testAggregationFollowsTheRulesTheDocumentationStates(string $query, string $expected): void
    {
        self::assertSame($expected, AnsweredQuery::of($query));
    }

    /**
     * @return Generator<string, array{string, string}>
     */
    public static function providerTheAggregationRulesTheDocumentationStates(): Generator
    {
        yield 'a summary passes over the values that are not there' => [
            'MATCH (p) RETURN count(p.visibility) AS named',
            '[named:INT64] 4',
        ];

        yield 'a count over rows counts them whatever they carry' => [
            'MATCH (p) RETURN count(*) AS rows',
            '[rows:INT64] 9',
        ];

        yield 'a summary of nothing material is nothing' => [
            'MATCH (p:Interface) RETURN sum(p.line) AS total',
            '[total:NULL] NULL',
        ];

        yield 'counting nothing is zero, which is the exception the documentation names' => [
            'MATCH (p:Interface) RETURN count(*) AS rows',
            '[rows:INT64] 0',
        ];

        yield 'a different default is asked for the way the documentation asks for it' => [
            'MATCH (p:Interface) RETURN coalesce(sum(p.line), 0) AS total',
            '[total:INT64] 0',
        ];
    }

    /**
     * @throws GqlException
     */
    #[Test]
    public function testHorizontalAggregationTakesPrecedenceOverVerticalAggregation(): void
    {
        self::assertSame(
            '[a:STRING, oldest:INT64] show, 14; store, 14',
            AnsweredQuery::of('MATCH (a:Method)-[e:methodCall]->{2}(b:Method) RETURN a.name AS a, min(e.line) AS oldest ORDER BY a'),
        );
    }

    /**
     * @throws GqlException
     */
    #[Test]
    public function testAHorizontalSummaryCanBeSummarisedVerticallyAfterwards(): void
    {
        self::assertSame(
            '[a:STRING, walks:INT64, avgOldest:FLOAT64] show, 2, 18.0; store, 2, 23.0; total, 1, 14.0',
            AnsweredQuery::of(
                'MATCH (a:Method)-[e:methodCall]->{1,2}(b:Method)'
                .' RETURN a.name AS a, count(*) AS walks, avg(min(e.line)) AS avgOldest GROUP BY a ORDER BY a',
            ),
        );
    }

    /**
     * @throws GqlException
     */
    #[Test]
    public function testAGroupListBecomesAnOrdinaryListTheWayTheDocumentationSays(): void
    {
        self::assertSame(
            '[a:STRING, n:INT64] show, 2; store, 2',
            AnsweredQuery::of('MATCH (a:Method)-[e:methodCall]->{2}(b) RETURN a.name AS a, size(collect_list(e)) AS n ORDER BY a'),
        );
    }

    /**
     * @throws GqlException
     */
    #[Test]
    public function testAnEdgeVariableIsOneRelationWhileThePatternIsBeingMatched(): void
    {
        self::assertSame(
            '[a:STRING, hops:INT64] total, 1',
            AnsweredQuery::of('MATCH (a:Method)-[e:methodCall WHERE e.line < 20]->{1,3}(b:Method) RETURN a.name AS a, size(e) AS hops'),
        );
    }

    /**
     * @throws GqlException
     */
    #[Test]
    public function testAFilterKeepsOnlyTheRowsItsPredicateIsTrueOf(): void
    {
        self::assertSame('[id:NULL] ', AnsweredQuery::of('MATCH (p:Unresolved) FILTER p.line > 0 RETURN p.id AS id'));
    }

    /**
     * @throws GqlException
     */
    #[Test]
    public function testNothingIsTheSmallestValueAnOrderingCompares(): void
    {
        self::assertSame(
            '[name:STRING] Controller; Invoice; Kernel',
            AnsweredQuery::of('MATCH (p:ClassLike) RETURN p.name AS name ORDER BY p.visibility, name LIMIT 3'),
        );
    }

    /**
     * @throws GqlException
     */
    #[DataProvider('providerTheNumberRulesTheDocumentationStates')]
    #[Test]
    public function testNumbersCoerceTheWayTheDocumentationStates(string $written, string $expected): void
    {
        self::assertSame($expected, AnsweredQuery::of('RETURN '.$written.' AS n'));
    }

    /**
     * @return Generator<string, array{string, string}>
     */
    public static function providerTheNumberRulesTheDocumentationStates(): Generator
    {
        yield 'a whole division stays whole, which is what makes a birth year one' => [
            '19990101 / 10000',
            '[n:INT64] 1999',
        ];

        yield 'an expression that meets an approximate number produces one' => ['7 / 2.0', '[n:FLOAT64] 3.5'];

        yield 'and so does one that meets it on the left' => ['1.5 + 1', '[n:FLOAT64] 2.5'];
    }

    /**
     * @throws GqlException
     */
    #[Test]
    public function testAListIsIndexedFromZero(): void
    {
        self::assertSame('[first:INT64] 10', AnsweredQuery::of('RETURN [10, 20, 30][0] AS first'));
    }

    #[Test]
    public function testNamesInOneLetClauseCannotSeeEachOther(): void
    {
        self::assertSame(
            StatusCode::InvalidReference,
            AnsweredQuery::statusOf("MATCH (p:Method) LET name = p.name, greeting = 'Hello, ' || name RETURN greeting"),
        );
    }

    /**
     * @throws GqlException
     */
    #[Test]
    public function testAnOptionalMatchKeepsTheRowItFoundNothingFor(): void
    {
        self::assertSame(
            '[name:STRING, parent:STRING] Controller, Kernel; Invoice, NULL; Kernel, NULL; Store, NULL',
            AnsweredQuery::of('MATCH (c:Class) OPTIONAL MATCH (c)-[:extends]->(p) RETURN c.name AS name, p.name AS parent ORDER BY name'),
        );
    }

    /**
     * @throws GqlException
     */
    #[Test]
    public function testAPatternMatchesNoEdgeTwiceUnlessItIsToldOtherwise(): void
    {
        self::assertSame(
            MatchedPattern::of('TRAIL (a:Method)-[:methodCall]->{1,5}(b:Method)'),
            MatchedPattern::of('(a:Method)-[:methodCall]->{1,5}(b:Method)'),
        );
    }

    /**
     * @throws GqlException
     */
    #[DataProvider('providerThePathModesTheDocumentationDescribes')]
    #[Test]
    public function testEveryPathModeForbidsWhatTheDocumentationSaysItForbids(string $mode, int $matches): void
    {
        self::assertCount(
            $matches,
            MatchedPattern::rows($mode.' (a)-[:methodCall]->{1,6}(b)', [], 10, SampleGraph::recursive()),
        );
    }

    /**
     * @return Generator<string, array{string, int}>
     */
    public static function providerThePathModesTheDocumentationDescribes(): Generator
    {
        yield 'WALK allows repeated nodes and edges' => ['WALK', 53];

        yield 'TRAIL allows no repeated edge' => ['TRAIL', 16];

        yield 'SIMPLE allows no repeated node but the first and the last' => ['SIMPLE', 13];

        yield 'ACYCLIC allows no repeated node at all' => ['ACYCLIC', 7];
    }

    /**
     * @throws GqlException
     */
    #[Test]
    public function testSimpleAllowsThePathThatComesBackToWhereItStarted(): void
    {
        $cycles = MatchedPattern::of(
            "SIMPLE (a WHERE a.name = 'first')-[:methodCall]->{3}(b WHERE b.name = 'first')",
            [],
            10,
            SampleGraph::recursive(),
        );

        self::assertSame('a=Ring\Round::first b=Ring\Round::first', $cycles);
    }

    /**
     * @throws GqlException
     */
    #[Test]
    public function testAcyclicForbidsThePathThatComesBackToWhereItStarted(): void
    {
        $cycles = MatchedPattern::of(
            "ACYCLIC (a WHERE a.name = 'first')-[:methodCall]->{3}(b WHERE b.name = 'first')",
            [],
            10,
            SampleGraph::recursive(),
        );

        self::assertSame('', $cycles);
    }

    /**
     * @throws GqlException
     */
    #[Test]
    public function testALabelExpressionMeansWhatTheDocumentationSaysItMeans(): void
    {
        self::assertSame(
            '[name:STRING] Controller; Invoice; Kernel; Store',
            AnsweredQuery::of('MATCH (p:ClassLike&!Interface) RETURN p.name AS name ORDER BY name'),
        );
    }

    /**
     * @throws GqlException
     */
    #[Test]
    public function testANameWrittenTwiceInOnePatternJoinsThePatternToItself(): void
    {
        self::assertSame(
            '[owner:STRING] Controller',
            AnsweredQuery::of('MATCH (c:Class)-[:declaresMethod]->(a), (c)-[:declaresMethod]->(b) WHERE a.name <> b.name'
                .' RETURN DISTINCT c.name AS owner'),
        );
    }
}
