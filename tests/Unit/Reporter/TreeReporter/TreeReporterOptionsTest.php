<?php

declare(strict_types=1);

namespace Tests\Unit\Reporter\TreeReporter;

use App\Reporter\TreeReporter\TreeReporterOptions;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
final class TreeReporterOptionsTest extends TestCase
{
    #[Test]
    public function testConstructWithDefault(): void
    {
        $options = new TreeReporterOptions();
        self::assertNull($options->level);
    }

    #[Test]
    public function testConstructWithLevel(): void
    {
        $options = new TreeReporterOptions(level: 5);
        self::assertSame(5, $options->level);
    }

    #[Test]
    public function testConstructThrowsExceptionForInvalidLevel(): void
    {
        $this->expectException(\AssertionError::class);
        new TreeReporterOptions(level: -1);
    }
}
