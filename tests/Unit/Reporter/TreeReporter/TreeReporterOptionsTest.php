<?php

declare(strict_types=1);

namespace Tests\Unit\Reporter\TreeReporter;

use App\Reporter\TreeReporter\TreeReporterOptions;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(TreeReporterOptions::class)]
#[Small]
final class TreeReporterOptionsTest extends TestCase
{
    public function testTheWholeTreeIsPrintedUnlessALevelIsGiven(): void
    {
        self::assertNull((new TreeReporterOptions())->level);
    }

    public function testALevelBoundIsKeptAsItWasGiven(): void
    {
        self::assertSame(3, (new TreeReporterOptions(3))->level);
    }

    public function testTheSmallestUsefulBoundIsOneLevelBelowTheRoot(): void
    {
        self::assertSame(1, (new TreeReporterOptions(1))->level);
    }
}
