<?php

declare(strict_types=1);

namespace Tests\Unit\Analyzer\Graph;

use App\Analyzer\Graph\GraphDifference;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(GraphDifference::class)]
#[Small]
final class GraphDifferenceTest extends TestCase
{
    public function testIsEmptyHoldsWhenNeitherGraphHoldsAnythingTheOtherLacks(): void
    {
        self::assertTrue((new GraphDifference([], [], [], []))->isEmpty());
    }

    public function testIsEmptyFailsWhenOneSymbolIsOnlyOnOneSide(): void
    {
        self::assertFalse((new GraphDifference(['class App\A resolved=yes at nowhere'], [], [], []))->isEmpty());
    }

    public function testIsEmptyFailsWhenOneRelationIsOnlyOnOneSide(): void
    {
        self::assertFalse((new GraphDifference([], [], [], ['method-call App\A::a -> App\B::b at /a.php:1:1']))->isEmpty());
    }

    public function testCountAddsUpEverythingTheTwoGraphsDisagreeAbout(): void
    {
        self::assertSame(4, (new GraphDifference(['a'], ['b'], ['c'], ['d']))->count());
    }

    public function testCountIsNothingWhenTheGraphsAgree(): void
    {
        self::assertSame(0, (new GraphDifference([], [], [], []))->count());
    }

    public function testDescribeSaysSoWhenTheGraphsAgree(): void
    {
        self::assertSame('the graphs are identical', (new GraphDifference([], [], [], []))->describe());
    }

    public function testDescribeNamesWhichSideALostSymbolIsOn(): void
    {
        self::assertStringContainsString(
            'symbols only the reference engine found (1):',
            (new GraphDifference(['class App\A resolved=yes at nowhere'], [], [], []))->describe(),
        );
    }

    public function testDescribeNamesWhichSideAnInventedRelationIsOn(): void
    {
        self::assertStringContainsString(
            'relations only this engine found (1):',
            (new GraphDifference([], [], [], ['method-call App\A::a -> App\B::b at /a.php:1:1']))->describe(),
        );
    }

    public function testSectionLeavesOutASideThatHoldsNothing(): void
    {
        self::assertSame([], GraphDifference::section('lost', []));
    }

    public function testSectionShortensASideTooLongToRead(): void
    {
        self::assertStringContainsString('... and 8 more', implode("\n", GraphDifference::section('lost', array_map(strval(...), range(1, 20)))));
    }

    public function testSectionWritesOutASideShortEnoughToRead(): void
    {
        self::assertSame(['  lost (2):', '    one', '    two'], GraphDifference::section('lost', ['one', 'two']));
    }
}
