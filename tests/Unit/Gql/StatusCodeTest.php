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
    public function testCasesAreEveryConditionPeqReports(): void
    {
        self::assertSame(
            [
                StatusCode::Success,
                StatusCode::NoData,
                StatusCode::NumericValueOutOfRange,
                StatusCode::SubstringError,
                StatusCode::DivisionByZero,
                StatusCode::InvalidType,
                StatusCode::ValuesNotComparable,
                StatusCode::UnknownFeature,
                StatusCode::SyntaxError,
                StatusCode::InvalidReference,
            ],
            StatusCode::cases(),
        );
    }

    #[DataProvider('providerEveryCodeAndWhatItStandsFor')]
    public function testEveryCodeIsTheFiveCharactersTheStandardWritesIt(StatusCode $code, string $written, string $condition, bool $succeeded): void
    {
        self::assertSame($written, $code->value);
    }

    #[DataProvider('providerEveryCodeAndWhatItStandsFor')]
    public function testConditionNamesTheConditionInTheStandardsOwnWording(StatusCode $code, string $written, string $condition, bool $succeeded): void
    {
        self::assertSame($condition, $code->condition());
    }

    #[DataProvider('providerEveryCodeAndWhatItStandsFor')]
    public function testSucceededReportsOnlyTheCodesWhoseConditionIsANote(StatusCode $code, string $written, string $condition, bool $succeeded): void
    {
        self::assertSame($succeeded, $code->succeeded());
    }

    /**
     * @return iterable<string, array{StatusCode, string, string, bool}>
     */
    public static function providerEveryCodeAndWhatItStandsFor(): iterable
    {
        yield 'a query that found rows' => [StatusCode::Success, '00000', 'note: successful completion', true];

        yield 'a query that found none, which is an answer' => [StatusCode::NoData, '02000', 'note: no data', true];

        yield 'a number its type cannot hold' => [
            StatusCode::NumericValueOutOfRange,
            '22003',
            'error: data exception - numeric value out of range',
            false,
        ];

        yield 'a substring a string cannot have' => [StatusCode::SubstringError, '22011', 'error: data exception - substring error', false];

        yield 'a division by zero' => [StatusCode::DivisionByZero, '22012', 'error: data exception - division by zero', false];

        yield 'a value of the wrong type' => [StatusCode::InvalidType, '22G03', 'error: data exception - invalid value type', false];

        yield 'two values with no order between them' => [
            StatusCode::ValuesNotComparable,
            '22G04',
            'error: data exception - values not comparable',
            false,
        ];

        yield 'a feature GQL does not define here' => [StatusCode::UnknownFeature, '42000', 'error: syntax error or access rule violation', false];

        yield 'a query that could not be read' => [
            StatusCode::SyntaxError,
            '42001',
            'error: syntax error or access rule violation - invalid syntax',
            false,
        ];

        yield 'a name nothing bound' => [
            StatusCode::InvalidReference,
            '42002',
            'error: syntax error or access rule violation - invalid reference',
            false,
        ];
    }
}
