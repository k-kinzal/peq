<?php

declare(strict_types=1);

namespace Tests\Unit\Reporter\Diagram\Layout;

use App\Reporter\Diagram\Layout\DiagramLayout;
use App\Reporter\Diagram\Layout\LayoutItem;
use App\Reporter\Diagram\Layout\LayoutItemKind;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(DiagramLayout::class)]
#[UsesClass(LayoutItem::class)]
#[UsesClass(LayoutItemKind::class)]
#[Small]
final class DiagramLayoutTest extends TestCase
{
    public function testLinksFromReadsTheArrowsLeavingOneColumnForTheNext(): void
    {
        $layout = new DiagramLayout(
            [
                'a' => new LayoutItem('a', LayoutItemKind::Symbol, 'A', 0),
                'b' => new LayoutItem('b', LayoutItemKind::Symbol, 'B', 1),
                'c' => new LayoutItem('c', LayoutItemKind::Symbol, 'C', 1),
                'd' => new LayoutItem('d', LayoutItemKind::Symbol, 'D', 2),
            ],
            [['a'], ['b', 'c'], ['d']],
            ['a' => 0, 'b' => 0, 'c' => 2, 'd' => 0],
            [['a', 'b'], ['b', 'd'], ['a', 'c'], ['c', 'd']],
        );

        self::assertSame([['b', 'd'], ['c', 'd']], $layout->linksFrom(1));
    }

    public function testLinksFromIsNothingForAColumnNothingLeaves(): void
    {
        $layout = new DiagramLayout(
            [
                'a' => new LayoutItem('a', LayoutItemKind::Symbol, 'A', 0),
                'b' => new LayoutItem('b', LayoutItemKind::Symbol, 'B', 1),
            ],
            [['a'], ['b']],
            ['a' => 0, 'b' => 0],
            [['a', 'b']],
        );

        self::assertSame([], $layout->linksFrom(1));
    }

    public function testLinksFromIsNothingForALayoutWithNoArrows(): void
    {
        self::assertSame([], (new DiagramLayout([], [], [], []))->linksFrom(0));
    }

    public function testLeavesReportsAPlaceAnArrowLeaves(): void
    {
        $layout = new DiagramLayout([], [['a'], ['b']], ['a' => 0, 'b' => 0], [['a', 'b']]);

        self::assertTrue($layout->leaves('a'));
    }

    public function testLeavesDoesNotReportAPlaceAnArrowOnlyArrivesAt(): void
    {
        $layout = new DiagramLayout([], [['a'], ['b']], ['a' => 0, 'b' => 0], [['a', 'b']]);

        self::assertFalse($layout->leaves('b'));
    }

    public function testLeavesDoesNotReportAPlaceInALayoutWithNoArrows(): void
    {
        self::assertFalse((new DiagramLayout([], [], [], []))->leaves('a'));
    }
}
