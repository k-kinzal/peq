<?php

declare(strict_types=1);

namespace Tests\Unit\Gql\Matching;

use App\Gql\Binding\BindingRow;
use App\Gql\Datum\EdgeDatum;
use App\Gql\Datum\IntegerDatum;
use App\Gql\Datum\NodeDatum;
use App\Gql\Datum\PathDatum;
use App\Gql\Matching\MatchState;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(MatchState::class)]
#[UsesClass(BindingRow::class)]
#[UsesClass(EdgeDatum::class)]
#[UsesClass(IntegerDatum::class)]
#[UsesClass(NodeDatum::class)]
#[UsesClass(PathDatum::class)]
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

    public function testBeforeKeepsWhatIsAlreadyBound(): void
    {
        self::assertEquals(new BindingRow(['n' => new IntegerDatum(1)]), MatchState::before(new BindingRow(['n' => new IntegerDatum(1)]))->row);
    }

    public function testStartingAtBeginsAPathWhereTheAttemptStarted(): void
    {
        $started = MatchState::before(BindingRow::unit())->startingAt(new NodeDatum('a'));

        self::assertEquals(new PathDatum([new NodeDatum('a')]), $started->path);
    }

    public function testStartingAtStandsAtTheSymbolTheAttemptStartedFrom(): void
    {
        $a = new NodeDatum('a');

        self::assertSame($a, MatchState::before(BindingRow::unit())->startingAt($a)->current);
    }

    public function testAcrossMakesThePathOneLonger(): void
    {
        $crossed = MatchState::before(BindingRow::unit())
            ->startingAt(new NodeDatum('a'))
            ->across(new EdgeDatum('a>b', ['call'], [], 'a', 'b'), new NodeDatum('b'))
        ;

        self::assertEquals(
            new PathDatum([new NodeDatum('a'), new EdgeDatum('a>b', ['call'], [], 'a', 'b'), new NodeDatum('b')]),
            $crossed->path,
        );
    }

    public function testAcrossStandsAtTheSymbolTheRelationLedTo(): void
    {
        $b = new NodeDatum('b');
        $crossed = MatchState::before(BindingRow::unit())
            ->startingAt(new NodeDatum('a'))
            ->across(new EdgeDatum('a>b', ['call'], [], 'a', 'b'), $b)
        ;

        self::assertSame($b, $crossed->current);
    }

    public function testAcrossStartsAPathWhenTheAttemptHadNotStartedOne(): void
    {
        $crossed = MatchState::before(BindingRow::unit())
            ->across(new EdgeDatum('a>b', ['call'], [], 'a', 'b'), new NodeDatum('b'))
        ;

        self::assertEquals(new PathDatum([new NodeDatum('b')]), $crossed->path);
    }

    public function testBindLeavesEverythingElseAboutTheAttemptAlone(): void
    {
        $bound = MatchState::before(BindingRow::unit())->bind('n', new IntegerDatum(1));

        self::assertNull($bound->current);
    }

    public function testBindRemembersWhatTheNameWasBoundTo(): void
    {
        $bound = MatchState::before(BindingRow::unit())->bind('n', new IntegerDatum(1));

        self::assertEquals(new BindingRow(['n' => new IntegerDatum(1)]), $bound->row);
    }

    public function testCrossedReportsARelationTheAttemptHasNotCrossed(): void
    {
        self::assertFalse(MatchState::before(BindingRow::unit())->crossed('a>b'));
    }

    public function testCrossedReportsARelationTheAttemptHasCrossed(): void
    {
        $crossed = MatchState::before(BindingRow::unit())
            ->startingAt(new NodeDatum('a'))
            ->across(new EdgeDatum('a>b', ['call'], [], 'a', 'b'), new NodeDatum('b'))
        ;

        self::assertTrue($crossed->crossed('a>b'));
    }

    public function testMetReportsTheSymbolTheAttemptStartedFrom(): void
    {
        $started = MatchState::before(BindingRow::unit())->startingAt(new NodeDatum('a'));

        self::assertTrue($started->met('a'));
    }

    public function testMetReportsASymbolTheAttemptHasReached(): void
    {
        $crossed = MatchState::before(BindingRow::unit())
            ->startingAt(new NodeDatum('a'))
            ->across(new EdgeDatum('a>b', ['call'], [], 'a', 'b'), new NodeDatum('b'))
        ;

        self::assertTrue($crossed->met('b'));
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
            ->across(new EdgeDatum('a>b', ['call'], [], 'a', 'b'), new NodeDatum('b'))
        ;

        self::assertFalse($crossed->startedAt('b'));
    }

    public function testWithRowReplacesWhatTheAttemptHasBound(): void
    {
        $state = MatchState::before(new BindingRow(['n' => new NodeDatum('old')]));

        self::assertEquals(new BindingRow(['m' => new NodeDatum('new')]), $state->withRow(new BindingRow(['m' => new NodeDatum('new')]))->row);
    }

    public function testWithRowKeepsThePathTheAttemptHasWalked(): void
    {
        $state = MatchState::before(BindingRow::unit())->startingAt(new NodeDatum('a'));

        self::assertEquals(new PathDatum([new NodeDatum('a')]), $state->withRow(BindingRow::unit())->path);
    }
}
