<?php

declare(strict_types=1);

namespace Tests\Unit\Action\Experimental;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(\App\Action\Experimental\InspectionRejected::class)]
final class InspectionRejectedTest extends TestCase
{
    public function testRejectionRetainsItsCause(): void
    {
        $cause = new \App\Analyzer\ExperimentAnalyzer\DataFlow\InspectionException('unsupported');
        $rejection = new \App\Action\Experimental\InspectionRejected('rejected', previous: $cause);
        self::assertSame($cause, $rejection->getPrevious());
    }
}
