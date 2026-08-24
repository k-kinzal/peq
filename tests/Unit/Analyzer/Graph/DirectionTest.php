<?php

declare(strict_types=1);

namespace Tests\Unit\Analyzer\Graph;

use App\Analyzer\Graph\Direction;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(Direction::class)]
#[Small]
final class DirectionTest extends TestCase
{
    public function testCasesAreSpelledTheWayTheCommandLineSpellsThem(): void
    {
        self::assertSame('uses', Direction::Uses->value);
        self::assertSame('used-by', Direction::UsedBy->value);
    }

    public function testTheDirectionsAreTheOnlyTwoWaysToReadTheGraph(): void
    {
        self::assertSame([Direction::Uses, Direction::UsedBy], Direction::cases());
    }

    public function testAWrittenDirectionResolvesToItsCase(): void
    {
        self::assertSame(Direction::UsedBy, Direction::from('used-by'));
    }

    public function testAnUnknownDirectionResolvesToNothing(): void
    {
        self::assertNull(Direction::tryFrom('sideways'));
    }
}
