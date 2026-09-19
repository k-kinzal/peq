<?php

declare(strict_types=1);

namespace Tests\Unit\Gql\Syntax\Pattern;

use App\Gql\Syntax\Pattern\EdgeDirection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(EdgeDirection::class)]
#[Small]
final class EdgeDirectionTest extends TestCase
{
    public function testThereAreOnlyThreeWaysToCrossARelation(): void
    {
        self::assertSame(
            [EdgeDirection::Along, EdgeDirection::Against, EdgeDirection::Either],
            EdgeDirection::cases(),
        );
    }

    #[DataProvider('providerDirectionsAndHowTheyAreWritten')]
    public function testSpellingWritesTheEdgePatternTheWayAQueryWritesIt(EdgeDirection $direction, string $inside, string $written): void
    {
        self::assertSame($written, $direction->spelling($inside));
    }

    /**
     * @return iterable<string, array{EdgeDirection, string, string}>
     */
    public static function providerDirectionsAndHowTheyAreWritten(): iterable
    {
        yield 'following the relation' => [EdgeDirection::Along, ':calls', '-[:calls]->'];

        yield 'reading it backwards' => [EdgeDirection::Against, ':calls', '<-[:calls]-'];

        yield 'not caring which way' => [EdgeDirection::Either, '', '-[]-'];
    }
}
