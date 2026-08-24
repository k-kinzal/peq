<?php

declare(strict_types=1);

namespace Tests\Unit\Analyzer;

use App\Analyzer\AnalysisFailedException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * @internal
 */
#[CoversClass(AnalysisFailedException::class)]
#[Small]
final class AnalysisFailedExceptionTest extends TestCase
{
    public function testAnAnalysisFailureNeedsNoHandlerOnTheWayOut(): void
    {
        self::assertContains(RuntimeException::class, class_parents(new AnalysisFailedException('nothing could be analysed')) === false ? [] : class_parents(new AnalysisFailedException('nothing could be analysed')));
    }

    public function testAnAnalysisFailureCarriesTheFailureItCameFrom(): void
    {
        $cause = new RuntimeException('the container could not be built');

        self::assertSame($cause, (new AnalysisFailedException('nothing could be analysed', 0, $cause))->getPrevious());
    }

    public function testAnAnalysisFailureSaysWhatCouldNotBeDone(): void
    {
        self::assertSame('nothing could be analysed', (new AnalysisFailedException('nothing could be analysed'))->getMessage());
    }
}
