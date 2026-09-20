<?php

declare(strict_types=1);

namespace Tests\Contract\Gql;

use App\Gql\GqlException;
use App\Gql\StatusCode;
use Generator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Tests\Fixture\Gql\AnsweredQuery;
use Tests\Fixture\Gql\GqlConditions;

/**
 * @internal
 */
#[CoversClass(StatusCode::class)]
#[UsesClass(GqlException::class)]
#[Medium]
final class GqlStatusContractTest extends TestCase
{
    #[DataProvider('providerStatusesPeqReports')]
    #[Test]
    public function testEveryStatusPeqReportsIsOneTheStandardDefines(StatusCode $status): void
    {
        self::assertTrue(
            GqlConditions::defines($status->value),
            sprintf(
                'peq reports GQLSTATUS %s, and ISO/IEC 39075 defines no such condition. '
                .'A caller testing for it would be testing for something no GQL implementation reports.',
                $status->value,
            ),
        );
    }

    #[DataProvider('providerStatusesPeqReports')]
    #[Test]
    public function testEveryStatusIsReportedInTheStandardsOwnWording(StatusCode $status): void
    {
        self::assertSame(
            GqlConditions::conditionOf($status->value),
            $status->condition(),
            sprintf('peq words GQLSTATUS %s differently from the standard.', $status->value),
        );
    }

    /**
     * @return Generator<string, array{StatusCode}>
     */
    public static function providerStatusesPeqReports(): Generator
    {
        foreach (StatusCode::cases() as $status) {
            yield $status->value.' '.$status->name => [$status];
        }
    }

    #[DataProvider('providerQueriesThatReportEachStatus')]
    #[Test]
    public function testEveryStatusPeqDefinesIsOneAQueryCanActuallyProduce(StatusCode $status, string $query): void
    {
        self::assertSame($status, AnsweredQuery::statusOf($query));
    }

    /**
     * @return Generator<string, array{StatusCode, string}>
     */
    public static function providerQueriesThatReportEachStatus(): Generator
    {
        yield 'a query that found something' => [StatusCode::Success, 'MATCH (p:Method) RETURN p.name AS name'];

        yield 'a query that found nothing' => [StatusCode::NoData, 'MATCH (p:Interface) RETURN p.name AS name'];

        yield 'a query that divided by zero' => [StatusCode::DivisionByZero, 'RETURN 1 / 0 AS n'];

        yield 'a query that compared the wrong kinds' => [StatusCode::InvalidType, 'RETURN upper([1]) AS n'];

        yield 'a query that named something unbound' => [StatusCode::InvalidReference, 'RETURN nothingBoundThis AS n'];

        yield 'a query that could not be read' => [StatusCode::SyntaxError, 'MATCH (p) RETRUN p'];

        yield 'a query peq will not run' => [StatusCode::UnknownFeature, 'INSERT (p:Person)'];
    }
}
