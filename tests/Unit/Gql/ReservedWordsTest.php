<?php

declare(strict_types=1);

namespace Tests\Unit\Gql;

use App\Gql\ReservedWords;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(ReservedWords::class)]
#[Small]
final class ReservedWordsTest extends TestCase
{
    #[DataProvider('providerWordsAndWhetherGqlReservesThem')]
    public function testReservesReportsWhetherGqlReservesAWord(string $word, bool $reserved): void
    {
        self::assertSame($reserved, ReservedWords::reserves($word));
    }

    /**
     * @return iterable<string, array{string, bool}>
     */
    public static function providerWordsAndWhetherGqlReservesThem(): iterable
    {
        yield 'a word the grammar uses' => ['MATCH', true];

        yield 'the same word in lower case' => ['match', true];

        yield 'the same word cased either way' => ['Match', true];

        yield 'a word held back for a later edition' => ['FUNCTION', true];

        yield 'a word a graph happens to use as a label' => ['Method', false];

        yield 'a word a graph happens to use as a property' => ['firstName', false];

        yield 'a word that only begins like a reserved one' => ['CALLABLE', false];
    }

    #[DataProvider('providerNamesAndHowAQueryWritesThem')]
    public function testAsWrittenWritesANameTheWayAQueryHasTo(string $name, string $written): void
    {
        self::assertSame($written, ReservedWords::asWritten($name));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function providerNamesAndHowAQueryWritesThem(): iterable
    {
        yield 'a name GQL leaves free' => ['Method', 'Method'];

        yield 'a name GQL reserves' => ['Function', '`Function`'];

        yield 'a property GQL reserves' => ['value', '`value`'];
    }

    public function testWordsHoldsBothProductionsTheStandardReserves(): void
    {
        self::assertCount(262, ReservedWords::WORDS);
    }

    public function testWordsIsWrittenInTheOrderTheGrammarWritesIt(): void
    {
        self::assertSame(['ABS', 'ACOS', 'ALL'], array_slice(ReservedWords::WORDS, 0, 3));
    }
}
