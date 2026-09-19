<?php

declare(strict_types=1);

namespace Tests\Unit\Gql\Syntax\Pattern;

use App\Gql\Syntax\Expression\VariableExpression;
use App\Gql\Syntax\Pattern\ElementFilter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(ElementFilter::class)]
#[UsesClass(VariableExpression::class)]
#[Small]
final class ElementFilterTest extends TestCase
{
    public function testEmptyReportsAFilterThatRequiresNothing(): void
    {
        self::assertTrue((new ElementFilter())->empty());
    }

    public function testAFilterCarriesThePropertiesItRequires(): void
    {
        $expected = new VariableExpression('wanted');
        $filter = new ElementFilter(['name' => $expected]);

        self::assertSame(['name' => $expected], $filter->properties);
        self::assertFalse($filter->empty());
    }

    public function testAFilterCarriesThePredicateItRequires(): void
    {
        $predicate = new VariableExpression('found');
        $filter = new ElementFilter([], $predicate);

        self::assertSame($predicate, $filter->predicate);
        self::assertFalse($filter->empty());
    }
}
