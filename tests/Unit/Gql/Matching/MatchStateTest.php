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
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(MatchState::class)]
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
#[Small]
final class MatchStateTest extends TestCase
{
    public function testBeforeStandsNowhereUntilTheAttemptHasStarted(): void
    {
        self::assertNull(MatchState::before(BindingRow::unit())->current);
    }

    public function testBeforeHasNoPathUntilTheAttemptHasStarted(): void
    {
        self::assertNull(MatchState::before(BindingRow::unit())->path);
    }

    public function testStartingAtBeginsAPathWhereTheAttemptStarted(): void
    {
        $started = MatchState::before(BindingRow::unit())->startingAt(new NodeDatum('a'));

        self::assertSame(0, $started->path?->length());
    }

    public function testStartingAtStandsAtTheSymbolTheAttemptStartedFrom(): void
    {
        $started = MatchState::before(BindingRow::unit())->startingAt(new NodeDatum('a'));

        self::assertSame('a', $started->current?->id);
    }

    public function testAcrossMakesThePathOneLonger(): void
    {
        $crossed = MatchState::before(BindingRow::unit())
            ->startingAt(new NodeDatum('a'))
            ->across(new EdgeDatum('e', [], [], 'a', 'b'), new NodeDatum('b'))
        ;

        self::assertSame(1, $crossed->path?->length());
    }

    public function testAcrossStartsAPathWhenTheAttemptHadNotStartedOne(): void
    {
        $crossed = MatchState::before(BindingRow::unit())
            ->across(new EdgeDatum('e', [], [], 'a', 'b'), new NodeDatum('b'))
        ;

        self::assertSame(0, $crossed->path?->length());
    }

    public function testBindLeavesEverythingElseAboutTheAttemptAlone(): void
    {
        $bound = MatchState::before(BindingRow::unit())->bind('n', new IntegerDatum(1));

        self::assertNull($bound->current);
    }

    public function testBindRemembersWhatTheNameWasBoundTo(): void
    {
        $bound = MatchState::before(BindingRow::unit())->bind('n', new IntegerDatum(1));

        self::assertSame('1', $bound->row->value('n')->toText());
    }

    public function testCrossedReportsARelationTheAttemptHasNotCrossed(): void
    {
        self::assertFalse(MatchState::before(BindingRow::unit())->crossed('e'));
    }

    public function testCrossedReportsARelationTheAttemptHasCrossed(): void
    {
        $crossed = MatchState::before(BindingRow::unit())
            ->startingAt(new NodeDatum('a'))
            ->across(new EdgeDatum('e', [], [], 'a', 'b'), new NodeDatum('b'))
        ;

        self::assertTrue($crossed->crossed('e'));
    }

    public function testMetReportsTheSymbolTheAttemptStartedFrom(): void
    {
        $started = MatchState::before(BindingRow::unit())->startingAt(new NodeDatum('a'));

        self::assertTrue($started->met('a'));
    }

    public function testMetReportsASymbolTheAttemptHasNotReached(): void
    {
        $started = MatchState::before(BindingRow::unit())->startingAt(new NodeDatum('a'));

        self::assertFalse($started->met('b'));
    }

    public function testStartedAtReportsTheSymbolTheAttemptStartedFrom(): void
    {
        $started = MatchState::before(BindingRow::unit())->startingAt(new NodeDatum('a'));

        self::assertTrue($started->startedAt('a'));
    }

    public function testStartedAtDoesNotReportASymbolTheAttemptMerelyReached(): void
    {
        $crossed = MatchState::before(BindingRow::unit())
            ->startingAt(new NodeDatum('a'))
            ->across(new EdgeDatum('e', [], [], 'a', 'b'), new NodeDatum('b'))
        ;

        self::assertFalse($crossed->startedAt('b'));
    }
}
