<?php

declare(strict_types=1);

namespace Tests\Unit\Gql\Matching;

use App\Gql\Binding\BindingRow;
use App\Gql\Datum\BooleanDatum;
use App\Gql\Datum\DatumKind;
use App\Gql\Datum\DatumOrder;
use App\Gql\Datum\EdgeDatum;
use App\Gql\Datum\IntegerDatum;
use App\Gql\Datum\ListDatum;
use App\Gql\Datum\NodeDatum;
use App\Gql\Datum\NullDatum;
use App\Gql\Datum\PathDatum;
use App\Gql\Datum\StringDatum;
use App\Gql\Matching\MatchState;
use App\Gql\Matching\PathModeRule;
use App\Gql\Syntax\Pattern\PathMode;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(PathModeRule::class)]
#[UsesClass(BooleanDatum::class)]
#[UsesClass(DatumKind::class)]
#[UsesClass(DatumOrder::class)]
#[UsesClass(EdgeDatum::class)]
#[UsesClass(IntegerDatum::class)]
#[UsesClass(ListDatum::class)]
#[UsesClass(NodeDatum::class)]
#[UsesClass(NullDatum::class)]
#[UsesClass(PathDatum::class)]
#[UsesClass(StringDatum::class)]
#[UsesClass(BindingRow::class)]
#[UsesClass(MatchState::class)]
#[UsesClass(PathMode::class)]
#[Small]
final class PathModeRuleTest extends TestCase
{
    #[DataProvider('providerModesAndWhetherTheyCrossARelationTwice')]
    public function testAllowsEdgeReportsWhetherAMatchMayCrossARelationItHasCrossed(PathMode $mode, bool $allowed): void
    {
        $crossed = MatchState::before(BindingRow::unit())
            ->startingAt(new NodeDatum('a'))
            ->across(new EdgeDatum('e', [], [], 'a', 'b'), new NodeDatum('b'))
        ;

        self::assertSame($allowed, PathModeRule::allowsEdge($mode, $crossed, new EdgeDatum('e', [], [], 'a', 'b')));
    }

    /**
     * @return iterable<string, array{PathMode, bool}>
     */
    public static function providerModesAndWhetherTheyCrossARelationTwice(): iterable
    {
        yield 'the mode that forbids nothing' => [PathMode::Walk, true];

        yield 'the mode that forbids crossing a relation twice' => [PathMode::Trail, false];

        yield 'the mode that forbids meeting a symbol twice' => [PathMode::Simple, false];

        yield 'the mode that forbids a cycle as well' => [PathMode::Acyclic, false];
    }

    public function testAllowsEdgeAllowsARelationTheMatchHasNotCrossed(): void
    {
        $state = MatchState::before(BindingRow::unit());

        self::assertTrue(PathModeRule::allowsEdge(PathMode::Trail, $state, new EdgeDatum('e', [], [], 'a', 'b')));
    }

    #[DataProvider('providerModesAndWhetherTheyComeBackToTheStart')]
    public function testAllowsNodeReportsWhetherAMatchMayComeBackToWhereItStarted(PathMode $mode, bool $allowed): void
    {
        $crossed = MatchState::before(BindingRow::unit())
            ->startingAt(new NodeDatum('a'))
            ->across(new EdgeDatum('e', [], [], 'a', 'b'), new NodeDatum('b'))
        ;

        self::assertSame($allowed, PathModeRule::allowsNode($mode, $crossed, new NodeDatum('a')));
    }

    /**
     * @return iterable<string, array{PathMode, bool}>
     */
    public static function providerModesAndWhetherTheyComeBackToTheStart(): iterable
    {
        yield 'the mode that forbids nothing' => [PathMode::Walk, true];

        yield 'the mode that only forbids crossing a relation twice' => [PathMode::Trail, true];

        yield 'the mode that allows a cycle and nothing else repeated' => [PathMode::Simple, true];

        yield 'the mode that forbids a cycle too' => [PathMode::Acyclic, false];
    }

    #[DataProvider('providerModesAndWhetherTheyMeetASymbolTwice')]
    public function testAllowsNodeReportsWhetherAMatchMayMeetASymbolItHasMet(PathMode $mode, bool $allowed): void
    {
        $crossed = MatchState::before(BindingRow::unit())
            ->startingAt(new NodeDatum('a'))
            ->across(new EdgeDatum('e', [], [], 'a', 'b'), new NodeDatum('b'))
        ;

        self::assertSame($allowed, PathModeRule::allowsNode($mode, $crossed, new NodeDatum('b')));
    }

    /**
     * @return iterable<string, array{PathMode, bool}>
     */
    public static function providerModesAndWhetherTheyMeetASymbolTwice(): iterable
    {
        yield 'the mode that forbids nothing' => [PathMode::Walk, true];

        yield 'the mode that only forbids crossing a relation twice' => [PathMode::Trail, true];

        yield 'the mode that forbids meeting a symbol twice' => [PathMode::Simple, false];

        yield 'the mode that forbids that and a cycle' => [PathMode::Acyclic, false];
    }
}
