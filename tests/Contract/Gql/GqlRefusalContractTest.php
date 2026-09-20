<?php

declare(strict_types=1);

namespace Tests\Contract\Gql;

use App\Gql\GqlException;
use App\Gql\Parsing\Parser;
use App\Gql\Parsing\StatementRefusal;
use App\Gql\StatusCode;
use Generator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Tests\Fixture\Gql\AnsweredQuery;
use Tests\Fixture\Gql\GqlReservedWords;

/**
 * @internal
 */
#[CoversClass(StatementRefusal::class)]
#[UsesClass(Parser::class)]
#[Medium]
final class GqlRefusalContractTest extends TestCase
{
    #[DataProvider('providerStatementsPeqDoesNotRun')]
    #[Test]
    public function testEveryStatementPeqDoesNotRunIsRefusedUnderTheStatusForOneItWillNotRun(string $keyword): void
    {
        self::assertSame(StatusCode::UnknownFeature, AnsweredQuery::statusOf($keyword.' (p)'));
    }

    #[DataProvider('providerStatementsPeqDoesNotRun')]
    #[Test]
    public function testEveryStatementPeqDoesNotRunIsRefusedWhereverItStands(string $keyword): void
    {
        self::assertSame(StatusCode::UnknownFeature, AnsweredQuery::statusOf('MATCH (p) '.$keyword.' (p)'));
    }

    #[DataProvider('providerStatementsPeqDoesNotRun')]
    #[Test]
    public function testEveryStatementPeqDoesNotRunIsOneGqlActuallyDefines(string $keyword): void
    {
        self::assertTrue(
            GqlReservedWords::knows($keyword),
            sprintf('peq refuses "%s" by name, and GQL defines no such statement to refuse.', $keyword),
        );
    }

    /**
     * @throws GqlException
     */
    #[DataProvider('providerStatementsPeqDoesNotRun')]
    #[Test]
    public function testEveryStatementPeqDoesNotRunSaysWhatItWouldHaveDone(string $keyword): void
    {
        $this->expectException(GqlException::class);
        $this->expectExceptionMessage(StatementRefusal::all()[$keyword]);

        Parser::read($keyword.' (p)');
    }

    /**
     * @return Generator<string, array{string}>
     */
    public static function providerStatementsPeqDoesNotRun(): Generator
    {
        foreach (array_keys(StatementRefusal::all()) as $keyword) {
            yield $keyword => [$keyword];
        }
    }

    #[Test]
    public function testAWordThatBeginsNoStatementAtAllIsStillAReadingFailure(): void
    {
        self::assertSame(StatusCode::SyntaxError, AnsweredQuery::statusOf('banana (p)'));
    }
}
