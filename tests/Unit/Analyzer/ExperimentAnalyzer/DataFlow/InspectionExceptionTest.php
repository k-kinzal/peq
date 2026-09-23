<?php

declare(strict_types=1);

namespace Tests\Unit\Analyzer\ExperimentAnalyzer\DataFlow;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(\App\Analyzer\ExperimentAnalyzer\DataFlow\InspectionException::class)]
final class InspectionExceptionTest extends TestCase
{
    public function testRejectionRetainsItsSourceLocation(): void
    {
        $exception = new \App\Analyzer\ExperimentAnalyzer\DataFlow\InspectionException('Line 12: references are unsupported.');
        self::assertSame('Line 12: references are unsupported.', $exception->getMessage());
    }
}
