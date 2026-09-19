<?php

declare(strict_types=1);

namespace Tests\Unit\Gql\Syntax\Expression;

use App\Gql\Syntax\Expression\CaseBranch;
use App\Gql\Syntax\Expression\CaseExpression;
use App\Gql\Syntax\Expression\VariableExpression;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(CaseExpression::class)]
#[UsesClass(CaseBranch::class)]
#[UsesClass(VariableExpression::class)]
#[Small]
final class CaseExpressionTest extends TestCase
{
    #[DataProvider('providerOneBranch')]
    public function testAChoiceTriedBranchByBranchHasNoSubject(CaseBranch $branch): void
    {
        self::assertNull((new CaseExpression(null, [$branch]))->subject);
    }

    #[DataProvider('providerOneBranch')]
    public function testAChoiceAgainstOneValueCarriesItAsItsSubject(CaseBranch $branch): void
    {
        $subject = new VariableExpression('kind');

        self::assertSame($subject, (new CaseExpression($subject, [$branch]))->subject);
    }

    #[DataProvider('providerOneBranch')]
    public function testAChoiceCarriesItsBranchesInTheOrderTheyAreTried(CaseBranch $branch): void
    {
        self::assertSame([$branch], (new CaseExpression(null, [$branch]))->branches);
    }

    #[DataProvider('providerOneBranch')]
    public function testAChoiceWrittenWithoutAFallbackHasNone(CaseBranch $branch): void
    {
        self::assertNull((new CaseExpression(null, [$branch]))->otherwise);
    }

    #[DataProvider('providerOneBranch')]
    public function testAChoiceCarriesTheFallbackItWasGiven(CaseBranch $branch): void
    {
        $otherwise = new VariableExpression('rest');

        self::assertSame($otherwise, (new CaseExpression(null, [$branch], $otherwise))->otherwise);
    }

    /**
     * @return iterable<string, array{CaseBranch}>
     */
    public static function providerOneBranch(): iterable
    {
        yield 'a branch over names' => [new CaseBranch(new VariableExpression('a'), new VariableExpression('b'))];
    }
}
