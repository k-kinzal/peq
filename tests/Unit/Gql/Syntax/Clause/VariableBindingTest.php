<?php

declare(strict_types=1);

namespace Tests\Unit\Gql\Syntax\Clause;

use App\Gql\Syntax\Clause\VariableBinding;
use App\Gql\Syntax\Expression\VariableExpression;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(VariableBinding::class)]
#[UsesClass(VariableExpression::class)]
#[Small]
final class VariableBindingTest extends TestCase
{
    public function testABindingCarriesTheNameItGivesAndWhatItGivesItTo(): void
    {
        $value = new VariableExpression('p');
        $binding = new VariableBinding('found', $value);

        self::assertSame('found', $binding->name);
        self::assertSame($value, $binding->value);
    }
}
