<?php

declare(strict_types=1);

namespace Tests\Unit\Gql\Syntax\Expression;

use App\Gql\Syntax\Expression\CallExpression;
use App\Gql\Syntax\Expression\VariableExpression;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(CallExpression::class)]
#[UsesClass(VariableExpression::class)]
#[Small]
final class CallExpressionTest extends TestCase
{
    public function testACallCarriesItsNameAndWhatItIsAppliedTo(): void
    {
        $argument = new VariableExpression('p');
        $call = new CallExpression('upper', [$argument]);

        self::assertSame('upper', $call->name);
        self::assertSame([$argument], $call->arguments);
    }

    public function testACallDropsNothingAndIsWrittenOverAValueUnlessItSaysOtherwise(): void
    {
        $call = new CallExpression('upper');

        self::assertFalse($call->distinct);
        self::assertFalse($call->star);
    }

    public function testACallCanSayThatItDropsRepeatedValues(): void
    {
        self::assertTrue((new CallExpression('count', [new VariableExpression('p')], true))->distinct);
    }

    public function testACallCanSayThatItWasWrittenOverRowsRatherThanOverAValue(): void
    {
        self::assertTrue((new CallExpression('count', [], false, true))->star);
    }
}
