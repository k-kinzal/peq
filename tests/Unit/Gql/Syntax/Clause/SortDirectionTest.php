<?php

declare(strict_types=1);

namespace Tests\Unit\Gql\Syntax\Clause;

use App\Gql\Syntax\Clause\SortDirection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(SortDirection::class)]
#[Small]
final class SortDirectionTest extends TestCase
{
    public function testThereAreOnlyTwoWaysToOrderRows(): void
    {
        self::assertSame([SortDirection::Ascending, SortDirection::Descending], SortDirection::cases());
    }

    public function testFactorLeavesAComparisonAloneWhenOrderingSmallestFirst(): void
    {
        self::assertSame(1, SortDirection::Ascending->factor());
    }

    public function testFactorTurnsAComparisonAroundWhenOrderingLargestFirst(): void
    {
        self::assertSame(-1, SortDirection::Descending->factor());
    }
}
