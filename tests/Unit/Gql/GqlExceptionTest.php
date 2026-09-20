<?php

declare(strict_types=1);

namespace Tests\Unit\Gql;

use App\Gql\GqlException;
use App\Gql\StatusCode;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(GqlException::class)]
#[UsesClass(StatusCode::class)]
#[Small]
final class GqlExceptionTest extends TestCase
{
    public function testAConditionCarriesTheStatusItIsReportedUnder(): void
    {
        $reported = new GqlException(StatusCode::InvalidType, 'a number was expected');

        self::assertSame(StatusCode::InvalidType, $reported->status);
        self::assertSame('a number was expected', $reported->reason);
    }

    public function testAConditionAboutTheWholeQueryIsAboutNoPlaceInIt(): void
    {
        $reported = new GqlException(StatusCode::InvalidType, 'a number was expected');

        self::assertNull($reported->queryLine);
        self::assertNull($reported->queryColumn);
        self::assertNull($reported->context);
    }

    public function testSyntaxReportsTheQueryWrongWhereItWentWrong(): void
    {
        $reported = GqlException::syntax('expected a pattern', 3, 7, '"RETRUN"');

        self::assertSame(StatusCode::SyntaxError, $reported->status);
        self::assertSame(3, $reported->queryLine);
        self::assertSame(7, $reported->queryColumn);
        self::assertSame('"RETRUN"', $reported->context);
    }

    public function testBecauseReportsAConditionThatIsAboutNoPlaceInTheQuery(): void
    {
        $reported = GqlException::because(StatusCode::InvalidReference, 'nothing binds "p" here');

        self::assertSame(StatusCode::InvalidReference, $reported->status);
        self::assertNull($reported->queryLine);
    }

    public function testDescribeWritesTheCodeFirstBecauseThatIsThePartThatIsPromised(): void
    {
        self::assertSame(
            '[42002] error: syntax error or access rule violation - invalid reference: nothing binds "p" here',
            GqlException::describe(StatusCode::InvalidReference, 'nothing binds "p" here'),
        );
    }

    public function testDescribeSaysWhereAConditionAboutAPlaceHappened(): void
    {
        self::assertSame(
            '[42001] error: syntax error or access rule violation - invalid syntax: '
            .'expected a pattern at line 3, column 7 (found "RETRUN")',
            GqlException::describe(StatusCode::SyntaxError, 'expected a pattern', 3, 7, '"RETRUN"'),
        );
    }

    public function testTheMessageOfAConditionIsHowItIsDescribed(): void
    {
        $reported = GqlException::syntax('expected a pattern', 3, 7, '"RETRUN"');

        self::assertSame(
            GqlException::describe(StatusCode::SyntaxError, 'expected a pattern', 3, 7, '"RETRUN"'),
            $reported->getMessage(),
        );
    }
}
