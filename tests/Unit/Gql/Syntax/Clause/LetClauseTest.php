<?php

declare(strict_types=1);

namespace Tests\Unit\Gql\Syntax\Clause;

use App\Gql\Syntax\Clause\LetClause;
use App\Gql\Syntax\Clause\VariableBinding;
use App\Gql\Syntax\Expression\VariableExpression;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(LetClause::class)]
#[UsesClass(VariableBinding::class)]
#[UsesClass(VariableExpression::class)]
#[Small]
final class LetClauseTest extends TestCase
{
    public function testANamingCarriesEveryNameItGives(): void
    {
        $binding = new VariableBinding('found', new VariableExpression('p'));

        self::assertSame([$binding], (new LetClause([$binding]))->bindings);
    }
}
