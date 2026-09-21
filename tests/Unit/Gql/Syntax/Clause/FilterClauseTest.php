<?php

declare(strict_types=1);

namespace Tests\Unit\Gql\Syntax\Clause;

use App\Gql\Syntax\Clause\FilterClause;
use App\Gql\Syntax\Expression\VariableExpression;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(FilterClause::class)]
#[UsesClass(VariableExpression::class)]
#[Small]
final class FilterClauseTest extends TestCase
{
    public function testAFilterCarriesWhatMustHoldOfARowForItToBeKept(): void
    {
        $predicate = new VariableExpression('found');

        self::assertSame($predicate, (new FilterClause($predicate))->predicate);
    }
}
