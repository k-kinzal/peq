<?php

declare(strict_types=1);

namespace Tests\Unit\Reporter\Query;

use App\Reporter\Query\TreeStep;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(TreeStep::class)]
#[Small]
final class TreeStepTest extends TestCase
{
    public function testAStepRemembersHowItWasReachedAndWhereItArrived(): void
    {
        self::assertSame('App\Money::add', (new TreeStep('calls', 'App\Money::add'))->id);
    }

    public function testKeyTellsAStepApartByHowItWasReachedAndWhereItArrived(): void
    {
        self::assertSame("calls\0App\\Money::add", (new TreeStep('calls', 'App\Money::add'))->key());
    }

    public function testKeyTellsApartTheSameSymbolReachedTwoDifferentWays(): void
    {
        self::assertNotSame((new TreeStep('calls', 'a'))->key(), (new TreeStep('extends', 'a'))->key());
    }
}
