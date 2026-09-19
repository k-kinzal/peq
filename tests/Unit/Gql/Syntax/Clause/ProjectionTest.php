<?php

declare(strict_types=1);

namespace Tests\Unit\Gql\Syntax\Clause;

use App\Gql\Syntax\Clause\Projection;
use App\Gql\Syntax\Expression\VariableExpression;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(Projection::class)]
#[UsesClass(VariableExpression::class)]
#[Small]
final class ProjectionTest extends TestCase
{
    public function testAColumnCarriesWhatItHoldsAndWhatProducedIt(): void
    {
        $value = new VariableExpression('p');
        $column = new Projection($value, 'person', 'p');

        self::assertSame($value, $column->value);
        self::assertSame('person', $column->alias);
        self::assertSame('p', $column->written);
    }

    public function testHeadingIsTheNameAColumnWasGiven(): void
    {
        self::assertSame('person', (new Projection(new VariableExpression('p'), 'person', 'p'))->heading());
    }

    public function testHeadingOfAnUnnamedColumnIsWhatProducedIt(): void
    {
        self::assertSame('p.firstName', (new Projection(new VariableExpression('p'), null, 'p.firstName'))->heading());
    }
}
