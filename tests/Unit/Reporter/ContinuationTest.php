<?php

declare(strict_types=1);

namespace Tests\Unit\Reporter;

use App\Reporter\Continuation;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(Continuation::class)]
#[Small]
final class ContinuationTest extends TestCase
{
    public function testDescendsIsTrueOnlyForANodeTheReportOpensOut(): void
    {
        self::assertTrue(Continuation::Descends->descends());
    }

    #[DataProvider('providerEveryWayAReportStops')]
    public function testDescendsIsFalseForEveryReasonAReportStops(Continuation $continuation): void
    {
        self::assertFalse($continuation->descends());
    }

    /**
     * @return iterable<string, array{Continuation}>
     */
    public static function providerEveryWayAReportStops(): iterable
    {
        yield 'a cycle back onto the current path' => [Continuation::Cycle];

        yield 'a symbol already expanded elsewhere' => [Continuation::Repeat];

        yield 'a symbol outside the analyzed sources' => [Continuation::Leaf];

        yield 'a symbol past the level bound' => [Continuation::Beyond];
    }

    #[DataProvider('providerEveryContinuationWrittenIntoTheReport')]
    public function testReportedIsTrueForANodeTheReportWritesDown(Continuation $continuation): void
    {
        self::assertTrue($continuation->reported());
    }

    /**
     * @return iterable<string, array{Continuation}>
     */
    public static function providerEveryContinuationWrittenIntoTheReport(): iterable
    {
        yield 'a symbol the report opens out' => [Continuation::Descends];

        yield 'a cycle back onto the current path' => [Continuation::Cycle];

        yield 'a symbol already expanded elsewhere' => [Continuation::Repeat];

        yield 'a symbol outside the analyzed sources' => [Continuation::Leaf];
    }

    public function testReportedIsFalseForWhatLiesPastTheLevelBound(): void
    {
        self::assertFalse(Continuation::Beyond->reported());
    }

    #[DataProvider('providerTheReasonsWorthNaming')]
    public function testMarkerNamesTheReasonABranchWasCut(Continuation $continuation, string $expected): void
    {
        self::assertSame($expected, $continuation->marker());
    }

    /**
     * @return iterable<string, array{Continuation, string}>
     */
    public static function providerTheReasonsWorthNaming(): iterable
    {
        yield 'a cycle is not an empty branch' => [Continuation::Cycle, 'recursive'];

        yield 'a repeat is expanded somewhere else' => [Continuation::Repeat, 'repeated'];
    }

    #[DataProvider('providerTheReasonsThatNeedNoWord')]
    public function testMarkerNamesNothingWhenThereIsNothingToExplain(Continuation $continuation): void
    {
        self::assertNull($continuation->marker());
    }

    /**
     * @return iterable<string, array{Continuation}>
     */
    public static function providerTheReasonsThatNeedNoWord(): iterable
    {
        yield 'a symbol the report opens out' => [Continuation::Descends];

        yield 'a symbol whose kind says it has nothing below it' => [Continuation::Leaf];

        yield 'a symbol the reader asked not to be told about' => [Continuation::Beyond];
    }

    #[DataProvider('providerEveryContinuation')]
    public function testMarkerIsWrittenAsALowerCaseWordOrNothingAtAll(Continuation $continuation): void
    {
        self::assertContains($continuation->marker(), [null, 'recursive', 'repeated']);
    }

    /**
     * @return iterable<string, array{Continuation}>
     */
    public static function providerEveryContinuation(): iterable
    {
        foreach (Continuation::cases() as $continuation) {
            yield $continuation->name => [$continuation];
        }
    }
}
