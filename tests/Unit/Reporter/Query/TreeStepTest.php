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
    public function testAStepRemembersTheRelationThatReachedIt(): void
    {
        self::assertSame('calls', (new TreeStep('calls', 'App\Money::add'))->label);
    }

    public function testAStepRemembersTheSymbolItArrivedAt(): void
    {
        self::assertSame('App\Money::add', (new TreeStep('calls', 'App\Money::add'))->id);
    }

    public function testAStepIsTakenTheWayItsRelationPointsUnlessToldOtherwise(): void
    {
        self::assertFalse((new TreeStep('calls', 'App\Money::add'))->backwards);
    }

    public function testAStepRemembersThatItWasTakenAgainstItsRelation(): void
    {
        self::assertTrue((new TreeStep('calls', 'App\Money::add', true))->backwards);
    }

    public function testKeyTellsAStepApartByHowItWasReachedAndWhereItArrived(): void
    {
        self::assertSame("calls\0>\0App\\Money::add", (new TreeStep('calls', 'App\Money::add'))->key());
    }

    public function testKeyMarksAStepTakenAgainstItsRelation(): void
    {
        self::assertSame("calls\0<\0App\\Money::add", (new TreeStep('calls', 'App\Money::add', true))->key());
    }

    public function testKeyOfTheStartOfAPathIsTheSymbolItStartsAt(): void
    {
        self::assertSame("\0>\0App\\Money", (new TreeStep('', 'App\Money'))->key());
    }

    public function testKeyTellsApartTheSameSymbolReachedByTwoDifferentRelations(): void
    {
        self::assertNotSame((new TreeStep('calls', 'a'))->key(), (new TreeStep('extends', 'a'))->key());
    }

    public function testKeyTellsApartTheSameSymbolReachedByTheSameRelationEachWayRound(): void
    {
        self::assertNotSame((new TreeStep('calls', 'a'))->key(), (new TreeStep('calls', 'a', true))->key());
    }

    public function testKeyTellsApartARelationNameThatRunsIntoTheSymbolName(): void
    {
        self::assertNotSame((new TreeStep('call', 'sa'))->key(), (new TreeStep('calls', 'a'))->key());
    }
}
