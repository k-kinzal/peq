<?php

declare(strict_types=1);

namespace Tests\Unit\Gql\Syntax\Expression;

use App\Gql\Syntax\Expression\VariableExpression;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(VariableExpression::class)]
#[Small]
final class VariableExpressionTest extends TestCase
{
    public function testAVariableCarriesTheNameTheQueryWrote(): void
    {
        self::assertSame('p', (new VariableExpression('p'))->name);
    }
}
