<?php

declare(strict_types=1);

namespace Tests\Contract\Gql;

use App\Gql\Element\EdgeProperties;
use App\Gql\Element\NodeProperties;
use App\Gql\GqlException;
use App\Gql\Invocation\AggregateCatalog;
use App\Gql\Invocation\FunctionCatalog;
use App\Gql\Parsing\Parser;
use Generator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Tests\Fixture\Gql\GqlReservedWords;
use Tests\Fixture\Gql\QueryVocabulary;

/**
 * @internal
 */
#[CoversClass(FunctionCatalog::class)]
#[UsesClass(AggregateCatalog::class)]
#[UsesClass(NodeProperties::class)]
#[UsesClass(EdgeProperties::class)]
#[UsesClass(Parser::class)]
#[Medium]
final class GqlVocabularyContractTest extends TestCase
{
    /**
     * @param list<string> $places
     */
    #[DataProvider('providerWordsTheEngineGivesAMeaningTo')]
    #[Test]
    public function testEveryWordTheEngineGivesAMeaningToIsOneGqlDefines(string $word, array $places): void
    {
        self::assertTrue(
            GqlReservedWords::knows($word),
            sprintf(
                '"%s" is written in %s, and GQL neither reserves it nor documents it. '
                .'A word of peq\'s own makes the query language a dialect rather than the one its readers know.',
                $word,
                implode(', ', $places),
            ),
        );
    }

    /**
     * @return Generator<string, array{string, list<string>}>
     */
    public static function providerWordsTheEngineGivesAMeaningTo(): Generator
    {
        yield from QueryVocabulary::words();
    }

    #[DataProvider('providerFunctionsPeqOffers')]
    #[Test]
    public function testEveryFunctionPeqOffersIsOneGqlReserves(string $name): void
    {
        self::assertTrue(
            GqlReservedWords::knows(strtoupper($name)),
            sprintf('peq offers a function called "%s", and GQL reserves no such name.', $name),
        );
    }

    /**
     * @return Generator<string, array{string}>
     */
    public static function providerFunctionsPeqOffers(): Generator
    {
        foreach ([...FunctionCatalog::all(), ...AggregateCatalog::all()] as $name) {
            yield $name => [$name];
        }
    }

    #[DataProvider('providerTypeNamesPeqReports')]
    #[Test]
    public function testEveryTypeNamePeqReportsIsOneGqlReserves(string $type): void
    {
        self::assertTrue(
            GqlReservedWords::knows($type),
            sprintf('peq reports a column type called "%s", and GQL reserves no such name.', $type),
        );
    }

    /**
     * @return Generator<string, array{string}>
     */
    public static function providerTypeNamesPeqReports(): Generator
    {
        $named = [];
        foreach ([...array_values(NodeProperties::all()), ...array_values(EdgeProperties::all())] as $type) {
            $parts = preg_split('/[<>]/', $type, -1, PREG_SPLIT_NO_EMPTY);
            foreach ($parts === false ? [] : $parts as $part) {
                $named[$part] = true;
            }
        }

        foreach (array_keys($named) as $type) {
            yield $type => [$type];
        }
    }

    /**
     * @throws GqlException
     */
    #[Test]
    public function testInequalityIsSpelledTheWayGqlSpellsIt(): void
    {
        self::assertNotSame([], Parser::read('RETURN 1 <> 2 AS differ')->blocks);
    }

    /**
     * @throws GqlException
     */
    #[Test]
    public function testInequalityIsNotAlsoSpelledTheWayOtherLanguagesSpellIt(): void
    {
        $this->expectException(GqlException::class);
        $this->expectExceptionMessage('invalid syntax');

        Parser::read('RETURN 1 != 2 AS differ');
    }

    /**
     * @throws GqlException
     */
    #[Test]
    public function testALabelThatSpellsAReservedWordCanBeWrittenInBackticksAsGqlSaysItShould(): void
    {
        self::assertNotSame([], Parser::read('MATCH (p:`Function`) RETURN p')->blocks);
    }
}
