<?php

declare(strict_types=1);

namespace Tests\Unit\Reporter\Diagram\Layout;

use App\Reporter\Diagram\Layout\LayoutItem;
use App\Reporter\Diagram\Layout\LayoutItemKind;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(LayoutItemKind::class)]
#[UsesClass(LayoutItem::class)]
#[Small]
final class LayoutItemKindTest extends TestCase
{
    public function testAPlaceIsTakenByASymbolOrByOneOfTwoKindsOfReference(): void
    {
        self::assertSame(
            [LayoutItemKind::Symbol, LayoutItemKind::Reference, LayoutItemKind::Recursion],
            LayoutItemKind::cases(),
        );
    }

    public function testASymbolTakesThePlaceOfASymbol(): void
    {
        self::assertSame(LayoutItemKind::Symbol, LayoutItem::symbol('App\A', 0)->kind);
    }

    public function testAnArrowToASymbolDrawnElsewhereTakesThePlaceOfAReference(): void
    {
        self::assertSame(LayoutItemKind::Reference, LayoutItem::reference('App\B', 'App\A', 1, false)->kind);
    }

    public function testAnArrowClosingACycleTakesThePlaceOfARecursion(): void
    {
        self::assertSame(LayoutItemKind::Recursion, LayoutItem::reference('App\B', 'App\A', 1, true)->kind);
    }
}
