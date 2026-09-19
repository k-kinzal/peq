<?php

declare(strict_types=1);

namespace Tests\Unit\Gql\Syntax\Expression;

use App\Gql\Syntax\Expression\CaseBranch;
use App\Gql\Syntax\Expression\VariableExpression;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(CaseBranch::class)]
#[UsesClass(VariableExpression::class)]
#[Small]
final class CaseBranchTest extends TestCase
{
    public function testABranchCarriesWhatHasToHoldAndWhatItIsWorthWhenItDoes(): void
    {
        $when = new VariableExpression('a');
        $then = new VariableExpression('b');
        $branch = new CaseBranch($when, $then);

        self::assertSame($when, $branch->when);
        self::assertSame($then, $branch->then);
    }
}
