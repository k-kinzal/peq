<?php

declare(strict_types=1);

namespace Tests\Unit\Gql;

use App\Gql\StatusCode;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(StatusCode::class)]
#[Small]
final class StatusCodeTest extends TestCase
{
    public function testEveryCodeIsFiveCharactersLongTheWayTheStandardWritesThem(): void
    {
        $lengths = array_map(static fn (StatusCode $code): int => strlen($code->value), StatusCode::cases());

        self::assertSame([5], array_values(array_unique($lengths)));
    }

    #[DataProvider('providerEveryCode')]
    public function testConditionNamesTheConditionTheCodeStandsFor(StatusCode $code): void
    {
        self::assertNotSame('', $code->condition());
    }

    /**
     * @return iterable<string, array{StatusCode}>
     */
    public static function providerEveryCode(): iterable
    {
        foreach (StatusCode::cases() as $code) {
            yield $code->value => [$code];
        }
    }

    public function testConditionUsesTheStandardsOwnWordingForASyntaxError(): void
    {
        self::assertSame(
            'error: syntax error or access rule violation - invalid syntax',
            StatusCode::SyntaxError->condition(),
        );
    }

    public function testConditionSaysAQueryThatFoundNothingIsANote(): void
    {
        self::assertSame('note: no data', StatusCode::NoData->condition());
    }

    #[DataProvider('providerCodesThatSucceeded')]
    public function testSucceededReportsACodeInASuccessClass(StatusCode $code): void
    {
        self::assertTrue($code->succeeded());
    }

    /**
     * @return iterable<string, array{StatusCode}>
     */
    public static function providerCodesThatSucceeded(): iterable
    {
        yield 'found rows' => [StatusCode::Success];

        yield 'found none, which is an answer' => [StatusCode::NoData];
    }

    #[DataProvider('providerCodesThatFailed')]
    public function testSucceededReportsACodeThatIsNotInOne(StatusCode $code): void
    {
        self::assertFalse($code->succeeded());
    }

    /**
     * @return iterable<string, array{StatusCode}>
     */
    public static function providerCodesThatFailed(): iterable
    {
        yield 'a division by zero' => [StatusCode::DivisionByZero];

        yield 'a value of the wrong type' => [StatusCode::InvalidType];

        yield 'a query that could not be read' => [StatusCode::SyntaxError];

        yield 'a name nothing bound' => [StatusCode::InvalidReference];

        yield 'a feature that does not exist' => [StatusCode::UnknownFeature];
    }

    public function testACodeThatSucceededIsOneWhoseConditionIsANote(): void
    {
        $noted = array_filter(StatusCode::cases(), static fn (StatusCode $code): bool => $code->succeeded());

        self::assertSame(
            array_map(static fn (StatusCode $code): bool => true, $noted),
            array_map(static fn (StatusCode $code): bool => str_starts_with($code->condition(), 'note:'), $noted),
        );
    }
}
