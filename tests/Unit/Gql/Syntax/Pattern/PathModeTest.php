<?php

declare(strict_types=1);

namespace Tests\Unit\Gql\Syntax\Pattern;

use App\Gql\Syntax\Pattern\PathMode;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(PathMode::class)]
#[Small]
final class PathModeTest extends TestCase
{
    public function testTheModesAreTheFourGqlDefines(): void
    {
        self::assertSame(
            [PathMode::Walk, PathMode::Trail, PathMode::Simple, PathMode::Acyclic],
            PathMode::cases(),
        );
    }

    #[DataProvider('providerModesAndHowTheyAreWritten')]
    public function testAModeIsWrittenTheWayAQueryWritesIt(PathMode $mode, string $written): void
    {
        self::assertSame($written, $mode->value);
    }

    /**
     * @return iterable<string, array{PathMode, string}>
     */
    public static function providerModesAndHowTheyAreWritten(): iterable
    {
        yield 'restricting nothing' => [PathMode::Walk, 'WALK'];

        yield 'crossing no relation twice' => [PathMode::Trail, 'TRAIL'];

        yield 'meeting no symbol twice, except where it started' => [PathMode::Simple, 'SIMPLE'];

        yield 'meeting no symbol twice at all' => [PathMode::Acyclic, 'ACYCLIC'];
    }
}
