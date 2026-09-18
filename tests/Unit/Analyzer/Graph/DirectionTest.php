<?php

declare(strict_types=1);

namespace Tests\Unit\Analyzer\Graph;

use App\Analyzer\Graph\Direction;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
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

    #[DataProvider('providerUnknownDirections')]
    public function testAnUnknownDirectionResolvesToNothing(string $written): void
    {
        self::assertNull(Direction::tryFrom($written));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function providerUnknownDirections(): iterable
    {
        yield 'sideways' => ['sideways'];

        yield 'nothing written' => [''];

        yield 'USES' => ['USES'];

        yield 'used_by' => ['used_by'];
    }
}
