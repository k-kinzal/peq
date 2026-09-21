<?php

declare(strict_types=1);

namespace Tests\Unit\Reporter\Diagram\Layout;

use App\Reporter\Diagram\Layout\Lane;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(Lane::class)]
#[Small]
final class LaneTest extends TestCase
{
    public function testSpanRunsFromTheHighestEndToTheLowest(): void
    {
        self::assertSame([0, 4], (new Lane(['a'], ['b', 'c']))->span(['a' => 2, 'b' => 0, 'c' => 4]));
    }

    public function testSpanRunsAsFarAsTheLinesArrowsComeOverAndGoOverOn(): void
    {
        self::assertSame([1, 5], (new Lane(['a'], ['b'], [5], [1]))->span(['a' => 2, 'b' => 4]));
    }

    public function testSpanOfALaneWhoseEndsShareALineIsThatLine(): void
    {
        self::assertSame([2, 2], (new Lane(['a'], ['b']))->span(['a' => 2, 'b' => 2]));
    }

    public function testSpanReachesAboveTheFirstLineWhereAnArrowComesOverThere(): void
    {
        self::assertSame([-1, 4], (new Lane([], ['b'], [-1]))->span(['b' => 4]));
    }

    public function testEntriesAreTheLinesOfThePlacesArrowsLeaveAndTheLinesTheyComeOverOn(): void
    {
        self::assertSame([0, 3], (new Lane(['a'], ['c'], [3]))->entries(['a' => 0, 'c' => 4]));
    }

    public function testEntriesAreInTheOrderThePlacesWereGiven(): void
    {
        self::assertSame([6, 2], (new Lane(['a', 'b'], ['c']))->entries(['a' => 6, 'b' => 2, 'c' => 4]));
    }

    public function testEntriesOfALaneEveryArrowComesOverToAreTheLinesTheyComeOverOn(): void
    {
        self::assertSame([1, -1], (new Lane([], ['c'], [1, -1]))->entries(['c' => 0]));
    }

    public function testExitsAreTheLinesOfThePlacesArrowsArriveAtAndTheLinesTheyGoOverOn(): void
    {
        self::assertSame([0, 3], (new Lane(['a'], ['b'], [], [3]))->exits(['a' => 1, 'b' => 0]));
    }

    public function testExitsOfALaneEveryArrowGoesOverFromAreTheLinesTheyGoOverOn(): void
    {
        self::assertSame([1, 3], (new Lane(['a'], [], [], [1, 3]))->exits(['a' => 0]));
    }
}
