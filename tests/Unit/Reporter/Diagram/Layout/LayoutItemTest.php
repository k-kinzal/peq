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
#[CoversClass(LayoutItem::class)]
#[UsesClass(LayoutItemKind::class)]
#[Small]
final class LayoutItemTest extends TestCase
{
    public function testSymbolIsThePlaceASymbolTakesStandingForItself(): void
    {
        self::assertEquals(
            new LayoutItem("symbol\0App\\Invoice", LayoutItemKind::Symbol, 'App\Invoice', 2),
            LayoutItem::symbol('App\Invoice', 2),
        );
    }

    public function testReferenceIsThePlaceAnArrowPointingAtASymbolDrawnElsewhereTakes(): void
    {
        self::assertEquals(
            new LayoutItem("reference\0App\\B\0App\\A", LayoutItemKind::Reference, 'App\A', 1),
            LayoutItem::reference('App\B', 'App\A', 1, false),
        );
    }

    public function testReferenceClosingACycleIsARecursion(): void
    {
        self::assertEquals(
            new LayoutItem("reference\0App\\B\0App\\A", LayoutItemKind::Recursion, 'App\A', 3),
            LayoutItem::reference('App\B', 'App\A', 3, true),
        );
    }

    public function testReferenceTellsApartTwoArrowsPointingAtOneSymbol(): void
    {
        self::assertNotSame(
            LayoutItem::reference('App\B', 'App\A', 1, false)->key,
            LayoutItem::reference('App\C', 'App\A', 1, false)->key,
        );
    }

    public function testReferenceTellsApartTwoArrowsBetweenTheSameSymbolsReadEitherWay(): void
    {
        self::assertNotSame(
            LayoutItem::reference('App\B', 'App\A', 1, false)->key,
            LayoutItem::reference('App\A', 'App\B', 1, false)->key,
        );
    }

    public function testKeyOfIsTheKeyOfThePlaceASymbolTakesInAnyColumn(): void
    {
        self::assertSame(LayoutItem::symbol('App\A', 3)->key, LayoutItem::keyOf('App\A'));
    }

    public function testKeyOfIsNeverTheKeyOfAReferenceToTheSymbol(): void
    {
        self::assertNotSame(LayoutItem::reference('App\A', 'App\A', 1, true)->key, LayoutItem::keyOf('App\A'));
    }

    public function testKeyOfCannotBeSpelledByASymbolNamedLikeAnotherKey(): void
    {
        self::assertNotSame(LayoutItem::keyOf("reference\0App\\B\0App\\A"), LayoutItem::reference('App\B', 'App\A', 1, false)->key);
    }
}
